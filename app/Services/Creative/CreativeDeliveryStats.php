<?php

namespace App\Services\Creative;

use Illuminate\Support\{Carbon, Collection};

class CreativeDeliveryStats
{
    public function build(Collection $items, ?string $from=null, ?string $to=null): array
    {
        $start=Carbon::parse($from??'1900-01-01')->startOfDay();
        $end=Carbon::parse($to??today())->endOfDay()->min(now());
        $due=$items->filter(function($brief) use($start,$end) {
            $date=CreativeDeadlines::scoringDue($brief);
            return $date && $date->betweenIncluded($start,$end);
        });
        $onTime=$due->filter(fn($b)=>$b->first_submitted_at && $b->first_submitted_at->lte(CreativeDeadlines::scoringDue($b)))->count();
        $verified=$items->filter(fn($b)=>$b->stage==='completed' && $b->completed_at);
        $quality=$verified->whereNotNull('quality_score');
        return ['total'=>$items->count(),'verified'=>$verified->count(),'due'=>$due->count(),'on_time'=>$onTime,
            'on_time_credit'=>$due->isEmpty()?0.0:round(100*$onTime/$due->count(),1),
            'delivery_credit'=>$items->isEmpty()?0.0:round(100*$verified->count()/$items->count(),1),
            'quality_credit'=>$verified->isEmpty()?0.0:round($quality->sum('quality_score')/$verified->count(),1),
            'quality_measured'=>$quality->count(),'quality_expected'=>$verified->count(),
            'deadline_setup_gaps'=>$items->filter(fn($b)=>!CreativeDeadlines::scoringDue($b))->count(),
            'review_overdue'=>$items->filter(fn($b)=>$b->stage==='in_review' && $b->review_due_at?->isPast())->count(),
            'correction_rounds'=>$items->sum(fn($b)=>max(0,$b->iteration-1))];
    }
}
