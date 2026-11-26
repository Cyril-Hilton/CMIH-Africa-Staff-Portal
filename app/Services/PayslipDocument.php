<?php
namespace App\Services;

use App\Models\Payslip;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class PayslipDocument
{
    public function logo(string $theme = 'light'): string
    {
        $path = public_path('images/logo/logo-'.($theme === 'dark' ? 'dark' : 'light').'.png');
        if (!is_file($path)) {
            $path = public_path('images/CMIH WEB ASSETS/Company logo/CMIH Logo_light theme.png');
        }
        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }

    public function filename(Payslip $payslip): string
    {
        return 'CMIH-Payslip-'.preg_replace('/[^0-9-]/', '', $payslip->period).'.pdf';
    }

    public function render(Payslip $payslip): string
    {
        $cache = storage_path('app/private/payslip-pdf-fonts');
        File::ensureDirectoryExists($cache);
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'fontCache' => $cache, 'tempDir' => $cache]);
        $options->setChroot([public_path('images'), base_path('vendor/dompdf')]);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('portal.payroll.payslip-pdf', ['payslip' => $payslip, 'staff' => $payslip->user, 'logo' => $this->logo()])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->output();
    }
}
