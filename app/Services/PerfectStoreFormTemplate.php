<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PerfectStoreFormTemplate
{
    public const KEY = 'perfect_store_v1';

    public function schema(): array
    {
        return config('perfect_store_form', []);
    }

    public function sections(): array
    {
        return $this->schema()['sections'] ?? [];
    }

    public function questions(): Collection
    {
        return collect($this->sections())
            ->flatMap(fn (array $section) => collect($section['questions'] ?? [])
                ->map(fn (array $question) => $question + ['section_key' => $section['key'] ?? null, 'section_title' => $section['title'] ?? null]));
    }

    public function questionKeys(): array
    {
        return $this->questions()->pluck('key')->filter()->values()->all();
    }

    public function questionMeta(): array
    {
        return $this->questions()
            ->map(fn (array $question) => Arr::only($question, [
                'key',
                'label',
                'type',
                'required',
                'metric',
                'section_key',
                'section_title',
            ]))
            ->values()
            ->all();
    }

    public function validationRules(): array
    {
        $rules = [
            'answers' => ['required', 'array'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
        ];

        foreach ($this->questions() as $question) {
            $fieldRules = [];
            $fieldRules[] = $question['required'] ?? false ? 'required' : 'nullable';

            if (($question['type'] ?? null) === 'number') {
                $fieldRules[] = 'numeric';
                $fieldRules[] = 'min:0';
            } elseif (($question['type'] ?? null) === 'planogram_status') {
                $fieldRules[] = Rule::in(['1', '0', 'OOS']);
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:1000';
            }

            $rules['answers.' . $question['key']] = $fieldRules;
        }

        return $rules;
    }

    public function defaultsFor(User $user, ?Outlet $outlet): array
    {
        $user->loadMissing(['merchandiserRegion', 'merchandiserKd', 'supervisor', 'merchandiserTm', 'merchandiserDsr', 'merchandiserRsm']);
        $outlet?->loadMissing('keyDistributor');

        $defaultsByLabel = [
            'REGION' => $user->merchandiserRegion?->name,
            'RSM' => $user->merchandiserRsm?->name,
            'TM' => $user->merchandiserTm?->name,
            'DSR' => $user->merchandiserDsr?->name ?? $user->name,
            'SUPERVISOR' => $user->supervisor?->name,
            'KEY DISTRIBUTOR' => $outlet?->keyDistributor?->name ?? $user->merchandiserKd?->name,
            'Merchandiser Name' => $user->name,
            'OUTLET NAME' => $outlet?->name,
            'OUTLET CODE' => $outlet?->code,
            'CHANNEL' => $outlet?->channel_type,
        ];

        return $this->questions()
            ->mapWithKeys(fn (array $question) => [
                $question['key'] => $defaultsByLabel[$question['label']] ?? null,
            ])
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    public function applyTrustedDefaults(User $user, ?Outlet $outlet, array $answers): array
    {
        return array_replace($answers, $this->defaultsFor($user, $outlet));
    }

    public function optionsFor(array $question, mixed $currentValue = null): array
    {
        $options = $question['options'] ?? [];

        if (filled($currentValue) && ! in_array($currentValue, $options, true)) {
            $options[] = (string) $currentValue;
        }

        return $options;
    }

    public function sanitizeAnswers(array $answers): array
    {
        $validKeys = array_flip($this->questionKeys());

        return collect($answers)
            ->filter(fn ($value, string $key) => isset($validKeys[$key]))
            ->map(fn ($value) => is_array($value) ? array_values($value) : trim((string) $value))
            ->all();
    }

    public function normalizedMetrics(array $answers): array
    {
        $metrics = [
            'metadata' => [],
            'posm' => [],
            'quantity_totals' => [
                'osa' => 0.0,
                'npd' => 0.0,
                'combined' => 0.0,
            ],
            'facings_total' => 0.0,
            'share_of_shelf' => [],
            'planogram' => [
                'compliant' => 0,
                'non_compliant' => 0,
                'out_of_stock' => 0,
                'total' => 0,
                'compliance_rate' => 0.0,
            ],
            'section_completion' => [],
        ];

        foreach ($this->sections() as $section) {
            $answered = 0;
            $required = 0;

            foreach ($section['questions'] ?? [] as $question) {
                $key = $question['key'];
                $answer = Arr::get($answers, $key);

                if ($question['required'] ?? false) {
                    $required++;
                }

                if (filled($answer) || $answer === '0') {
                    $answered++;
                }

                $metric = $question['metric'] ?? null;
                $sectionKey = $section['key'] ?? null;

                if (($section['kind'] ?? null) === 'metadata') {
                    $metrics['metadata'][$question['label']] = $answer;
                    continue;
                }

                if (($section['kind'] ?? null) === 'posm') {
                    $metrics['posm'][Str::snake($question['label'])] = $answer;
                    continue;
                }

                if ($metric === 'quantity_on_shelf') {
                    $bucket = $sectionKey === 'npd_quantity_on_shelf' ? 'npd' : 'osa';
                    $numeric = (float) ($answer ?: 0);
                    $metrics['quantity_totals'][$bucket] += $numeric;
                    $metrics['quantity_totals']['combined'] += $numeric;
                    continue;
                }

                if ($metric === 'facings') {
                    $metrics['facings_total'] += (float) ($answer ?: 0);
                    continue;
                }

                if ($metric === 'share_of_shelf_total') {
                    $metrics['share_of_shelf'][Str::snake($question['label'])] = (float) ($answer ?: 0);
                    continue;
                }

                if ($metric === 'planogram_status') {
                    $metrics['planogram']['total']++;
                    if ($answer === '1') {
                        $metrics['planogram']['compliant']++;
                    } elseif ($answer === 'OOS') {
                        $metrics['planogram']['out_of_stock']++;
                    } else {
                        $metrics['planogram']['non_compliant']++;
                    }
                }
            }

            $metrics['section_completion'][$section['key']] = [
                'answered' => $answered,
                'required' => $required,
                'rate' => $required > 0 ? round(($answered / $required) * 100, 1) : 100.0,
            ];
        }

        $inStockPlanogramTotal = $metrics['planogram']['total'] - $metrics['planogram']['out_of_stock'];
        $metrics['planogram']['compliance_rate'] = $inStockPlanogramTotal > 0
            ? round(($metrics['planogram']['compliant'] / $inStockPlanogramTotal) * 100, 1)
            : 0.0;

        return $metrics;
    }
}
