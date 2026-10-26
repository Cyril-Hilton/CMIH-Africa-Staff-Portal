<?php
namespace App\Services\Creative;
class CreativeReportingPeriod
{
    /** Use original weekly source periods, without manufacturing milestone timestamps. */
    public static function includeHistory($query,$from,$to):void
    {
        $query->orWhereExists(function($source)use($from,$to){
            $source->selectRaw('1')->from('creative_sources as history')->join('weekly_consolidated_items as weekly','weekly.id','=','history.source_id')
                ->whereColumn('history.creative_request_id','creative_requests.id')->where('history.source_type','weekly')
                ->whereDate('weekly.week_start','<=',$to)->whereDate('weekly.week_end','>=',$from);
        });
        $query->orWhere(function($source)use($from,$to){
            $source->where('legacy',true)->whereBetween('source_recorded_at',[$from,$to])->whereNotExists(function($weekly){
                $weekly->selectRaw('1')->from('creative_sources as history')->whereColumn('history.creative_request_id','creative_requests.id')->where('history.source_type','weekly');
            });
        });
    }
}
