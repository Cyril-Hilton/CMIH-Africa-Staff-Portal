<?php

namespace App\Services;

use App\Models\LeaveApplication;
use App\Models\PassOutRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Approved availability only: never expose absence type, reasons or HR notes. */
class ApprovedAvailability
{
    public function windows(Carbon $from, Carbon $to): Collection
    {
        $rows = LeaveApplication::where('status', 'approved')->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get()->map(fn ($l) => ['key' => 'leave:'.$l->id, 'user_id' => (int) $l->user_id, 'start' => $l->start_date->copy()->startOfDay(), 'end' => $l->end_date->copy()->endOfDay(), 'label' => 'Approved absence']);

        return $rows->concat(PassOutRequest::where('status', 'approved')->whereBetween('request_date', [$from->toDateString(), $to->toDateString()])->get()->map(fn ($p) => ['key' => 'passout:'.$p->id, 'user_id' => (int) $p->user_id, 'start' => Carbon::parse($p->request_date->toDateString().' '.$p->departure_time), 'end' => $p->request_date->copy()->endOfDay(), 'label' => 'Approved afternoon pass-out']));
    }
}
