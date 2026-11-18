<?php

namespace App\Services\Creative;

use App\Models\{CreativeRequest, PerformanceEvent};
use Illuminate\Support\Carbon;

class CreativeDeadlines
{
    /** Submitted work uses its frozen HOD deadline; later replanning cannot rewrite a result. */
    public static function scoringDue(CreativeRequest $brief): ?Carbon
    {
        if (!$brief->first_submitted_at) return $brief->internal_eta;
        $metadata=$brief->firstSubmission?->metadata??[];
        if (array_key_exists('internal_eta',$metadata)) return !empty($metadata['internal_eta'])?Carbon::parse($metadata['internal_eta']):null;
        $event=$brief->relationLoaded('hodDeadlineSnapshots')?$brief->hodDeadlineSnapshots->first():$brief->hodDeadlineSnapshots()->first();
        return !empty($event?->after['internal_eta'])?Carbon::parse($event->after['internal_eta']):null;
    }
}
