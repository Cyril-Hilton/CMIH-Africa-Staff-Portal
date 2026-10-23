<?php

namespace App\Services;

class BrandedSpreadsheet
{
    public function fromCsv(string $csv): string
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $this->xlsx($rows);
    }

    public function csvForImport(string $path): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Invalid Excel workbook.');
        }
        try {
            foreach (['xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml'] as $entry) {
                $stat = $zip->statName($entry);
                if ($stat && $stat['size'] > 20 * 1024 * 1024) {
                    throw new \RuntimeException('Excel worksheet is too large.');
                }
            }
            $load = function ($xml) {
                if (! $xml || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
                    throw new \RuntimeException('Invalid worksheet XML.');
                }
                $node = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
                if (! $node) {
                    throw new \RuntimeException('Invalid worksheet XML.');
                }
                $node->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                return $node;
            };
            $shared = [];
            if ($xml = $zip->getFromName('xl/sharedStrings.xml')) {
                foreach ($load($xml)->xpath('//s:si') as $item) {
                    $item->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $shared[] = implode('', array_map('strval', $item->xpath('.//s:t')));
                }
            }
            $sheet = $load($zip->getFromName('xl/worksheets/sheet1.xml'));
            if ($sheet->xpath('//s:f')) {
                throw new \RuntimeException('Use values rather than formulas in the import workbook.');
            }
            $handle = fopen('php://temp', 'w+');
            foreach ($sheet->xpath('//s:sheetData/s:row') as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    preg_match('/^([A-Z]+)/', (string) $cell['r'], $m);
                    $index = 0;
                    foreach (str_split($m[1] ?? 'A') as $letter) {
                        $index = $index * 26 + ord($letter) - 64;
                    }
                    if ($index > 500) {
                        throw new \RuntimeException('Too many worksheet columns.');
                    }
                    while (count($values) < $index) {
                        $values[] = '';
                    }
                    $type = (string) $cell['t'];
                    $values[$index - 1] = $type === 's' ? ($shared[(int) $cell->v] ?? '') : ($type === 'inlineStr' ? (string) $cell->is->t : (string) $cell->v);
                }
                fputcsv($handle, $values, ',', '"', '');
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return $csv;
        } finally {
            $zip->close();
        }
    }

    public function xlsx(array $rows): string
    {
        $escape = fn ($value) => htmlspecialchars(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $count = max(1, ...array_map('count', $rows ?: [[]]));
        $header = 0;
        foreach ($rows as $i => $row) {
            if (count($row) === $count) {
                $header = $i;
                break;
            }
        }
        $column = function (int $n): string {
            $name = '';
            while ($n > 0) {
                $n--;
                $name = chr(65 + $n % 26).$name;
                $n = intdiv($n, 26);
            }

            return $name;
        };
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="'.($header + 1).'" topLeftCell="A'.($header + 2).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>';
        for ($c = 0; $c < $count; $c++) {
            $length = 14;
            foreach (array_slice($rows, 0, 250) as $row) {
                $length = max($length, mb_strlen((string) ($row[$c] ?? '')));
            } $xml .= '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.min(44, $length + 3).'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        foreach ($rows as $i => $row) {
            $isHeader = $i === $header || in_array($row[0] ?? '', ['KPI', 'Commitment ID']);
            $style = $isHeader ? 1 : ($i < $header ? 4 : ($i % 2 ? 2 : 3));
            $height = $i === $header ? 34 : 32;
            foreach ($row as $v) {
                if (mb_strlen((string) $v) > 70 || str_contains((string) $v, "\n")) {
                    $height = 60;
                    break;
                }
            }
            $xml .= '<row r="'.($i + 1).'" ht="'.$height.'" customHeight="1">';
            foreach ($row as $c => $value) {
                $ref = $column($c + 1).($i + 1);
                $xml .= '<c r="'.$ref.'" s="'.$style.'"';
                if (is_int($value) || is_float($value)) {
                    $xml .= '><v>'.$value.'</v></c>';
                } else {
                    $xml .= ' t="inlineStr"><is><t xml:space="preserve">'.$escape($value ?? '').'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $last = $column($count).max(1, count($rows));
        $xml .= '</sheetData><autoFilter ref="A'.($header + 1).':'.$last.'"/><printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/><headerFooter><oddHeader>&amp;LCMIH AFRICA&amp;RReport</oddHeader><oddFooter>&amp;LConfidential · CMIH Africa&amp;RPage &amp;P of &amp;N</oddFooter></headerFooter></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'cmih-xlsx-');
        $zip = new \ZipArchive;
        try {
            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create Excel export.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="CMIH Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
            $zip->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><color rgb="FF17243A"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF17243A"/><name val="Calibri"/></font></fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF17243A"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0F5FA"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/></patternFill></fill></fills><borders count="2"><border/><border><bottom style="hair"><color rgb="FFD9E2EC"/></bottom></border></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="5"><xf fontId="0" fillId="0" borderId="0"/><xf fontId="1" fillId="2" borderId="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf fontId="0" fillId="3" borderId="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf fontId="0" fillId="4" borderId="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf fontId="2" fillId="4" borderId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
            $zip->close();

            return file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
