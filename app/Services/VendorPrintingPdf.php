<?php

namespace App\Services;

use App\Models\VendorPrintingRequest;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class VendorPrintingPdf
{
    public function html(VendorPrintingRequest $record): string
    {
        $logo = public_path('images/logo/logo-light.png');

        return view('portal.printing-requests.pdf', ['record' => $record, 'logo' => 'data:image/png;base64,'.base64_encode(file_get_contents($logo))])->render();
    }

    public function render(VendorPrintingRequest $record): string
    {
        $cache = storage_path('app/private/printing-pdf-fonts');
        File::ensureDirectoryExists($cache);
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'fontCache' => $cache, 'tempDir' => $cache]);
        $options->setChroot([public_path('images'), base_path('vendor/dompdf')]);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($record), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $pdf->getCanvas()->page_text(744, 568, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.38, 0.42, 0.48]);

        return $pdf->output();
    }
}
