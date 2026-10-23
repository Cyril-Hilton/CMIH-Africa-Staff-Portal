<?php

namespace App\Support;

use App\Models\SiteContent;
use Illuminate\Support\Carbon;

class MerchandiserClockWindows
{
    public const VISIT_DEFAULTS = [
        'label' => 'Outlet Visit Window',
        'short_label' => 'Visit Window',
        'icon' => 'clock',
        'start' => '08:00',
        'end' => '17:00',
        'late_start' => '09:00',
    ];

    public const DEFAULTS = [
        'morning' => [
            'label' => 'Morning Clock-in',
            'short_label' => 'Morning',
            'icon' => 'clock',
            'start' => '09:00',
            'end' => '10:00',
        ],
        'midday' => [
            'label' => 'Midday Check-in',
            'short_label' => 'Midday',
            'icon' => 'clock',
            'start' => '12:00',
            'end' => '13:00',
        ],
        'cob' => [
            'label' => 'Clock-out / COB',
            'short_label' => 'Clock-out',
            'icon' => 'clock',
            'start' => '16:30',
            'end' => '17:30',
        ],
    ];

    public static function settings(): array
    {
        return collect(self::DEFAULTS)
            ->mapWithKeys(function (array $defaults, string $slot) {
                $start = self::cleanTime(
                    SiteContent::getValue("merchandiser_clock_{$slot}_start", $defaults['start']),
                    $defaults['start']
                );
                $end = self::cleanTime(
                    SiteContent::getValue("merchandiser_clock_{$slot}_end", $defaults['end']),
                    $defaults['end']
                );

                return [
                    $slot => [
                        ...$defaults,
                        'start' => $start,
                        'end' => $end,
                        'range' => "{$start}-{$end}",
                    ],
                ];
            })
            ->all();
    }

    public static function visitSettings(): array
    {
        $start = self::cleanTime(
            SiteContent::getValue('merchandiser_visit_window_start', self::VISIT_DEFAULTS['start']),
            self::VISIT_DEFAULTS['start']
        );
        $end = self::cleanTime(
            SiteContent::getValue('merchandiser_visit_window_end', self::VISIT_DEFAULTS['end']),
            self::VISIT_DEFAULTS['end']
        );
        $lateStart = self::cleanTime(
            SiteContent::getValue('merchandiser_visit_late_start', self::VISIT_DEFAULTS['late_start']),
            self::VISIT_DEFAULTS['late_start']
        );

        return [
            ...self::VISIT_DEFAULTS,
            'start' => $start,
            'end' => $end,
            'late_start' => $lateStart,
            'range' => "{$start}-{$end}",
        ];
    }

    public static function visitWindow(string $timezone = 'Africa/Accra', ?Carbon $date = null): array
    {
        $date = $date ? $date->copy()->timezone($timezone) : Carbon::today($timezone);
        $setting = self::visitSettings();
        $start = Carbon::parse($date->toDateString().' '.$setting['start'], $timezone);
        $end = Carbon::parse($date->toDateString().' '.$setting['end'], $timezone);
        $lateStart = Carbon::parse($date->toDateString().' '.$setting['late_start'], $timezone);

        if ($end->lt($start)) {
            $end->addDay();
        }

        return [
            ...$setting,
            'start_at' => $start,
            'end_at' => $end,
            'late_start_at' => $lateStart,
        ];
    }

    public static function windows(string $timezone = 'Africa/Accra', ?Carbon $date = null): array
    {
        $date = $date ? $date->copy()->timezone($timezone) : Carbon::today($timezone);

        return collect(self::settings())
            ->mapWithKeys(function (array $setting, string $slot) use ($timezone, $date) {
                $start = Carbon::parse($date->toDateString().' '.$setting['start'], $timezone);
                $end = Carbon::parse($date->toDateString().' '.$setting['end'], $timezone);

                if ($end->lt($start)) {
                    $end->addDay();
                }

                return [
                    $slot => [
                        ...$setting,
                        'start_at' => $start,
                        'end_at' => $end,
                    ],
                ];
            })
            ->all();
    }

    public static function validationRules(): array
    {
        $rules = [];

        foreach (array_keys(self::DEFAULTS) as $slot) {
            $rules["{$slot}_start"] = ['required', 'date_format:H:i'];
            $rules["{$slot}_end"] = ['required', 'date_format:H:i'];
        }

        return $rules;
    }

    public static function visitValidationRules(): array
    {
        return [
            'visit_start' => ['required', 'date_format:H:i'],
            'visit_end' => ['required', 'date_format:H:i'],
            'late_start' => ['required', 'date_format:H:i'],
        ];
    }

    public static function persist(array $validated, int $updatedBy): void
    {
        foreach (array_keys(self::DEFAULTS) as $slot) {
            foreach (['start', 'end'] as $edge) {
                SiteContent::updateOrCreate(
                    ['key' => "merchandiser_clock_{$slot}_{$edge}"],
                    [
                        'value' => $validated["{$slot}_{$edge}"],
                        'type' => 'text',
                        'updated_by' => $updatedBy,
                    ]
                );
            }
        }

        SiteContent::forgetCachedValues();
    }

    public static function persistVisitWindow(array $validated, int $updatedBy): void
    {
        foreach ([
            'merchandiser_visit_window_start' => $validated['visit_start'],
            'merchandiser_visit_window_end' => $validated['visit_end'],
            'merchandiser_visit_late_start' => $validated['late_start'],
        ] as $key => $value) {
            SiteContent::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => 'text',
                    'updated_by' => $updatedBy,
                ]
            );
        }

        SiteContent::forgetCachedValues();
    }

    private static function cleanTime(string $time, string $fallback): string
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $fallback;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return $fallback;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
