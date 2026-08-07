<?php

namespace App\Support;

use App\Models\Sku;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class SkuShelfFixtureGenerator
{
    /**
     * Generate deterministic synthetic shelf scenes that can be used to
     * regression-test SKU recognition prompts and expected counts.
     */
    public function generate(int $count, string $outputPath): array
    {
        $count = max(1, min($count, 30));
        $absoluteOutput = $this->absolutePath($outputPath);

        if (! is_dir($absoluteOutput) && ! mkdir($absoluteOutput, 0775, true) && ! is_dir($absoluteOutput)) {
            throw new RuntimeException("Unable to create fixture directory: {$absoluteOutput}");
        }

        $skus = $this->catalog();
        $scenarios = [
            ['key' => 'front_wide', 'label' => 'Front wide shelf photo', 'scale' => 1.0, 'skew' => 0, 'occlusion' => false, 'single' => false],
            ['key' => 'high_count_24', 'label' => 'High-count shelf with twenty-four units', 'scale' => 0.82, 'skew' => 0, 'occlusion' => false, 'single' => false, 'fixed_total' => 24],
            ['key' => 'far_distance', 'label' => 'Far distance aisle capture', 'scale' => 0.72, 'skew' => 0, 'occlusion' => false, 'single' => false],
            ['key' => 'left_angle', 'label' => 'Human photo from left angle', 'scale' => 0.92, 'skew' => -8, 'occlusion' => false, 'single' => false],
            ['key' => 'right_angle_occluded', 'label' => 'Right angle with partial occlusion', 'scale' => 0.9, 'skew' => 8, 'occlusion' => true, 'single' => false],
            ['key' => 'close_crop', 'label' => 'Close crop with stacked products', 'scale' => 1.2, 'skew' => 0, 'occlusion' => false, 'single' => false],
            ['key' => 'single_item_left', 'label' => 'Only one item left on shelf', 'scale' => 1.05, 'skew' => 0, 'occlusion' => false, 'single' => true],
            ['key' => 'low_light', 'label' => 'Low light supermarket shelf', 'scale' => 0.95, 'skew' => 0, 'occlusion' => true, 'single' => false],
            ['key' => 'busy_mixed_shelf', 'label' => 'Busy mixed shelf with competing SKUs', 'scale' => 0.88, 'skew' => -4, 'occlusion' => true, 'single' => false],
        ];

        $manifest = [
            'generated_at' => now()->toIso8601String(),
            'format' => 'svg',
            'purpose' => 'Synthetic shelf scenes with expected SKU counts for AI prompt regression.',
            'fixtures' => [],
        ];

        for ($index = 1; $index <= $count; $index++) {
            mt_srand(20260702 + $index);

            $scenario = $scenarios[($index - 1) % count($scenarios)];
            $selectedSkus = $this->selectSkus($skus, ($scenario['single'] ?? false) || isset($scenario['fixed_total']) ? 1 : mt_rand(2, min(5, $skus->count())));
            $expectedCounts = [];

            foreach ($selectedSkus as $sku) {
                $expectedCounts[$sku['name']] = $scenario['fixed_total'] ?? (($scenario['single'] ?? false) ? 1 : mt_rand(1, 8));
            }

            $fileName = sprintf('fixture_%02d_%s.svg', $index, $scenario['key']);
            $filePath = $absoluteOutput.DIRECTORY_SEPARATOR.$fileName;

            file_put_contents($filePath, $this->renderSvg($scenario, $selectedSkus, $expectedCounts, $index));

            $manifest['fixtures'][] = [
                'file' => $fileName,
                'scenario' => $scenario['key'],
                'label' => $scenario['label'],
                'expected_counts' => $expectedCounts,
                'notes' => $this->scenarioNotes($scenario),
            ];
        }

        file_put_contents(
            $absoluteOutput.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return [
            'path' => $absoluteOutput,
            'count' => count($manifest['fixtures']),
            'manifest' => $absoluteOutput.DIRECTORY_SEPARATOR.'manifest.json',
        ];
    }

    private function catalog(): Collection
    {
        $storedSkus = Sku::query()
            ->orderByRaw('reference_image_path is null')
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'aliases'])
            ->map(fn (Sku $sku): array => [
                'id' => $sku->id,
                'name' => $sku->name,
                'aliases' => $sku->aliases ?? [],
            ]);

        if ($storedSkus->isNotEmpty()) {
            return $storedSkus;
        }

        return collect([
            ['id' => null, 'name' => 'Sunlight Multi-Purpose Soap Spring', 'aliases' => ['sunlight soap']],
            ['id' => null, 'name' => 'Pepsodent Herbal Toothpaste', 'aliases' => ['pepsodent herbal']],
            ['id' => null, 'name' => 'Closeup Red Hot Toothpaste', 'aliases' => ['closeup red']],
            ['id' => null, 'name' => 'Geisha Black Soap', 'aliases' => ['geisha soap']],
            ['id' => null, 'name' => 'Royco Chicken Cubes', 'aliases' => ['royco cubes']],
        ]);
    }

    private function selectSkus(Collection $skus, int $needed): Collection
    {
        return $skus
            ->shuffle()
            ->take(max(1, min($needed, $skus->count())))
            ->values();
    }

    private function renderSvg(array $scenario, Collection $skus, array $counts, int $fixtureIndex): string
    {
        $width = 1600;
        $height = 1000;
        $background = $scenario['key'] === 'low_light' ? '#080607' : '#130909';
        $shelfColor = $scenario['key'] === 'low_light' ? '#1b1414' : '#2b1713';
        $lightOpacity = $scenario['key'] === 'low_light' ? '0.16' : '0.34';

        $products = [];
        $rowY = 260;
        $slotX = 130;
        $slotGap = 34;
        $maxPerRow = $scenario['key'] === 'close_crop' ? 5 : 8;

        foreach ($skus as $sku) {
            $quantity = $counts[$sku['name']] ?? 1;
            $productWidth = (int) round(95 * $scenario['scale']);
            $productHeight = (int) round(155 * $scenario['scale']);
            $label = $this->labelFor($sku['name']);
            $color = $this->colorFor($sku['name']);

            for ($copy = 0; $copy < $quantity; $copy++) {
                $row = intdiv(count($products), $maxPerRow);
                $column = count($products) % $maxPerRow;
                $x = $slotX + ($column * ($productWidth + $slotGap)) + mt_rand(-8, 8);
                $y = $rowY + ($row * 215) + mt_rand(-6, 9);
                $products[] = $this->productSvg($x, $y, $productWidth, $productHeight, $label, $color, $sku['name']);
            }
        }

        if ($scenario['key'] === 'busy_mixed_shelf') {
            for ($i = 0; $i < 8; $i++) {
                $products[] = $this->productSvg(
                    1040 + (($i % 4) * 100),
                    280 + (intdiv($i, 4) * 210),
                    82,
                    138,
                    'OTHER',
                    '#505050',
                    'Competing non-catalog SKU'
                );
            }
        }

        $occlusion = '';
        if ($scenario['occlusion']) {
            $occlusion = '<rect x="940" y="215" width="190" height="580" rx="38" fill="#111" opacity="0.42"/>'
                .'<rect x="1020" y="260" width="58" height="470" rx="26" fill="#2d1b13" opacity="0.65"/>';
        }

        $transform = sprintf(
            'translate(%d 0) skewX(%d)',
            $scenario['skew'] < 0 ? 45 : ($scenario['skew'] > 0 ? -45 : 0),
            $scenario['skew']
        );
        $productsSvg = $this->implodeSvg($products);
        $scenarioTitle = $this->escape(strtoupper($scenario['label']));

        $expected = collect($counts)
            ->map(fn (int $quantity, string $name): string => $this->escape($name).': '.$quantity)
            ->implode(' · ');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" role="img" aria-label="Synthetic SKU shelf fixture {$fixtureIndex}">
    <defs>
        <linearGradient id="sceneGlow" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#5a1616" stop-opacity="0.72"/>
            <stop offset="0.58" stop-color="#050505" stop-opacity="1"/>
            <stop offset="1" stop-color="#1e0909" stop-opacity="1"/>
        </linearGradient>
        <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="18" stdDeviation="14" flood-color="#000" flood-opacity="0.45"/>
        </filter>
    </defs>
    <rect width="100%" height="100%" fill="{$background}"/>
    <rect width="100%" height="100%" fill="url(#sceneGlow)" opacity="0.94"/>
    <ellipse cx="720" cy="110" rx="650" ry="170" fill="#ffd78a" opacity="{$lightOpacity}"/>
    <g transform="{$transform}">
        <rect x="80" y="230" width="1370" height="650" rx="38" fill="{$shelfColor}" stroke="#5f3a2f" stroke-width="5" filter="url(#softShadow)"/>
        <rect x="110" y="430" width="1310" height="30" rx="10" fill="#5c382b"/>
        <rect x="110" y="650" width="1310" height="30" rx="10" fill="#5c382b"/>
        <rect x="110" y="855" width="1310" height="25" rx="10" fill="#3e261f"/>
        <g>
            {$productsSvg}
        </g>
        {$occlusion}
    </g>
    <rect x="58" y="58" width="1484" height="104" rx="24" fill="#0a0707" opacity="0.78" stroke="#ffffff" stroke-opacity="0.14"/>
    <text x="92" y="104" fill="#f8f3ed" font-family="Arial, Helvetica, sans-serif" font-size="30" font-weight="700" letter-spacing="5">SKU AI FIXTURE {$fixtureIndex}: {$scenarioTitle}</text>
    <text x="92" y="140" fill="#ffd54a" font-family="Arial, Helvetica, sans-serif" font-size="18" font-weight="700">Expected: {$expected}</text>
</svg>
SVG;
    }

    private function productSvg(int $x, int $y, int $width, int $height, string $label, string $color, string $skuName): string
    {
        $escapedLabel = $this->escape($label);
        $escapedSku = $this->escape($skuName);
        $fontSize = $width < 90 ? 15 : 18;
        $stripeY = $y + 14;
        $labelX = $x + 12;
        $labelY = $y + 70;
        $subLabelY = $y + 96;

        return <<<SVG
<g aria-label="{$escapedSku}">
    <rect x="{$x}" y="{$y}" width="{$width}" height="{$height}" rx="14" fill="{$color}" stroke="#f8f1d7" stroke-width="3"/>
    <rect x="{$x}" y="{$y}" width="{$width}" height="{$height}" rx="14" fill="#fff" opacity="0.09"/>
    <rect x="{$x}" y="{$stripeY}" width="{$width}" height="26" fill="#ffffff" opacity="0.18"/>
    <text x="{$labelX}" y="{$labelY}" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="{$fontSize}" font-weight="800">{$escapedLabel}</text>
    <text x="{$labelX}" y="{$subLabelY}" fill="#17120d" font-family="Arial, Helvetica, sans-serif" font-size="13" font-weight="800">CATALOG SKU</text>
</g>
SVG;
    }

    private function scenarioNotes(array $scenario): string
    {
        return match ($scenario['key']) {
            'far_distance' => 'Products appear smaller, as if the merchandiser stood farther from the shelf.',
            'left_angle', 'right_angle_occluded' => 'Scene is skewed to mimic angled phone photos.',
            'single_item_left' => 'Only one catalog item is present; useful for low-count detection.',
            'high_count_24' => 'One catalog SKU is repeated twenty-four times across the shelf; useful for quantity-count regression.',
            'low_light' => 'Darker shelf lighting with partial occlusion.',
            'busy_mixed_shelf' => 'Catalog products appear alongside non-catalog products.',
            default => 'Clear shelf scene with expected catalog SKU counts.',
        };
    }

    private function colorFor(string $value): string
    {
        $hash = abs(crc32($value));
        $hue = $hash % 360;
        $saturation = 58 + ($hash % 18);
        $lightness = 36 + ($hash % 20);

        return sprintf('hsl(%d, %d%%, %d%%)', $hue, $saturation, $lightness);
    }

    private function labelFor(string $name): string
    {
        return Str::of($name)
            ->upper()
            ->replaceMatches('/[^A-Z0-9 ]/', ' ')
            ->squish()
            ->explode(' ')
            ->take(2)
            ->implode(' ');
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('/^[A-Z]:\\\\/i', $path) || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return base_path($path);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function implodeSvg(array $svg): string
    {
        return implode("\n", $svg);
    }

}
