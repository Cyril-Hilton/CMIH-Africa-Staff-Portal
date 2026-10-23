<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PerformanceReportPack;
use App\Services\PerformanceAccess;
use Illuminate\Http\Request;

class PerformanceReportPackController extends Controller
{
    public function index(Request $r)
    {
        $packs = PerformanceReportPack::query()->when(! PerformanceAccess::executive($r->user()), fn ($q) => $q->where('user_id', $r->user()->id))->orderByDesc('created_at')->paginate(20);

        return view('portal.performance.report-packs', compact('packs'));
    }

    public function show(Request $r, PerformanceReportPack $pack, string $format = 'json')
    {
        abort_unless((int) $pack->user_id === $r->user()->id || PerformanceAccess::executive($r->user()), 403);
        abort_unless(in_array($format, ['json', 'pdf', 'xlsx']), 404);
        if ($format === 'json') {
            return response()->json(['pack_id' => $pack->id, 'sha256' => $pack->sha256, 'data' => $pack->payload]);
        }
        $rows = $pack->payload['rows'];
        $rows[] = ['Snapshot SHA-256', $pack->sha256];
        $bytes = app(\App\Services\PerformanceExport::class)->{$format}($rows);

        return response($bytes)->header('Content-Type', $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition', 'attachment; filename="CMIH-report-pack-'.$pack->id.'.'.$format.'"');
    }
}
