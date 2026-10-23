<?php

namespace App\Services;

class PerformanceExport
{
    public function xlsx(array $rows): string
    {
        return app(BrandedSpreadsheet::class)->xlsx($rows);
    }

    public function pdf(array $rows): string
    {
        $columns = max(1, ...array_map('count', $rows ?: [[]]));
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml(view('exports.branded-report', compact('rows', 'columns'))->render());
        $dompdf->setPaper($columns > 9 ? 'A3' : 'A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
