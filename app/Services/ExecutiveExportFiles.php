<?php

namespace App\Services;

class ExecutiveExportFiles
{
    public function xlsx(array $report): string
    {
        $sheets = ['Export scope' => [['CMIH AFRICA · Executive export'], ['Reporting dates', $report['period']], ['Generated', $report['generated']], ['Requested by', $report['requestedBy']], ['Selected records', $report['records']->count()], ['Scope', $report['input']['scope']], ['Notes', 'Current registers / inventory remain current snapshots. Recorded status is not independent verification. Each section retains its original source meaning.'], ['Selection', json_encode(\Illuminate\Support\Arr::except($report['input'], ['records', 'selection_mode']), JSON_UNESCAPED_UNICODE)]]];
        foreach ($report['records']->groupBy('section') as $section => $records) {
            $rows = [['Record ID', 'Record', 'Owner', 'Field', 'Value', 'Original record']];
            foreach ($records as $record) {
                foreach ($record['fields'] ?: ['Source' => 'Original record'] as $field => $value) {
                    $rows[] = [$record['key'], $record['title'], $record['owner'], $field, $value, $record['url']];
                }
            }
            $sheets[substr(ExecutiveExport::SECTIONS[$section], 0, 31)] = $rows;
        }
        $path = tempnam(sys_get_temp_dir(), 'cmih-executive-');
        $part = tempnam(sys_get_temp_dir(), 'cmih-sheet-');
        $zip = new \ZipArchive;
        try {
            if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create export workbook.');
            }
            $types = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
            $book = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
            $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
            $n = 0;
            foreach ($sheets as $title => $rows) {
                $n++;
                file_put_contents($part, app(BrandedSpreadsheet::class)->xlsx($rows));
                $source = new \ZipArchive;
                if ($source->open($part) !== true) {
                    throw new \RuntimeException('Unable to read export worksheet.');
                }
                $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', $source->getFromName('xl/worksheets/sheet1.xml'));
                if ($n === 1) {
                    $zip->addFromString('xl/styles.xml', $source->getFromName('xl/styles.xml'));
                }
                $source->close();
                $title = htmlspecialchars(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $book .= '<sheet name="'.$title.'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
                $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
                $types .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            }
            $rels .= '<Relationship Id="rId'.($n + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?>'.$types.'</Types>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?>'.$book.'</sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?>'.$rels);
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->close();

            return file_get_contents($path);
        } finally {
            if (is_file($part)) {
                unlink($part);
            }
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function pdf(array $report): string
    {
        $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml(view('portal.executive.export-pdf', compact('report'))->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
