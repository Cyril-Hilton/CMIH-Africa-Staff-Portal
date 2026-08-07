<?php

namespace App\Console\Commands;

use App\Models\KeyDistributor;
use App\Models\Outlet;
use Illuminate\Console\Command;

class BackfillKeyDistributorCoordinates extends Command
{
    protected $signature = 'merchandisers:backfill-kd-coordinates
        {--write : Persist high-confidence coordinate updates}
        {--suggest-outlets : Include outlet-centroid suggestions for admin review}';

    protected $description = 'Backfill missing KD GPS coordinates from explicit KD-row coordinates and report lower-confidence outlet suggestions.';

    public function handle(): int
    {
        $write = (bool) $this->option('write');
        $includeSuggestions = (bool) $this->option('suggest-outlets');
        $updates = [];
        $suggestions = [];

        KeyDistributor::query()
            ->where(function ($query) {
                $query->whereNull('latitude')->orWhereNull('longitude');
            })
            ->orderBy('name')
            ->each(function (KeyDistributor $kd) use ($write, $includeSuggestions, &$updates, &$suggestions) {
                $candidate = $this->candidateFromAddress((string) $kd->address);

                if ($candidate) {
                    $updates[] = [
                        'id' => $kd->id,
                        'name' => $kd->name,
                        'source' => $candidate['source'],
                        'latitude' => $candidate['latitude'],
                        'longitude' => $candidate['longitude'],
                        'action' => $write ? 'updated' : 'dry-run',
                    ];

                    if ($write) {
                        $kd->forceFill([
                            'latitude' => $candidate['latitude'],
                            'longitude' => $candidate['longitude'],
                        ])->save();
                    }

                    return;
                }

                if (! $includeSuggestions) {
                    return;
                }

                $suggestion = $this->outletCentroidSuggestion($kd);
                if ($suggestion) {
                    $suggestions[] = [
                        'id' => $kd->id,
                        'name' => $kd->name,
                        'source' => 'outlet_centroid_review_only',
                        'latitude' => $suggestion['latitude'],
                        'longitude' => $suggestion['longitude'],
                        'action' => 'not-updated',
                        'note' => "{$suggestion['outlet_count']} outlet coords; span {$suggestion['lat_span']} lat / {$suggestion['lng_span']} lng",
                    ];
                }
            });

        if ($updates) {
            $this->info($write ? 'High-confidence KD coordinates updated:' : 'High-confidence KD coordinates found (dry-run):');
            $this->table(['ID', 'KD', 'Source', 'Latitude', 'Longitude', 'Action'], array_map(
                fn (array $row) => [$row['id'], $row['name'], $row['source'], $row['latitude'], $row['longitude'], $row['action']],
                $updates
            ));
        } else {
            $this->info('No high-confidence KD-row coordinates found for missing KDs.');
        }

        if ($suggestions) {
            $this->warn('Outlet-derived suggestions are review-only and were not written:');
            $this->table(['ID', 'KD', 'Source', 'Latitude', 'Longitude', 'Action', 'Note'], array_map(
                fn (array $row) => [$row['id'], $row['name'], $row['source'], $row['latitude'], $row['longitude'], $row['action'], $row['note']],
                $suggestions
            ));
        }

        if (! $write && $updates) {
            $this->line('Run again with --write to persist only the high-confidence KD-row coordinates.');
        }

        return self::SUCCESS;
    }

    private function candidateFromAddress(string $address): ?array
    {
        if (preg_match('/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', $address, $matches)) {
            return $this->validCoordinate((float) $matches[1], (float) $matches[2])
                ? [
                    'source' => 'address_decimal',
                    'latitude' => round((float) $matches[1], 8),
                    'longitude' => round((float) $matches[2], 8),
                ]
                : null;
        }

        $dmsPattern = '/(\d{1,2})[^0-9]+(\d{1,2})[^0-9]+(\d{1,2}(?:\.\d+)?)"?\s*([NS]).*?(\d{1,3})[^0-9]+(\d{1,2})[^0-9]+(\d{1,2}(?:\.\d+)?)"?\s*([EW])/i';
        if (preg_match($dmsPattern, $address, $matches)) {
            $latitude = $this->dmsToDecimal($matches[1], $matches[2], $matches[3], $matches[4]);
            $longitude = $this->dmsToDecimal($matches[5], $matches[6], $matches[7], $matches[8]);

            return $this->validCoordinate($latitude, $longitude)
                ? [
                    'source' => 'address_dms',
                    'latitude' => round($latitude, 8),
                    'longitude' => round($longitude, 8),
                ]
                : null;
        }

        return null;
    }

    private function outletCentroidSuggestion(KeyDistributor $kd): ?array
    {
        $stats = Outlet::query()
            ->where('kd_id', $kd->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('COUNT(*) as outlet_count')
            ->selectRaw('AVG(latitude) as avg_lat')
            ->selectRaw('AVG(longitude) as avg_lng')
            ->selectRaw('MIN(latitude) as min_lat')
            ->selectRaw('MAX(latitude) as max_lat')
            ->selectRaw('MIN(longitude) as min_lng')
            ->selectRaw('MAX(longitude) as max_lng')
            ->first();

        if (! $stats || (int) $stats->outlet_count === 0) {
            return null;
        }

        return [
            'outlet_count' => (int) $stats->outlet_count,
            'latitude' => round((float) $stats->avg_lat, 8),
            'longitude' => round((float) $stats->avg_lng, 8),
            'lat_span' => round(abs((float) $stats->max_lat - (float) $stats->min_lat), 4),
            'lng_span' => round(abs((float) $stats->max_lng - (float) $stats->min_lng), 4),
        ];
    }

    private function dmsToDecimal(string $degrees, string $minutes, string $seconds, string $hemisphere): float
    {
        $value = (float) $degrees + ((float) $minutes / 60) + ((float) $seconds / 3600);

        return in_array(strtoupper($hemisphere), ['S', 'W'], true) ? -$value : $value;
    }

    private function validCoordinate(float $latitude, float $longitude): bool
    {
        return $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;
    }
}
