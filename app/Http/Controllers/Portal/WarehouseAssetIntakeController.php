<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\{Asset, WarehouseAssetSuggestion};
use App\Services\{OperationsReporting, WarehouseAssetIntake};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseAssetIntakeController extends Controller
{
    public function index(Request $request, WarehouseAssetIntake $intake)
    {
        $actor = $request->user();
        $canReview = $intake->canReview($actor);
        abort_unless($canReview || OperationsReporting::member($actor), 403);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'dismissed', 'superseded', 'all'])],
            'q' => 'nullable|string|max:120',
        ]);
        $status = $filters['status'] ?? 'pending';
        $query = WarehouseAssetSuggestion::with(['asset:id,name,asset_tag,warehouse_location', 'submitter:id,name', 'reviewer:id,name', 'activation:id,title'])
            ->when(!$canReview, fn ($visible) => $visible->where('submitted_by', $actor->id));
        $pending = (clone $query)->where('status', 'pending')->count();
        $suggestions = $query->when($status !== 'all', fn ($filtered) => $filtered->where('status', $status))
            ->when($filters['q'] ?? null, fn ($filtered, $term) => $filtered->where(fn ($fields) => $fields
                ->where('reported->name', 'like', '%'.$term.'%')->orWhere('source_reference', 'like', '%'.$term.'%')))
            ->orderByDesc('id')->paginate(10)->withQueryString();
        $assets = $canReview && $suggestions->contains('status', 'pending')
            ? Asset::where('is_warehouse_tracked', true)->orderBy('name')->get(['id','name','asset_tag','brand'])
            : collect();

        return response()->view('portal.warehouse-intake.index', compact('pending', 'suggestions', 'canReview', 'status', 'assets'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function review(Request $request, WarehouseAssetSuggestion $suggestion, WarehouseAssetIntake $intake)
    {
        abort_unless($intake->canReview($request->user()), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['save', 'approve', 'link', 'dismiss'])],
            'revision' => 'required|integer|min:1',
            'name' => 'required_if:decision,approve|nullable|string|max:255',
            'type' => 'required_if:decision,approve|nullable|string|max:80',
            'condition' => 'required_if:decision,approve|nullable|string|max:80',
            'warehouse_quantity' => 'required_if:decision,approve|nullable|integer|min:0|max:9999999',
            'warehouse_location' => 'required_if:decision,approve|nullable|string|max:255',
            'brand' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:100',
            'asset_tag' => 'nullable|string|max:100',
            'asset_id' => ['required_if:decision,link', 'nullable', 'integer', Rule::exists('assets', 'id')->where('is_warehouse_tracked', true)],
            'notes' => 'required_if:decision,dismiss|nullable|string|max:2000',
        ]);
        $intake->review($suggestion, $request->user(), $data);

        return back()->with('status', match ($data['decision']) {
            'approve' => 'Approved and added to warehouse inventory.',
            'link' => 'Approved and linked to the existing asset.',
            'dismiss' => 'Suggestion dismissed.',
            default => 'Corrections saved for review.',
        });
    }
}
