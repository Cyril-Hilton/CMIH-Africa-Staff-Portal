<?php

namespace App\Services;

use App\Models\PhoneDirectory;
use App\Models\ThirdPartyVendor;
use Illuminate\Support\Collection;

/** A shared read model; existing directory contacts are never copied into another vendor ledger. */
class HrVendorDirectory
{
    public function records(): Collection
    {
        $key = fn ($name) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $name)));
        $rows = ThirdPartyVendor::orderBy('name')->get()->mapWithKeys(fn ($vendor) => [$key($vendor->name) => [
            'id' => $vendor->id, 'kind' => 'vendor', 'label' => $vendor->name, 'source' => 'Shared vendor register',
            'revision' => hash('sha256', json_encode($vendor->getRawOriginal())), 'attachment' => null, 'url' => null,
            'data' => ['name' => $vendor->name, 'service' => $vendor->category, 'contact' => $vendor->hr_details['contact'] ?? null, 'contract_end' => $vendor->hr_details['contract_end'] ?? null, 'state' => $vendor->hr_details['state'] ?? null],
        ]]);
        $contacts = PhoneDirectory::where(fn ($q) => $q->where('is_vendor', true)->orWhere('category', 'vendor'))->orderBy('id')->get()->groupBy(fn ($contact) => $key($contact->company ?: $contact->name));
        foreach ($contacts as $identity => $group) {
            $first = $group->first();
            $contactText = $group->map(fn ($contact) => collect([$contact->name, $contact->job_title, $contact->phone, $contact->extension ? 'Ext. '.$contact->extension : null, $contact->email])->filter()->implode(' · '))->unique()->implode("\n");
            if ($rows->has($identity)) {
                $row = $rows->get($identity);
                $row['source'] = 'Shared vendor register + existing vendor directory';
                if (empty($row['data']['contact'])) {
                    $row['data']['contact'] = $contactText;
                }
                $rows->put($identity, $row);
            } else {
                $name = $first->company ?: $first->name;
                $rows->put($identity, ['id' => -(int) $first->id, 'kind' => 'vendor', 'label' => $name, 'source' => 'Existing vendor directory · '.$group->count().' contact(s)', 'read_only' => true, 'revision' => null, 'attachment' => null, 'url' => null, 'data' => ['name' => $name, 'service' => null, 'contact' => $contactText, 'contract_end' => null, 'state' => null]]);
            }
        }

        return $rows->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }
}
