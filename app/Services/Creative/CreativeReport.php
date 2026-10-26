<?php
namespace App\Services\Creative;

use App\Models\{CreativeRequest, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/** Period reports use the complete authorised cohort, independently of board pagination. */
class CreativeReport
{
    public function build(User $actor, string $period, string $anchor, array $filters = [], bool $images = false): array
    {
        $start = Carbon::parse($anchor, config('creative.timezone'))->startOfDay();
        $start = match ($period) {
            'monthly' => $start->startOfMonth(), 'quarterly' => $start->startOfQuarter(),
            'yearly' => $start->startOfYear(), default => $start->startOfWeek(),
        };
        $end = match ($period) {
            'monthly' => $start->copy()->endOfMonth(), 'quarterly' => $start->copy()->endOfQuarter(),
            'yearly' => $start->copy()->endOfYear(), default => $start->copy()->endOfWeek(),
        };
        $query = CreativeRequest::visibleTo($actor)->where('is_group',false)
            ->when($filters['assignee'] ?? null, fn($q, $v) => $q->where('assignee_id', $v))
            ->when($filters['q'] ?? null, fn($q, $v) => $q->where(fn($q) => $q->where('title', 'like', '%'.$v.'%')->orWhere('client', 'like', '%'.$v.'%')->orWhere('reference', 'like', '%'.$v.'%')))
            ->where(function ($q) use ($start, $end) {
                foreach (['received_at','requested_deadline','submitted_at','completed_at','delivered_at'] as $field) {
                    $q->orWhereBetween($field, [$start, $end]);
                }
                $q->orWhere(fn($q) => $q->where('planned_start','<=',$end)->where('internal_eta','>=',$start));
                $q->orWhereHas('updates', fn($q) => $q->whereBetween('created_at', [$start, $end]));
                CreativeReportingPeriod::includeHistory($q,$start,$end);
            });
        $items = $query->with(['assignee','requester','reviewer','attachments','firstSubmission','updates'=>fn($q)=>$q->where('created_at','<=',$end)])->get();
        $inside = fn($date) => $date && $date->betweenIncluded($start, $end);
        $submitted = $items->filter(fn($i) => $inside($i->first_submitted_at));
        $verified = $items->filter(fn($i) => $inside($i->completed_at));
        $onTime = $submitted->filter(fn($i) => $i->firstSubmission?->metadata['requested_deadline'] ?? $i->requested_deadline);
        $onTimeCount = $onTime->filter(function($i) {
            $deadline = $i->firstSubmission?->metadata['requested_deadline'] ?? $i->requested_deadline;
            return $i->first_submitted_at->lte(Carbon::parse($deadline));
        })->count();
        $buckets = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $bucketEnd = match($period) {'yearly' => $cursor->copy()->endOfMonth(), 'quarterly' => $cursor->copy()->endOfMonth(), 'monthly' => $cursor->copy()->endOfWeek(), default => $cursor->copy()->endOfDay()};
            if($bucketEnd->gt($end)) $bucketEnd = $end->copy();
            $buckets[] = ['label'=>$cursor->format(in_array($period,['quarterly','yearly'])?'M':'d M'), 'submitted'=>$submitted->filter(fn($i)=>$i->first_submitted_at->betweenIncluded($cursor,$bucketEnd))->count(), 'verified'=>$verified->filter(fn($i)=>$i->completed_at->betweenIncluded($cursor,$bucketEnd))->count()];
            $cursor = $bucketEnd->copy()->addSecond();
        }
        $gallery = $items->filter(fn($i)=>$i->attachments->contains(fn($a)=>$a->kind==='deliverable'))->sortByDesc('submitted_at')->take($period==='yearly'?12:6)->values()->map(function($i) use ($images) {
            $file=$i->attachments->first(fn($a)=>$a->kind==='deliverable' && in_array($a->mime,['image/jpeg','image/png','image/webp','video/mp4','video/webm']));
            return ['id'=>$i->id,'title'=>$i->title,'brand'=>$i->client?:'Internal','creative'=>$i->assignee?->name?:'Unassigned','stage'=>CreativeProjection::stageLabel($i),'preview'=>$file?route('portal.creative.attachment',$file):null,'mime'=>$file?->mime,'image'=>$images&&$file?$this->thumbnail($file):null];
        })->all();
        $events = $items->flatMap(function($i) use($inside) {
            $result=[];
            foreach(['received_at'=>'Brief Received','submitted_at'=>'Work Submitted','completed_at'=>'Verified','requested_deadline'=>'Deadline'] as $field=>$label) if($inside($i->$field)) $result[]=['date'=>$i->$field->toIso8601String(),'label'=>$label,'title'=>$i->title,'creative'=>$i->assignee?->name?:'Unassigned'];
            return $result;
        })->sortBy('date')->values();
        $actions = $items->filter(fn($i)=>!in_array($i->stage,['completed','archived']))->sortBy('requested_deadline')->map(fn($i)=>['id'=>$i->id,'title'=>$i->title,'action'=>$i->blocker?:($i->stage==='in_review'?'Feedback Needed':'Continue '.config('creative.stages.'.$i->stage)),'owner'=>$i->blocker_owner?:match($i->stage){'in_review'=>$i->reviewer?->name?:'Reviewer Needed','delivered'=>'Creative HOD: Record Client Approval','brief_received','backlog'=>'Creative HOD: Planning','needs_info'=>$i->requester?->name?:'Requester',default=>$i->assignee?->name?:'Assignment Needed'},'deadline'=>$i->requested_deadline?->format('d M Y')?:'Not Recorded'])->values();
        $weekly = \App\Models\WeeklyConsolidatedItem::whereIn('department', User::departmentAliases('creatives'))->where('workflow_state','legacy')->whereBetween('week_start',[$start->toDateString(),$end->toDateString()])
            ->when($filters['assignee']??null,fn($q,$id)=>$q->where(fn($q)=>$q->where('lead_staff_id',$id)->orWhereJsonContains('supporting_staff_ids',(int)$id)))
            ->when($filters['q']??null,fn($q,$term)=>$q->where(fn($q)=>$q->where('campaign_name','like','%'.$term.'%')->orWhere('client_name','like','%'.$term.'%')->orWhere('deliverables','like','%'.$term.'%')))->get();
        $waiting = $items->filter(fn($i)=>$i->stage==='in_review');
        return ['period'=>$period,'from'=>$start->toDateString(),'to'=>$end->toDateString(),'label'=>match($period){'yearly'=>$start->format('Y'),'quarterly'=>'Q'.$start->quarter.' '.$start->year,'monthly'=>$start->format('F Y'),default=>$start->format('d M').' – '.$end->format('d M Y')},
            'weekly_snapshot'=>['entries'=>$weekly->count(),'reported_statuses'=>$weekly->countBy('status')->all()],'total'=>$items->count(),'received'=>$items->filter(fn($i)=>$inside($i->received_at))->count(),'submitted'=>$submitted->count(),'verified'=>$verified->count(),'on_time'=>$onTime->count()?round(100*$onTimeCount/$onTime->count()):null,
            'waiting'=>$waiting->count(),'review_wait_hours'=>$waiting->sum(fn($i)=>$i->submitted_at?max(0, $i->submitted_at->diffInHours(min(now(),$end),false)):0),
            'stages'=>$items->countBy('stage')->all(),'buckets'=>$buckets,'gallery'=>$gallery,'gallery_total'=>$items->filter(fn($i)=>$i->attachments->contains(fn($a)=>$a->kind==='deliverable'))->count(),
            'timeline'=>$events->take(24)->all(),'timeline_total'=>$events->count(),'actions'=>$actions->take(12)->all(),'actions_total'=>$actions->count(),
            'team'=>$items->groupBy(fn($i)=>$i->assignee?->name?:'Unassigned')->map(fn($group,$name)=>['name'=>$name,'work'=>$group->count(),'verified'=>$group->filter(fn($i)=>$inside($i->completed_at))->count()])->values()->all(),
            'kpis'=>app(CreativeKpiSnapshot::class)->forPeriod($start,$end),
            'note'=>'Delivery counts are activity measures, not staff scores. Stage and review queues show the current state of this period’s work. Historical totals use recorded milestone dates. Undated history is not assigned an invented date.'];
    }

    private function thumbnail($file): ?string
    {
        if(!str_starts_with($file->mime,'image/') || !Storage::disk('local')->exists($file->path)) return null;
        $path=Storage::disk('local')->path($file->path);
        $size=@getimagesize($path);
        if(!$size || $size[0]*$size[1]>24000000) return null;
        $image=match($file->mime){'image/jpeg'=>@imagecreatefromjpeg($path),'image/png'=>@imagecreatefrompng($path),'image/webp'=>@imagecreatefromwebp($path),default=>false};
        if(!$image) return null;
        $ratio=min(1,1000/max($size[0],$size[1]));$thumb=imagecreatetruecolor(max(1,(int)($size[0]*$ratio)),max(1,(int)($size[1]*$ratio)));
        imagefill($thumb,0,0,imagecolorallocate($thumb,255,255,255));imagecopyresampled($thumb,$image,0,0,0,0,imagesx($thumb),imagesy($thumb),$size[0],$size[1]);
        ob_start();imagejpeg($thumb,null,80);$bytes=ob_get_clean();imagedestroy($image);imagedestroy($thumb);
        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }
}
