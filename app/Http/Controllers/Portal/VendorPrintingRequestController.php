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
    private function authorizeManager(Request $r): void
    {
        abort_unless($r->user()->canManageOperationsPrintingRequests(), 403);
    }

    public function index(Request $r)
    {
        $this->authorizeManager($r);
        $r->validate(['q' => 'nullable|string|max:100']);
        $records = VendorPrintingRequest::with('creator')->when($r->filled('q'), fn ($q) => $q->where('reference', 'like', '%'.$r->q.'%'))->latest()->paginate(12)->withQueryString();

        return view('portal.printing-requests.index', compact('records'));
    }

    public function create(Request $r)
    {
        $this->authorizeManager($r);
        $record = new VendorPrintingRequest(['information' => ['request_date' => now()->toDateString(), 'requested_by' => $r->user()->name, 'urgency' => 'Normal'], 'items' => [['quantity' => 1, 'artwork_status' => 'Pending']], 'vendor' => ['approval_status' => 'Pending', 'vendor_confirmation' => 'Pending'], 'checklist' => []]);

        return view('portal.printing-requests.form', compact('record'));
    }

    public function edit(Request $r, VendorPrintingRequest $printingRequest)
    {
        $this->authorizeManager($r);

        return view('portal.printing-requests.form', ['record' => $printingRequest]);
    }

    private function validated(Request $r): array
    {
        $rules = ['information' => 'required|array', 'vendor' => 'required|array', 'items' => 'required|array|min:1|max:30', 'checklist' => 'nullable|array'];
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
        $this->authorizeManager($r);
        $v = $this->validated($r);
        $record = DB::transaction(function () use ($r, $v) {
            $item = VendorPrintingRequest::create($v + ['created_by' => $r->user()->id, 'updated_by' => $r->user()->id]);
            $item->update(['reference' => 'PR-'.now()->format('Ym').'-'.str_pad((string) $item->id, 5, '0', STR_PAD_LEFT)]);
            PerformanceWorkflow::record('printing_request', $item->id, $r->user()->id, 'create', [], $item->getAttributes(), 'Vendor print specification recorded');

            return $item;
        });

        return redirect()->route('portal.printing-requests.edit', $record)->with('status', 'Printing request saved. Preview or download your PDF.')->with('download_printing_request', $r->input('intent') === 'download');
    }

    public function update(Request $r, VendorPrintingRequest $printingRequest)
    {
        $this->authorizeManager($r);
        $r->validate(['revision' => 'required|integer']);
        $v = $this->validated($r);
        DB::transaction(function () use ($r, $v, $printingRequest) {
            $item = VendorPrintingRequest::whereKey($printingRequest->id)->lockForUpdate()->firstOrFail();
            abort_if($item->revision !== (int) $r->revision, 409, 'This request has changed. Reload before saving.');
            $before = $item->getAttributes();
            $item->fill($v);
            $item->updated_by = $r->user()->id;
            $item->revision++;
            $item->save();
            PerformanceWorkflow::record('printing_request', $item->id, $r->user()->id, 'update', $before, $item->getAttributes(), 'Printing specification revised');
        });

        return back()->with('status', 'Printing request updated. The next download uses this revision.')->with('download_printing_request', $r->input('intent') === 'download');
    }

    public function preview(Request $r, VendorPrintingRequest $printingRequest, VendorPrintingPdf $pdf)
    {
        $this->authorizeManager($r);

        return response($pdf->html($printingRequest))->header('Cache-Control', 'private, no-store')->header('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'self'");
    }

    public function download(Request $r, VendorPrintingRequest $printingRequest, VendorPrintingPdf $pdf)
    {
        $this->authorizeManager($r);
        $bytes = $pdf->render($printingRequest);
        PerformanceWorkflow::record('printing_request', $printingRequest->id, $r->user()->id, 'download', [], ['reference' => $printingRequest->reference, 'revision' => $printingRequest->revision], 'Vendor printing PDF downloaded');

        return response($bytes)->header('Content-Type', 'application/pdf')->header('Content-Disposition', 'attachment; filename="'.$printingRequest->reference.'.pdf"')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
