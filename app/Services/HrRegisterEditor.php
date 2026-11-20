<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLog;
use App\Models\HrRegisterRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrRegisterEditor
{
    public function validate(string $kind, array $data, User $viewer, ?int $id = null): array
    {
        $service = app(HrLiveRegisters::class);
        $schema = $service->schema($kind);
        abort_unless(HrLiveRegisters::canEdit($viewer) && $schema, 403);
        $data = array_map(fn ($v) => is_string($v) && trim($v) === '' ? null : $v, $data);
        $rules = [];
        foreach ($schema['fields'] as $key => $f) {
            $required = in_array($key, match ($kind) {
                'staff' => ['name', 'email', 'department'],'asset' => ['asset_tag', 'name', 'category', 'quantity_procured', 'condition'],'staff_document' => ['staff_id', 'document_type', 'received_on'],'vehicle' => ['name', 'registration_number', 'state'],'vacancy' => ['role', 'department', 'opened_on', 'state'],'candidate' => ['name', 'stage'],'employee_case' => ['staff_id', 'case_type', 'opened_on', 'state', 'summary'],'welfare_case' => ['staff_id', 'case_type', 'opened_on', 'state'],'training' => ['title', 'date'],'appraisal_record' => ['staff_id', 'period_start', 'period_end', 'due_on'],'supply' => ['name', 'quantity', 'reorder_level'],'vendor' => ['name', 'service', 'state'],'purchase' => ['item', 'quantity', 'requested_on', 'state'],'communication' => ['title', 'document_type', 'date'],'driver' => ['name'],'probation' => ['staff_id', 'starts_on', 'confirmation_due'],'exit' => ['staff_id', 'exit_type', 'notice_on', 'last_day'],'facility' => ['name', 'category', 'quantity', 'condition'],'fuel' => ['date', 'vehicle_id', 'amount'],'policy' => ['name', 'version', 'state'],'holiday' => ['name', 'date'],'acknowledgement' => ['staff_id', 'policy_id', 'signed_on'],default => [array_key_first($schema['fields'])]
            });
            $r = [$required ? 'required' : 'nullable'];
            $type = $f['type'];
            if ($type === 'number') {
                $r = [...$r, 'numeric', 'min:0', 'max:999999999'];
            } elseif ($type === 'date') {
                $r = [...$r, 'date_format:Y-m-d'];
            } elseif ($type === 'datetime-local') {
                $r = [...$r, 'date_format:Y-m-d\\TH:i'];
            } elseif ($type === 'checkbox') {
                $r = [...$r, 'boolean'];
            } elseif ($type === 'staff') {
                $r = [...$r, 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->whereNotIn('access_role', [User::MERCHANDISER_ROLE, User::MERCHANDISER_SUPERVISOR_ROLE, User::BRAND_PROMOTER_ROLE]))];
            } elseif ($type === 'staff_multi') {
                $r = [...$r, 'array', 'max:500'];
                $rules[$key.'.*'] = ['integer', 'distinct', Rule::in(array_keys($service->options('staff', $viewer)))];
            } elseif ($type === 'department') {
                $r = [...$r, Rule::in(array_keys(config('performance.departments')))];
            } elseif ($type === 'select') {
                $r = [...$r, Rule::in($f['options'])];
            } elseif (str_starts_with($type, 'record=')) {
                $r = [...$r, 'integer', Rule::in(array_keys($service->options(substr($type, 7), $viewer)))];
            } else {
                $r = [...$r, 'string', 'max:'.($type === 'textarea' ? 6000 : 255)];
            }
            if ($kind === 'staff' && $key === 'email') {
                $r = [...$r, 'email', Rule::unique('users', 'email')->ignore($id)];
            }
            if ($kind === 'staff' && $key === 'staff_id_number') {
                $r = [...$r, Rule::unique('users', 'staff_id_number')->ignore($id)];
            }
            if ($kind === 'asset' && $key === 'asset_tag') {
                $r = [...$r, Rule::unique('assets', 'asset_tag')->ignore($id)];
            }
            if (in_array($key, ['quantity', 'quantity_procured', 'reorder_level', 'required_completions', 'completed', 'responses'], true)) {
                $r[] = 'integer';
            }
            if (in_array($key, ['received_on', 'signed_on', 'completed_on', 'approved_on', 'resolved_on', 'filled_on', 'confirmed_on', 'paid_on'], true)) {
                $r[] = 'before_or_equal:today';
            }
            $rules[$key] = $r;
        }
        $v = Validator::make($data, $rules)->validate();
        foreach ($schema['fields'] as $key => $f) {
            if ($f['type'] === 'checkbox') {
                $v[$key] = (bool) ($v[$key] ?? false);
            }
        }
        foreach ([['resolved_on', 'opened_on'], ['resolved_on', 'starts_on'], ['last_day', 'notice_on'], ['confirmation_due', 'starts_on'], ['completed_on', 'starts_on'], ['ends_at', 'starts_at'], ['period_end', 'period_start']] as [$end,$start]) {
            if (! empty($v[$end]) && ! empty($v[$start]) && $v[$end] < $v[$start]) {
                throw ValidationException::withMessages([$end => 'End date must not precede the start.']);
            }
        }
        if (isset($v['completed'],$v['required_completions']) && $v['completed'] > $v['required_completions']) {
            throw ValidationException::withMessages(['completed' => 'Completions cannot exceed the required count.']);
        }
        if ($kind === 'vehicle' && HrRegisterRecord::where('kind', 'vehicle')->when($id, fn ($q) => $q->whereKeyNot($id))->get()->contains(fn ($r) => mb_strtolower(trim($r->data['registration_number'] ?? '')) === mb_strtolower(trim($v['registration_number'])))) {
            throw ValidationException::withMessages(['registration_number' => 'This registration is already in the vehicle register. Edit the existing vehicle.']);
        }
        if ($kind === 'exit' && ! empty($v['clearance_done']) && Asset::where('assigned_to', $v['staff_id'])->where('condition', '!=', 'Disposed')->exists()) {
            throw ValidationException::withMessages(['clearance_done' => 'Recover the staff member’s assigned assets before marking clearance complete.']);
        }
        if ($kind === 'policy' && ($v['state'] ?? '') === 'Approved' && empty($v['approved_on'])) {
            throw ValidationException::withMessages(['approved_on' => 'Record the policy approval date.']);
        }

        return $v;
    }

    public function save(string $kind, array $data, User $viewer, ?int $id = null, mixed $revision = null, ?array $attachment = null)
    {
        $v = $this->validate($kind, $data, $viewer, $id);

        return DB::transaction(function () use ($kind, $v, $viewer, $id, $revision, $attachment) {
            $row = $id ? match ($kind) {
                'staff' => User::internalStaff()->lockForUpdate()->findOrFail($id),'vendor' => \App\Models\ThirdPartyVendor::lockForUpdate()->findOrFail($id),'asset' => Asset::where(fn ($q) => $q->where('is_warehouse_tracked', false)->orWhereNull('is_warehouse_tracked'))->lockForUpdate()->findOrFail($id),default => HrRegisterRecord::where('kind', $kind)->lockForUpdate()->findOrFail($id)
            } : null;
            if ($row) {
                $actual = in_array($kind, ['staff', 'asset', 'vendor']) ? hash('sha256', json_encode($row->getRawOriginal())) : $row->revision;
                abort_unless((string) $actual === (string) $revision, 409, 'This record changed. Refresh before editing.');
            }
            $before = $row?->getAttributes() ?? [];
            if ($kind === 'staff') {
                if (! $row) {
                    $row = new User(['password' => Hash::make(Str::random(64)), 'status' => 'active', 'access_role' => 'staff', 'job_level' => 'executive']);
                }
                $row->forceFill($v)->save();
            } elseif ($kind === 'vendor') {
                if (! $row) {
                    $row = new \App\Models\ThirdPartyVendor;
                }
                $row->fill(['name' => $v['name'], 'category' => $v['service'], 'hr_details' => array_replace($row->hr_details ?? [], ['contact' => $v['contact'] ?? null, 'contract_end' => $v['contract_end'] ?? null, 'state' => $v['state'] ?? null, 'updated_by' => $viewer->id])])->save();
            } elseif ($kind === 'asset') {
                $extra = ['acquired_on' => $v['acquired_on'] ?? null, 'inventory_quantity' => (int) $v['quantity_procured']];
                unset($v['acquired_on']);
                if (! $row) {
                    $row = new Asset(['type' => 'Office equipment', 'status' => 'available', 'is_warehouse_tracked' => false, 'added_by' => $viewer->id]);
                }
                $row->fill($v + ['hr_details' => array_replace($row->hr_details ?? [], $extra)]);
                $row->status = ! empty($v['assigned_to']) ? 'assigned' : 'available';
                if (($v['condition'] ?? '') === 'Disposed') {
                    $row->status = 'disposed';
                }$row->save();
                AssetLog::create(['asset_id' => $row->id, 'user_id' => $viewer->id, 'action' => 'hr_register_update', 'reported_condition' => $row->condition, 'notes' => 'HR register updated; assignment and quantity retained in change history.']);
            } else {
                if (! $row) {
                    $row = new HrRegisterRecord(['kind' => $kind, 'created_by' => $viewer->id]);
                }
                $label = $v['name'] ?? $v['title'] ?? $v['role'] ?? $v['item'] ?? $v['location'] ?? $v['service'] ?? null;
                if (! $label && ! empty($v['staff_id'])) {
                    $label = User::find($v['staff_id'])->name.' · '.($v['document_type'] ?? $v['case_type'] ?? $kind);
                }
                $row->fill(['label' => $label ?: app(HrLiveRegisters::class)->schema($kind)['label'], 'data' => $v, 'updated_by' => $viewer->id]);
                if ($attachment) {
                    $row->attachment = $attachment;
                }if ($id) {
                    $row->revision++;
                }$row->save();
            }
            if (in_array($kind, ['vehicle', 'driver'], true)) {
                $this->syncFleetLinks($row, $viewer);
            }
            $after = $row->getAttributes();
            unset($before['password'],$after['password'],$before['remember_token'],$after['remember_token']);
            PerformanceWorkflow::record('hr_register_'.$kind, $row->id, $viewer->id, $id ? 'update' : 'create', $before, $after, 'HR live register change');

            return $row;
        });
    }

    private function syncFleetLinks(HrRegisterRecord $row, User $actor): void
    {
        $otherKind = $row->kind === 'vehicle' ? 'driver' : 'vehicle';
        $localField = $row->kind === 'vehicle' ? 'driver_id' : 'vehicle_id';
        $remoteField = $row->kind === 'vehicle' ? 'vehicle_id' : 'driver_id';
        $selected = (int) ($row->data[$localField] ?? 0);
        $others = HrRegisterRecord::where('kind', $otherKind)->lockForUpdate()->get();
        if ($selected) {
            $chosen = $others->firstWhere('id', $selected);
            if (! $chosen) {
                throw ValidationException::withMessages([$localField => 'The linked fleet record is unavailable.']);
            }
            if (! empty($chosen->data[$remoteField]) && (int) $chosen->data[$remoteField] !== $row->id) {
                throw ValidationException::withMessages([$localField => 'This driver / vehicle is already assigned elsewhere. Clear its existing assignment first.']);
            }
        }
        foreach ($others as $other) {
            $isSelected = $selected === $other->id;
            $hasLink = (int) ($other->data[$remoteField] ?? 0) === $row->id;
            if (! $isSelected && ! $hasLink) {
                continue;
            }
            $before = $other->getAttributes();
            $data = $other->data;
            $data[$remoteField] = $isSelected ? $row->id : null;
            if ($data === $other->data) {
                continue;
            }$other->update(['data' => $data, 'updated_by' => $actor->id, 'revision' => $other->revision + 1]);
            PerformanceWorkflow::record('hr_register_'.$otherKind,$other->id,$actor->id,'link_assignment',$before,$other->getAttributes(),'Driver and vehicle assignment synchronized from the linked register.');
        }
    }
}
