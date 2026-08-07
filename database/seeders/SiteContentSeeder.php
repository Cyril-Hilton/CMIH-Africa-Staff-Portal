<?php

namespace Database\Seeders;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $logoLight = public_path('images/logo/logo-light.png');
        $logoDark = public_path('images/logo/logo-dark.png');

        if (File::exists($logoLight)) {
            $path = 'content/logo-light.png';
            Storage::disk('public')->put($path, File::get($logoLight));
            SiteContent::updateOrCreate(
                ['key' => 'logo_light'],
                ['value' => $path, 'type' => 'image', 'updated_by' => null]
            );
        }

        if (File::exists($logoDark)) {
            $path = 'content/logo-dark.png';
            Storage::disk('public')->put($path, File::get($logoDark));
            SiteContent::updateOrCreate(
                ['key' => 'logo_dark'],
                ['value' => $path, 'type' => 'image', 'updated_by' => null]
            );
        }
    }
}
