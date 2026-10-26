<?php
namespace App\Services;
use App\Models\WeeklyConsolidatedItem;
use App\Models\User;
use DOMDocument;
class BrandsWeeklyPresentation
{
    public function build(string $from,string $to, array $filters=[]): \Illuminate\Pagination\LengthAwarePaginator
    {
        $v=\Illuminate\Support\Facades\Validator::make($filters,['q'=>'nullable|string|max:200','status'=>'nullable|in:Planned,In Progress,Done,Blocked,Deferred','sort'=>'nullable|in:workstream,deadline,newest'])->validate();
        $query=WeeklyConsolidatedItem::with(['leadStaff','creator'])->whereIn('department',User::departmentAliases('brands_marketing'))->where('workflow_state','legacy')->whereDate('week_start','<=',$to)->whereDate('week_end','>=',$from);
        if(!empty($v['q'])) $query->where(fn($q)=>$q->where('campaign_name','like','%'.$v['q'].'%')->orWhere('client_name','like','%'.$v['q'].'%')->orWhere('deliverables','like','%'.$v['q'].'%')->orWhereHas('leadStaff',fn($u)=>$u->where('name','like','%'.$v['q'].'%')));
        if(!empty($v['status']))$query->where('status',$v['status']);
        match($v['sort']??'workstream'){'deadline'=>$query->orderBy('week_end'),'newest'=>$query->orderByDesc('week_start'),default=>$query->orderBy('campaign_name')};
        $items=$query->orderBy('id')->paginate(8,['*'],'brands_page')->withQueryString()->appends(['weekly_department'=>'brands_marketing'])->fragment('staff-weekly-performance');
        $supportIds=$items->getCollection()->flatMap(fn($item)=>$item->supporting_staff_ids??[])->unique();
        $names=User::whereIn('id',$supportIds)->pluck('name','id');
        return $items->through(function($item)use($names){
            return ['id'=>$item->id,'reference'=>$item->brandsTaskId(),'workstream'=>$item->campaign_name?:'Uncategorised Workstream','task'=>$item->client_name?:'Untitled Activity','owner'=>$item->creator?->name??'Unavailable Owner','lead'=>$item->leadStaff?->name??'Unassigned','support'=>collect($item->supporting_staff_ids??[])->map(fn($id)=>['name'=>$names[$id]??'Unavailable Staff','role'=>$item->supporting_roles[$id]??null])->all(),'brief'=>$this->content($item->deliverables),'kpis'=>$this->content($item->target_breakdown),'start'=>$item->week_start?->format('d M Y'),'due'=>$item->week_end?->format('d M Y'),'status'=>$item->status?:'Planned'];
        });
    }

    /** Convert saved rich text into escaped text and structured table cells; no source HTML executes. */
    public function content(?string $html): array
    {
        if(!trim($html??'')) return ['text'=>'','tables'=>[],'html'=>''];
        $doc=new DOMDocument();
        $prior=libxml_use_internal_errors(true);
        try { $doc->loadHTML('<?xml encoding="utf-8" ?><html><body>'.$html.'</body></html>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING); }
        finally {libxml_clear_errors();libxml_use_internal_errors($prior);}
        foreach(['script','style','iframe','object','embed'] as $tag) foreach(iterator_to_array($doc->getElementsByTagName($tag)) as $node) $node->parentNode?->removeChild($node);
        $tables=[];
        foreach(iterator_to_array($doc->getElementsByTagName('table')) as $table){
            $rows=[];
            foreach($table->getElementsByTagName('tr') as $tr){$cells=[];foreach($tr->childNodes as $cell)if(in_array($cell->nodeName,['td','th'],true))$cells[]=trim(preg_replace('/\s+/u',' ',$cell->textContent));if($cells)$rows[]=$cells;}
            if($rows)$tables[]=$rows;
            $table->parentNode?->removeChild($table);
        }
        $body=$doc->getElementsByTagName('body')->item(0);
        $markup=$body?$doc->saveHTML($body):'';
        $text=html_entity_decode(strip_tags(preg_replace('/<\/?(?:p|div|li|h[1-6]|br|ul|ol)\b[^>]*>/i',"\n",$markup)),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $text=trim(preg_replace('/\n[\s\x{00a0}]*\n+/u',"\n\n",$text));
        return ['text'=>$text,'tables'=>$tables,'html'=>\App\Support\ReportRichText::html($html)];
    }
}
