<?php

namespace Tests\Concerns;

trait ReadsExcelExports
{
    private function excelText(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-qa-');
        try {
            file_put_contents($path, $bytes);

            return app(\App\Services\BrandedSpreadsheet::class)->csvForImport($path);
        } finally {
            unlink($path);
        }
    }
}
