<?php

namespace App\Services;

use App\Models\{Asset, PerformanceRegister, PosmLedger, User, WarehouseAssetSuggestion};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseAssetIntake
{
    public function canReview(User $user): bool
    {
        return $user->isActive() && !$user->isServiceAccount()
            && ($user->isOperationsDepartmentLead() || $user->isActingForOperationsDepartmentLead() || $user->isCvoOrSuperAdmin());
    }

    public function submitStaffEntry(User $actor,array $input):WarehouseAssetSuggestion
    {
        abort_unless($actor->isActive()&&!$actor->isServiceAccount()&&User::normalizeDepartmentKey($actor->department)==='operations_projects',403);
        $input=collect($input)->except(['image','assigned_to'])->all();
        $reported=$input+['basis'=>'Operations staff inventory entry','quantity'=>$input['warehouse_quantity'],'reported_location'=>$input['warehouse_location']??null,'asset_code'=>$input['asset_tag']??null,'details'=>$input['description']??null,'reported_date'=>today()->toDateString()];
        $key=hash('sha256',json_encode(['staff_entry',$actor->id,collect($reported)->except('image_path')->all()],JSON_THROW_ON_ERROR));
        $row=WarehouseAssetSuggestion::firstOrCreate(['source_key'=>$key],['source_type'=>'staff_entry','source_id'=>$actor->id,'source_reference'=>'Operations inventory submission','submitted_by'=>$actor->id,'reported'=>$reported]);
        if($row->wasRecentlyCreated){
            PerformanceWorkflow::record('warehouse_asset_suggestion',$row->id,$actor->id,'submitted',[],['status'=>'pending'],'Operations inventory entry awaiting HOD review.');
            foreach(User::internalStaff()->where('status','active')->get()->filter(fn($u)=>$this->canReview($u)) as $reviewer)PerformanceWorkflow::notify($reviewer->id,'warehouse-intake:'.$row->id,'Warehouse inventory entry awaiting review',$actor->name.' submitted '.$input['name'].'. Review before adding it to inventory.',route('portal.assets.warehouse.intake'));
        }
        return $row;
    }

    public function fromOperations(PerformanceRegister $record): int
    {
        if (User::normalizeDepartmentKey($record->department) !== 'operations_projects' || !$record->approved_at) {
            return 0;
        }
        $data = $record->data ?? [];
        $activationId = $record->kind === 'activation' ? $record->id : (int) ($data['activation_id'] ?? 0);
        if ($activationId && $activationId !== $record->id && !OperationsReporting::activations()->whereKey($activationId)->exists()) {
            $activationId = 0;
        }
        $reported = [
            'activity' => $record->title, 'project' => $record->project, 'client' => $record->client,
            'brand' => $this->plain($data['brand'] ?? ''), 'reported_location' => $this->plain($data['location'] ?? ''),
            'quantity' => $data['quantity'] ?? null, 'returned_quantity' => $data['returned_quantity'] ?? null,
            'asset_code' => $this->plain($data['asset_code'] ?? ''), 'vendor' => $this->plain($data['vendor'] ?? $data['vendors'] ?? ''),
            'details' => $this->plain($data['description'] ?? $data['remarks'] ?? ''), 'reported_status' => $record->status,
            'reported_date' => $data['deployed_on'] ?? $data['starts_on'] ?? (!empty($data['import_undated']) ? null : $record->period_start?->toDateString()),
        ];
        $suggestions = match ($record->kind) {
            'posm_deployment' => [array_replace($reported, ['name' => $this->plain($data['asset_name'] ?? ''), 'type' => $this->plain($data['category'] ?? '') ?: 'POSM', 'basis' => 'Reported POSM deployment'])],
            'fabrication' => [array_replace($reported, ['name' => $this->plain($record->title), 'type' => 'Fabrication', 'basis' => 'Fabrication work plan'])],
            'activation', 'project', 'weekly_update' => $this->assetNames($data['planned_assets'] ?? $data['asset_requirements'] ?? '')
                ->map(fn ($name) => array_replace($reported, ['name' => $name, 'type' => 'Equipment', 'quantity' => null, 'basis' => 'Planned asset in Operations report']))->all(),
            default => [],
        };

        return $this->queue('operations_register', $record->id, $record->reference, $record->submitted_by, $activationId ?: null,
            $record->status === 'cancelled' ? [] : $suggestions);
    }

    public function fromPosmLedger(PosmLedger $record): int
    {
        if ($record->asset_id || !filled($record->item_name)) {
            return 0;
        }
        return $this->queue('posm_ledger', $record->id, 'POSM #'.$record->id, $record->created_by, null, [[
            'name' => $record->item_name, 'type' => $record->item_type ?: 'POSM',
            'brand' => $record->client_brand, 'reported_location' => $record->location,
            'basis' => 'Recorded POSM movement', 'quantity' => $record->quantity_out, 'returned_quantity' => $record->quantity_in,
            'reported_date' => $record->occurred_on?->toDateString(), 'details' => $record->notes,
        ]]);
    }

    private function queue(string $sourceType, int $id, ?string $reference, ?int $submittedBy, ?int $activationId, array $candidates): int
    {
        return DB::transaction(function () use ($sourceType, $id, $reference, $submittedBy, $activationId, $candidates) {
            $keys = [];
            $created = 0;
            foreach ($candidates as $reported) {
                $key = hash('sha256', json_encode([$sourceType, $id, $activationId, array_diff_key($reported, ['reported_status' => true])], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $keys[] = $key;
                $suggestion = WarehouseAssetSuggestion::firstOrCreate(['source_key' => $key], [
                    'source_type' => $sourceType, 'source_id' => $id, 'source_reference' => $reference,
                    'activation_id' => $activationId, 'submitted_by' => $submittedBy, 'reported' => $reported,
                ]);
                if ($suggestion->status === 'superseded') {
                    $suggestion->update(['status' => 'pending', 'revision' => $suggestion->revision + 1]);
                }
                $created += (int) $suggestion->wasRecentlyCreated;
            }
            WarehouseAssetSuggestion::where('source_type', $sourceType)->where('source_id', $id)
                ->where('status', 'pending')->whereNotIn('source_key', $keys)->update([
                    'status' => 'superseded', 'revision' => DB::raw('revision + 1'),
                ]);
            return $created;
        });
    }

    public function review(WarehouseAssetSuggestion $suggestion, User $actor, array $input): void
    {
        abort_unless($this->canReview($actor), 403);
        DB::transaction(function () use ($suggestion, $actor, $input) {
            $row = WarehouseAssetSuggestion::lockForUpdate()->findOrFail($suggestion->id);
            abort_if($row->status !== 'pending' || (int) $row->revision !== (int) $input['revision'], 409, 'This suggestion changed. Open the current version.');
            $action = $input['decision'];
            $review = collect($input)->only(['name', 'brand', 'category', 'type', 'asset_tag', 'warehouse_location', 'condition', 'warehouse_quantity', 'asset_id', 'notes'])->all();
            $meta = ['review' => $review, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'revision' => $row->revision + 1];
            if ($action === 'save') {
                $row->update($meta);
                return;
            }
            if ($action === 'dismiss') {
                $row->update($meta + ['status' => 'dismissed']);
                return;
            }
            $asset = $action === 'link'
                ? Asset::where('is_warehouse_tracked', true)->lockForUpdate()->findOrFail($input['asset_id'])
                : $this->createAsset($row, $actor, $review);
            $row->update($meta + ['asset_id' => $asset->id, 'status' => 'approved']);
            if($row->submitted_by)PerformanceWorkflow::notify($row->submitted_by,'warehouse-intake-reviewed:'.$row->id.':'.($row->revision),'Warehouse inventory entry approved',$asset->name.' is now linked to the warehouse inventory.',route('portal.assets.warehouse.index'));
            PerformanceWorkflow::record('warehouse_asset_suggestion', $row->id, $actor->id, $action,
                ['status' => 'pending'], ['status' => 'approved', 'asset_id' => $asset->id], $review['notes'] ?? 'Approved warehouse source record.');
        });
    }

    private function createAsset(WarehouseAssetSuggestion $row, User $actor, array $review): Asset
    {
        $tag = $review['asset_tag'] ?? null;
        $matches = Asset::where('is_warehouse_tracked', true)->where(function ($query) use ($review, $tag) {
            $query->where(fn ($name) => $name->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($review['name']))])
                ->whereRaw("LOWER(TRIM(COALESCE(brand, ''))) = ?", [mb_strtolower(trim($review['brand'] ?? ''))]));
            if (filled($tag)) {
                $query->orWhereRaw('LOWER(TRIM(asset_tag)) = ?', [mb_strtolower(trim($tag))]);
            }
        })->exists();
        if ($matches) {
            throw ValidationException::withMessages(['name' => 'An inventory record already matches this name and brand or asset tag. Link it to the existing asset.']);
        }
        return Asset::create([
            'name' => $review['name'], 'brand' => $review['brand'] ?? null, 'type' => $review['type'],
            'category' => $review['category'] ?? null, 'asset_tag' => $tag,
            'condition' => $review['condition'], 'warehouse_quantity' => $review['warehouse_quantity'],
            'warehouse_location' => $review['warehouse_location'], 'status' => $review['warehouse_quantity'] > 0 ? 'available' : 'out_of_stock',
            'description' => Str::limit($row->reported['details'] ?? '', 2000, ''),
            'warehouse_notes' => $review['notes'] ?? null, 'image_path'=>$row->reported['image_path']??null,'serial_number'=>$row->reported['serial_number']??null, 'is_warehouse_tracked' => true, 'added_by' => $row->submitted_by??$actor->id,
        ]);
    }

    private function assetNames(?string $text)
    {
        return collect(preg_split('/[\r\n;]+/', $this->plain($text)))->map(fn ($name) => trim($name))
            ->filter()->unique(fn ($name) => mb_strtolower($name))->take(50);
    }

    private function plain(?string $value): string
    {
        return trim(html_entity_decode(strip_tags(str_ireplace(['</p>', '</li>', '<br>', '<br/>', '<br />'], "\n", $value ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
}
