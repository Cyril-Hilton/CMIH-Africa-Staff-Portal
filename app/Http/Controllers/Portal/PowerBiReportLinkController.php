<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;
use App\Models\PowerBiReportLink;
use App\Services\PerformanceWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PowerBiReportLinkController extends Controller
{
    private function access(Request $request): void { abort_unless(PowerBiReportLink::staffCanAccess($request->user()), 403); }
    public function index(Request $request) {
        $this->access($request);
        $links = PowerBiReportLink::with('creator')->where(function ($q) use ($request) {
            $q->where('active', true);
            if ($request->user()->isCvoOrSuperAdmin() || $request->user()->hasRole(['admin', 'super_admin'])) $q->orWhere('active', false);
            else $q->orWhere('created_by', $request->user()->id);
        })->orderByDesc('active')->latest('id')->paginate(12);
        return view('portal.power-bi.index', compact('links'));
    }
    private function validated(Request $request): array {
        $data = $request->validate(['title'=>'required|string|max:150', 'url'=>'required|string|max:2000']);
        $data['url'] = trim($data['url']);
        if (! PowerBiReportLink::validUrl($data['url'])) throw ValidationException::withMessages(['url'=>'Paste a restricted https://app.powerbi.com report link. Public “Publish to web” links are not supported.']);
        $data['url_hash'] = hash('sha256', $data['url']);
        return $data;
    }
    public function store(Request $request) {
        $this->access($request); $data = $this->validated($request);
        return DB::transaction(function () use ($request, $data) {
            if (PowerBiReportLink::where('url_hash', $data['url_hash'])->exists()) throw ValidationException::withMessages(['url'=>'This report link is already listed. Check the shared list or your archived links.']);
            $link = PowerBiReportLink::create($data + ['created_by'=>$request->user()->id, 'updated_by'=>$request->user()->id]);
            PerformanceWorkflow::record('power_bi_link', $link->id, $request->user()->id, 'create', [], $link->getAttributes(), 'Staff shared a report link');
            return redirect()->route('portal.power-bi.index')->with('status', 'Power BI link added.');
        });
    }
    public function update(Request $request, PowerBiReportLink $link) {
        $this->access($request); abort_unless($link->manageableBy($request->user()), 403);
        $data = $this->validated($request); $v = $request->validate(['revision'=>'required|integer|min:1','active'=>'required|boolean']);
        return DB::transaction(function () use ($request, $link, $data, $v) {
            $locked = PowerBiReportLink::whereKey($link->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->revision !== (int) $v['revision'], 409, 'This link changed. Refresh before saving.');
            if (PowerBiReportLink::where('url_hash',$data['url_hash'])->whereKeyNot($link->id)->exists()) throw ValidationException::withMessages(['url'=>'This report link is already listed.']);
            $before = $locked->getAttributes();
            $locked->update($data + ['active'=>$v['active'], 'revision'=>$locked->revision + 1, 'updated_by'=>$request->user()->id]);
            PerformanceWorkflow::record('power_bi_link', $locked->id, $request->user()->id, 'update', $before, $locked->getAttributes(), 'Report link updated or archived');
            return redirect()->route('portal.power-bi.index')->with('status', 'Power BI link updated.');
        });
    }
    public function open(Request $request, PowerBiReportLink $link) {
        $this->access($request); abort_unless($link->active && PowerBiReportLink::validUrl($link->url),404);
        return redirect()->away($link->url)->withHeaders(['Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer']);
    }
}
