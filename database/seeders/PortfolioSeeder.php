<?php

namespace Database\Seeders;

use App\Models\PortfolioAlbum;
use App\Models\PortfolioImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PortfolioSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure directories exist
        Storage::disk('public')->makeDirectory('portfolio/covers');
        Storage::disk('public')->makeDirectory('portfolio/gallery');

        $guinnessDir = public_path('images/portfolio/guinness');
        if (File::isDirectory($guinnessDir)) {
            $files = collect(File::files($guinnessDir))
                ->filter(fn ($file) => preg_match('/\.(jpg|jpeg|png|webp)$/i', $file->getFilename()))
                ->sortBy(fn ($file) => $file->getFilename())
                ->values();

            if ($files->isNotEmpty()) {
                $title = 'Guinness Influencer Soiree';

                if (! PortfolioAlbum::where('title', $title)->exists()) {
                    $coverSource = $files->first();
                    $coverFilename = 'portfolio/covers/guinness-influencer-soiree.' . $coverSource->getExtension();
                    Storage::disk('public')->put($coverFilename, File::get($coverSource->getPathname()));

                    $album = PortfolioAlbum::create([
                        'title' => $title,
                        'brand' => 'Guinness',
                        'cover_image' => $coverFilename,
                        'description' => 'Influencer-led engagement and premium brand storytelling for Guinness.',
                        'date' => now()->subDays(30),
                    ]);

                    foreach ($files as $index => $file) {
                        $galleryFilename = 'portfolio/gallery/' . $album->id . '/img-' . ($index + 1) . '.' . $file->getExtension();
                        Storage::disk('public')->put($galleryFilename, File::get($file->getPathname()));
                        $album->images()->create([
                            'image_path' => $galleryFilename,
                        ]);
                    }
                }

                return;
            }
        }

        $placeholders = [
            1 => ['title' => 'Brand Activation', 'brand' => 'Nairobi - POP Deployment'],
            2 => ['title' => 'Roadshow Momentum', 'brand' => 'Lagos - Road Shows'],
            3 => ['title' => 'Launch Experience', 'brand' => 'Accra - Event Management'],
            4 => ['title' => 'Retail Impact', 'brand' => 'Kigali - Shopper Marketing'],
            5 => ['title' => 'Campus Energy', 'brand' => 'Cape Town - Campus Activation'],
            6 => ['title' => 'Town Storming', 'brand' => 'Abuja - Community Engagement'],
        ];

        foreach ($placeholders as $index => $data) {
            $ext = file_exists(public_path("images/placeholder-portfolio-{$index}.png")) ? 'png' : 'jpg';
            $sourceFile = public_path("images/placeholder-portfolio-{$index}.{$ext}");
            $coverFilename = "portfolio/covers/placeholder-{$index}.{$ext}";
            
            // Simulate upload by copying file to storage if it exists source
            if (File::exists($sourceFile)) {
                Storage::disk('public')->put($coverFilename, File::get($sourceFile));
            } else {
                continue; // Skip if source doesn't exist
            }

            $album = PortfolioAlbum::create([
                'title' => $data['title'],
                'brand' => $data['brand'],
                'cover_image' => $coverFilename,
                'description' => 'This is a placeholder description for the ' . $data['title'] . ' campaign. Authentic data will be uploaded by the administrator.',
                'date' => now()->subDays($index * 10),
            ]);

            // Add some placeholder gallery images (using the same set for now)
            for ($i = 1; $i <= 3; $i++) {
                $galleryImgIndex = ($index + $i) % 6 ?: 6; // Rotate through images
                $ext = file_exists(public_path("images/placeholder-portfolio-{$galleryImgIndex}.png")) ? 'png' : 'jpg';
                $sourceGallery = public_path("images/placeholder-portfolio-{$galleryImgIndex}.{$ext}");
                $galleryFilename = "portfolio/gallery/{$album->id}/img-{$i}.{$ext}";

                if (File::exists($sourceGallery)) {
                    Storage::disk('public')->put($galleryFilename, File::get($sourceGallery));
                    $album->images()->create([
                        'image_path' => $galleryFilename
                    ]);
                }
            }
        }
    }
}
