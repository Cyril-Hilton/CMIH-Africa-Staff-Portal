<?php

namespace App\Http\Middleware;

use App\Services\BrandedSpreadsheet;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormatSpreadsheetDownloads
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $disposition = $response->headers->get('Content-Disposition', '');
        $type = $response->headers->get('Content-Type', '');
        // Legacy endpoints keep their existing query scopes and authorization; only their download representation changes.
        if ($response->isSuccessful() && str_contains(strtolower($disposition), 'attachment') && (str_contains($type, 'text/csv') || preg_match('/\.csv(?:[";]|$)/i', $disposition) || (str_contains($type, 'application/vnd.ms-excel') && $response instanceof StreamedResponse))) {
            if ($response instanceof StreamedResponse) {
                ob_start();
                try {
                    $response->sendContent();
                    $content = ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            } else {
                $content = $response->getContent();
            }
            $headers = $response->headers->all();
            unset($headers['content-length'],$headers['content-type'],$headers['content-disposition']);
            $filename = 'cmih-report.xlsx';
            if (preg_match('/filename="?([^";]+)"?/i', $disposition, $match)) {
                $filename = preg_replace('/\.(csv|xls)$/i', '.xlsx', basename($match[1]));
            }

            return response(app(BrandedSpreadsheet::class)->fromCsv($content), 200, $headers)->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition', 'attachment; filename="'.$filename.'"')->header('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
