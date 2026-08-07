<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $lightDir = public_path('images/CMIH WEB ASSETS/BRAND LOGOS/LIGHT THEME');
        $darkDir = public_path('images/CMIH WEB ASSETS/BRAND LOGOS/DARK THEME');

        if (! File::isDirectory($lightDir)) {
            return;
        }

        $lightFiles = collect(File::files($lightDir))
            ->filter(fn ($file) => $this->isImage($file->getFilename()))
            ->values();

        if ($lightFiles->isEmpty()) {
            return;
        }

        $darkMap = collect();
        if (File::isDirectory($darkDir)) {
            $darkMap = collect(File::files($darkDir))
                ->filter(fn ($file) => $this->isImage($file->getFilename()))
                ->mapWithKeys(fn ($file) => [$this->normalizeKey($file->getFilename()) => $file->getPathname()]);
        }

        $index = 1;
        foreach ($lightFiles as $file) {
            $lightPath = $file->getPathname();
            $darkPath = $darkMap[$this->normalizeKey($file->getFilename())] ?? null;

            $name = $this->prettyName($file->getFilename());
            if ($name === '') {
                $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            }

            $slug = Str::slug($name);
            if ($slug === '') {
                $slug = 'brand-' . $index;
            }

            $lightTarget = 'brands/' . $slug . '-light.' . $file->getExtension();
            Storage::disk('public')->put($lightTarget, File::get($lightPath));

            if ($darkPath) {
                $darkExt = pathinfo($darkPath, PATHINFO_EXTENSION);
                $darkTarget = 'brands/' . $slug . '-dark.' . $darkExt;
                Storage::disk('public')->put($darkTarget, File::get($darkPath));
            } else {
                $darkTarget = $lightTarget;
            }

            Brand::updateOrCreate(
                ['name' => $name],
                [
                    'logo_path' => $lightTarget,
                    'logo_dark_path' => $darkTarget,
                ]
            );

            $index++;
        }
    }

    private function isImage(string $filename): bool
    {
        return (bool) preg_match('/\.(png|jpg|jpeg|webp|gif)$/i', $filename);
    }

    private function normalizeKey(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = str_replace(['_', '-'], ' ', $base);
        $base = preg_replace('/\b(dark|light|black|white)\b/i', '', $base);
        $base = preg_replace('/\s+/', ' ', $base);
        return strtolower(trim($base));
    }

    private function prettyName(string $filename): string
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $base = str_replace(['_', '-'], ' ', $base);
        $base = preg_replace('/\b(logo|dark|light|black|white|cmyk|smallsize|withoutdescriptor)\b/i', '', $base);
        $base = preg_replace('/\s+/', ' ', $base);
        return trim($base);
    }
}
