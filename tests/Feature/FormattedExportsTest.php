<?php

namespace Tests\Feature;

use App\Services\BrandedSpreadsheet;
use Tests\TestCase;

class FormattedExportsTest extends TestCase
{
    public function test_workbook_is_real_excel_styled_filtered_and_stores_formula_like_values_as_text(): void
    {
        $bytes = app(BrandedSpreadsheet::class)->fromCsv("Name,Phone,Notes\nCreator,0241111111,=HYPERLINK(\"bad\")\n");
        $path = tempnam(sys_get_temp_dir(), 'excel-test-');
        file_put_contents($path, $bytes);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        try {
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $styles = $zip->getFromName('xl/styles.xml');
            $this->assertStringContainsString('0241111111', $sheet);
            $this->assertStringContainsString('inlineStr', $sheet);
            $this->assertStringNotContainsString('<f>', $sheet);
            $this->assertStringContainsString('autoFilter', $sheet);
            $this->assertStringContainsString('state="frozen"', $sheet);
            $this->assertStringContainsString('FF17243A', $styles);
            $this->assertNotFalse(simplexml_load_string($sheet));
        } finally {
            $zip->close();
            unlink($path);
        }
    }

    public function test_legacy_csv_download_routes_return_excel_and_keep_private_headers(): void
    {
        $response = (new \App\Http\Middleware\FormatSpreadsheetDownloads)->handle(\Illuminate\Http\Request::create('/portal/export'), fn () => response()->streamDownload(function () {
            echo "Name,Phone\nExample,0241111111\n";
        }, 'report.csv', ['Content-Type' => 'text/csv']));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('report.xlsx', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $response->getContent());
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }
}
