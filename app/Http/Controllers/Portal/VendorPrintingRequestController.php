<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\VendorPrintingRequest;
use App\Services\PerformanceWorkflow;
use App\Services\VendorPrintingPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VendorPrintingRequestController extends Controller
{
    private function authorizeAccess(Request $r, ?VendorPrintingRequest $record = null): void
    {
        abort_unless($r->user()->canAccessOperationsPrintingRequests(), 403);
        if($record)abort_unless($r->user()->canManageOperationsPrintingRequests() || (int)$record->created_by===(int)$r->user()->id,403);
    }

    public function index(Request $r)
    {
        $this->authorizeAccess($r);
        $r->validate(['q'=>'nullable|string|max:100','state'=>'nullable|in:draft,pending,approved,needs_changes,lead_created,legacy','sort'=>'nullable|in:newest,oldest,reference']);
        $base=VendorPrintingRequest::query()->when(!$r->user()->canManageOperationsPrintingRequests(),fn($q)=>$q->where('created_by',$r->user()->id));
        $counts=(clone $base)->selectRaw('review_status,count(*) as total')->groupBy('review_status')->pluck('total','review_status');
        $records=$base->with(['creator','reviewer'])->when($r->filled('q'),fn($q)=>$q->where(fn($q)=>$q->where('reference','like','%'.$r->q.'%')->orWhere('information','like','%'.$r->q.'%')))->when($r->filled('state'),fn($q)=>$q->where('review_status',$r->state))->orderBy($r->input('sort')==='reference'?'reference':'created_at',$r->input('sort')==='oldest'||$r->input('sort')==='reference'?'asc':'desc')->orderByDesc('id')->paginate(12)->withQueryString();

        return view('portal.printing-requests.index', compact('records','counts'));
    }

    public function create(Request $r)
    {
        $this->authorizeAccess($r);
        $record = new VendorPrintingRequest(['information' => ['request_date' => now()->toDateString(), 'requested_by' => $r->user()->name, 'urgency' => 'Normal'], 'items' => [['quantity' => 1, 'artwork_status' => 'Pending']], 'vendor' => ['approval_status' => 'Pending', 'vendor_confirmation' => 'Pending'], 'checklist' => []]);

        return view('portal.printing-requests.form', compact('record'));
    }

    public function edit(Request $r, VendorPrintingRequest $printingRequest)
    {
        $this->authorizeAccess($r,$printingRequest);

        return view('portal.printing-requests.form', ['record' => $printingRequest]);
    }

    private function validated(Request $r): array
    {
        if(!$r->user()->canManageOperationsPrintingRequests()) {
            $vendor=(array)$r->input('vendor',[]);$vendor['approval_status']='Pending';$vendor['approved_by']=null;
            $information=(array)$r->input('information',[]);$information['requested_by']=$r->user()->name;
            $r->merge(['vendor'=>$vendor,'information'=>$information]);
        }
        $r->validate(['intent'=>'nullable|in:save,download,draft,submit']);
        $rules = ['information'  => 'required|array', 'vendor' => 'required|array', 'items' => 'required|array|min:1|max:30', 'checklist' => 'nullable|array'];
        foreach (config('printing_requests.information') as $key => [$label,$type]) {
            $rules['information.'.$key] = $type === 'date' ? 'required|date' : ($type === 'select' ? ['required', Rule::in(config('printing_requests.options.'.$key))] : 'required|string|max:250');
        }
        $rules['information.required_date'] = 'required|date|after_or_equal:information.request_date';
        foreach (config('printing_requests.vendor') as $key => [$label,$type]) {
            $rules['vendor.'.$key] = match ($type) {
                'yesno' => ['required', Rule::in(['Yes', 'No'])],'select' => ['required', Rule::in(config('printing_requests.options.'.$key))],'number' => 'nullable|numeric|min:0|max:999999999999','date' => 'nullable|date|after_or_equal:information.request_date',default => 'nullable|string|max:'.($type === 'textarea' ? 1500 : 250)
            };
        }
        $rules['vendor.approved_by'] = 'required_if:vendor.approval_status,Approved|nullable|string|max:250';
        foreach (config('printing_requests.item_fields') as $key => $label) {
            $rules['items.*.'.$key] = match ($key) {
                'quantity' => 'required|integer|min:1|max:1000000','description','dimension' => 'required|string|max:300','remarks' => 'nullable|string|max:300',default => ['required', Rule::in(config('printing_requests.options.'.$key))]
            };
        }
        foreach (config('printing_requests.checklist') as $key => $label) {
            $rules['checklist.'.$key] = 'nullable|boolean';
        }
        $v = $r->validate($rules);
        foreach ($v['items'] as $index => $item) {
            if (in_array('Other', $item, true) && empty(trim($item['remarks'] ?? ''))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items.'.$index.'.remarks' => 'Explain the Other specification in this item’s remarks.']);
            }
        }
        $v['information'] = array_intersect_key($v['information'], config('printing_requests.information'));
        $v['vendor'] = array_intersect_key($v['vendor'], config('printing_requests.vendor'));
        $v['items'] = array_values(array_map(fn ($item) => array_intersect_key($item, config('printing_requests.item_fields')), $v['items']));
        $v['checklist'] = collect(config('printing_requests.checklist'))->mapWithKeys(fn ($label, $key) => [$key => $r->boolean('checklist.'.$key)])->all();

        return $v;
    }

    public function store(Request $r)
    {
        $this->authorizeAccess($r);
        $v = $this->validated($r);
        $record = DB::transaction(function () use ($r, $v) {
            $item = VendorPrintingRequest::create($v + ['created_by' => $r->user()->id, 'updated_by' => $r->user()->id,'review_status'=>$r->user()->canManageOperationsPrintingRequests()?'lead_created':($r->input('intent')==='draft'?'draft':'pending')]);
            $item->update(['reference' => 'PR-'.now()->format('Ym').'-'.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT)]);
            PerformanceWorkflow::record('printing_request', $item->id, $r->user()->id, 'create', [], $item->getAttributes(), 'Vendor print specification recorded');

            return $item;
        });

        $this->notifyReview($record);
        return redirect()->route('portal.printing-requests.edit', $record)->with('status', $record->downloadable()?'Printing request saved. Preview or download your PDF.':($record->review_status==='draft'?'Draft saved. Submit it when ready for HOD review.':'Request submitted for HOD review. PDF download unlocks after approval.'))->with('download_printing_request', $record->downloadable() && $r->input('intent') === 'download');
    }

    public function update(Request $r, VendorPrintingRequest $printingRequest)
    {
        $this->authorizeAccess($r,$printingRequest);
        $r->validate(['revision' => 'required|integer']);
        $v = $this->validated($r);
        DB::transaction(function () use ($r, $v, $printingRequest) {
            $item = VendorPrintingRequest::whereKey($printingRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($item->revision !== (int) $r->revision, 409, 'This request has changed. Reload before saving.');
            $before = $item->getAttributes();
            $item->fill($v);
            $item->updated_by = $r->user()->id;
            $item->revision++;
            $item->review_status=$r->user()->canManageOperationsPrintingRequests() && (int)$item->created_by===(int)$r->user()->id?'lead_created':($r->input('intent')==='draft'?'draft':'pending');
            $item->reviewed_by=null;$item->reviewed_at=null;$item->reviewed_revision=null;$item->review_note=null;
            if($item->review_status!=='lead_created'){$vendor=$item->vendor;$vendor['approval_status']='Pending';$vendor['approved_by']=null;$item->vendor=$vendor;}
            $item->save();
            PerformanceWorkflow::record('printing_request', $item->id, $r->user()->id, 'update', $before, $item->getAttributes(), 'Printing specification revised');
        });

        $printingRequest->refresh();$this->notifyReview($printingRequest);
        return back()->with('status', $printingRequest->downloadable()?'Printing request updated. The next download uses this revision.':'Request saved. This revision needs HOD approval before PDF download.')->with('download_printing_request', $printingRequest->downloadable() && $r->input('intent') === 'download');
    }

    private function notifyReview(VendorPrintingRequest $record): void
    {
        if($record->review_status!=='pending')return;
        $ids=\App\Models\User::internalStaff()->where('status','active')->get()->filter(fn($u)=>$u->canManageOperationsPrintingRequests() && (int)$u->id!==(int)$record->created_by)->pluck('id')->all();
        \App\Services\NotificationService::sendToMany($ids,'Printing Request Awaiting Review',"{$record->reference} · Revision {$record->revision} needs Operations HOD review.",route('portal.printing-requests.edit',$record).'#printing-review');
    }

    public function review(Request $r, VendorPrintingRequest $printingRequest)
    {
        $this->authorizeAccess($r,$printingRequest);
        abort_unless($r->user()->canManageOperationsPrintingRequests(),403);
        abort_if((int)$printingRequest->created_by===(int)$r->user()->id,403,'Your own leadership request does not need a separate self-approval.');
        $data=$r->validate(['revision'=>'required|integer','decision'=>'required|in:approve,return','review_note'=>'required|string|min:5|max:2000']);
        DB::transaction(function()use($r,$data,$printingRequest){
            $item=VendorPrintingRequest::whereKey($printingRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($item->revision!==(int)$data['revision'],409,'The request changed. Reload and review the latest revision.');
            abort_unless($item->review_status==='pending',422,'Only a submitted request can be reviewed.');
            $before=$item->getAttributes();$item->review_status=$data['decision']==='approve'?'approved':'needs_changes';
            $item->reviewed_by=$r->user()->id;$item->reviewed_at=now();$item->reviewed_revision=$item->revision;$item->review_note=$data['review_note'];
            $vendor=$item->vendor;$vendor['approval_status']=$data['decision']==='approve'?'Approved':'Revision Required';$vendor['approved_by']=$data['decision']==='approve'?$r->user()->name:null;$item->vendor=$vendor;$item->save();
            PerformanceWorkflow::record('printing_request',$item->id,$r->user()->id,$data['decision']==='approve'?'approve':'return',$before,$item->getAttributes(),$data['review_note']);
            \App\Services\NotificationService::send($item->created_by,$data['decision']==='approve'?'Printing Request Approved':'Printing Request Needs Corrections',"{$item->reference}: ".$data['review_note'],route('portal.printing-requests.edit',$item));
        });
        return back()->with('status',$data['decision']==='approve'?'Request approved. The staff member can download this revision.':'Request returned for corrections. Download remains locked.');
    }

    public function preview(Request $r, VendorPrintingRequest $printingRequest, VendorPrintingPdf $pdf)
    {
        $this->authorizeAccess($r,$printingRequest);
        abort_unless($printingRequest->downloadable() || $r->user()->canManageOperationsPrintingRequests(),403,'This revision awaits HOD approval.');

        return response($pdf->html($printingRequest))->header('Cache-Control', 'private, no-store')->header('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'self'");
    }

    public function download(Request $r, VendorPrintingRequest $printingRequest, VendorPrintingPdf $pdf)
    {
        $this->authorizeAccess($r,$printingRequest);
        abort_unless($printingRequest->downloadable(),403,'This revision must be approved by the HOD before download.');
        $bytes = $pdf->render($printingRequest);
        PerformanceWorkflow::record('printing_request', $printingRequest->id, $r->user()->id, 'download', [], ['reference' => $printingRequest->reference, 'revision' => $printingRequest->revision], 'Vendor printing PDF downloaded');

        return response($bytes)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="'.$printingRequest->reference.'.pdf"')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
