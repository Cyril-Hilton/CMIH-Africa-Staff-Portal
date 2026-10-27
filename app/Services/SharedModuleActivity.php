<?php

namespace App\Services;

use App\Models\{PosmLedger, Campaign, AssetLog, PettyCashClaim, SupplierInvoice, PerformanceRegister};
use Illuminate\Support\Facades\Schema;

/** Public allow-list. Never return raw operational models or private narrative/amounts. */
class SharedModuleActivity
{
    public function build(string $department, string $from, string $to): array
    {
        $sources = match ($department) {
            'brands_marketing'=>Schema::hasTable('posm_ledgers') ? ['posm'=>[PosmLedger::query(),'POSM Movements']] : [],
            'operations_projects'=>['campaign'=>[Campaign::query()->with('projectLead'),'Projects & Activations'],'assets'=>[AssetLog::query()->whereNotNull('campaign_id')->with('asset'),'Asset Movements']],
            'finance'=>['claims'=>[PettyCashClaim::query(),'Claim Processing'],'invoices'=>[SupplierInvoice::query(),'Invoice Processing']],
            default=>[],
        };
        if (in_array($department,['brands_marketing','operations_projects','finance'],true)) {
            $sources['pending']=[PerformanceRegister::where('department',$department)->whereNull('approved_at'),'Reports Awaiting Review'];
        }
        $searchKey='activity_q_'.$department;
        $search=request()->validate([$searchKey=>'nullable|string|max:150'])[$searchKey]??null;
        $result=[];
        foreach ($sources as $type=>[$query,$label]) {
            if ($type==='campaign') {
                // Undated old projects are not silently attributed to a selected week.
                $query->whereDate('start_date','<=',$to)->where(fn($q)=>$q->whereDate('end_date','>=',$from)->orWhereNull('end_date'));
            } elseif ($type==='posm') {
                $query->where(fn($q)=>$q->whereBetween('occurred_on',[$from,$to])->orWhere(fn($q)=>$q->whereNull('occurred_on')->whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59'])));
            } elseif ($type==='pending') {
                $query->whereDate('period_start','<=',$to)->whereDate('period_end','>=',$from);
            } else {
                $query->whereBetween('created_at',[$from.' 00:00:00',$to.' 23:59:59']);
            }
            if ($search) {
                $query->where(function($q) use($type,$search) {
                    if($type==='posm')$q->where('item_name','like','%'.$search.'%')->orWhere('client_brand','like','%'.$search.'%');
                    elseif($type==='campaign')$q->where('name','like','%'.$search.'%')->orWhere('client_name','like','%'.$search.'%');
                    elseif($type==='assets')$q->whereHas('asset',fn($a)=>$a->where('name','like','%'.$search.'%'));
                    elseif($type==='pending')$q->where('kind','like','%'.$search.'%')->orWhere('id','like','%'.$search.'%');
                    else $q->where('status','like','%'.$search.'%')->orWhere('id','like','%'.$search.'%');
                });
            }
            $rows=$query->orderByDesc('id')->paginate(10,['*'],'source_'.$department.'_'.$type.'_page')->withQueryString()->fragment('module-source-activity');
            $rows->through(fn($record)=>$this->project($type,$record));
            $result[$type]=['label'=>$label,'rows'=>$rows];
        }
        return $result;
    }

    private function project(string $type, $record): array
    {
        $base=['reference'=>strtoupper($type).'-'.$record->id,'date'=>($type==='posm'?$record->occurred_on??$record->created_at:$record->created_at)?->format('d M Y')];
        return $base + match($type) {
            'posm'=>['title'=>$record->item_name,'context'=>$record->client_brand,'status'=>'Recorded movement','detail'=>'In: '.$record->quantity_in.' · Out: '.$record->quantity_out.' · Location: '.($record->location?:'Not recorded')],
            'campaign'=>['title'=>$record->name,'context'=>$record->client_name,'status'=>$record->status_update?:'Not recorded','detail'=>'Lead: '.($record->projectLead?->name??'Unassigned').' · '.$record->start_date?->format('d M Y').'–'.($record->end_date?->format('d M Y')??'Ongoing')],
            'pending'=>['title'=>'Department report recorded','context'=>ucwords(str_replace('_',' ',$record->kind)),'status'=>'Awaiting Independent Review','detail'=>'Source details stay with the department until reviewed.'],
            'assets'=>['title'=>$record->asset?->name??'Asset movement','context'=>null,'status'=>ucwords($record->action),'detail'=>'Operational movement recorded; private notes remain in the module.'],
            // Public processing references only: no claimant, supplier, amount, receipt or justification.
            default=>['title'=>$type==='claims'?'Expense claim processing':'Supplier invoice processing','context'=>null,'status'=>$record->status,'detail'=>'Financial details remain restricted to authorised reviewers.'],
        };
    }
}
