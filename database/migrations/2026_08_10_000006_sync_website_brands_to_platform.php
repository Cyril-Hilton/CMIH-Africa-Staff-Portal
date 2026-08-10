<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();
        $lightBase = 'images/CMIH WEB ASSETS/BRAND LOGOS/LIGHT THEME/';
        $darkBase = 'images/CMIH WEB ASSETS/BRAND LOGOS/DARK THEME/';

        $brands = [
            ['name' => 'AXE', 'category' => 'Personal Care', 'logo' => $lightBase.'AXE.png', 'dark_logo' => $darkBase.'AXE.png'],
            ['name' => 'Baileys', 'category' => 'Beverage', 'logo' => $lightBase.'Baileys logo.png', 'dark_logo' => $lightBase.'Baileys logo.png'],
            ['name' => 'BII', 'category' => 'Beverage', 'logo' => $lightBase.'BII Logo.png', 'dark_logo' => $darkBase.'BII Logo LIGHT.png'],
            ['name' => 'Castle Milk Stout', 'category' => 'Beverage', 'logo' => $lightBase.'CM DARK.png', 'dark_logo' => $darkBase.'CM LIGHT.png'],
            ['name' => 'Diageo', 'category' => 'Beverage', 'logo' => $lightBase.'diageo.png', 'dark_logo' => $lightBase.'diageo.png'],
            ['name' => 'Dove', 'category' => 'Beauty and Personal Care', 'logo' => $lightBase.'Dove black.png', 'dark_logo' => $darkBase.'Dove white.png'],
            ['name' => 'Friesland', 'category' => 'Nutrition', 'logo' => $lightBase.'Friesland.png', 'dark_logo' => $lightBase.'Friesland.png'],
            ['name' => 'Gino', 'category' => 'Food', 'logo' => $lightBase.'Gino dark .png', 'dark_logo' => 'images/brand-platform/gino.png'],
            ['name' => "Gordon's", 'category' => 'Beverage', 'logo' => $lightBase."Gordon's dark.png", 'dark_logo' => $darkBase."Gordon's white.png"],
            ['name' => 'Guinness', 'category' => 'Beverage', 'logo' => $lightBase.'Guinness dark.png', 'dark_logo' => $darkBase.'Guinness light.png'],
            ['name' => 'Johnnie Walker', 'category' => 'Beverage', 'logo' => $lightBase.'JW_Logo_WithoutDescriptor_SmallSize_Black_cmyk.png', 'dark_logo' => $darkBase.'JW_Logo_WithoutDescriptor_SmallSize_white_cmyk.png'],
            ['name' => 'KPMG', 'category' => 'Professional Services', 'logo' => $lightBase.'KPMG.png', 'dark_logo' => $lightBase.'KPMG.png'],
            ['name' => 'Lush Hair', 'category' => 'Beauty', 'logo' => $lightBase.'Lush hair.png', 'dark_logo' => 'images/brand-platform/lush.png'],
            ['name' => 'Malta Guinness', 'category' => 'Beverage', 'logo' => $lightBase.'Malta guinness.png', 'dark_logo' => $darkBase.'Malta guinness light.png'],
            ['name' => 'OMO', 'category' => 'Home Care', 'logo' => 'images/brand-platform/omo.png', 'dark_logo' => 'images/brand-platform/omo.png'],
            ['name' => 'Orijin', 'category' => 'Beverage', 'logo' => $lightBase.'Orijin .png', 'dark_logo' => $lightBase.'Orijin .png'],
            ['name' => 'PEAK', 'category' => 'Nutrition', 'logo' => $lightBase.'PEAK LOGO.png', 'dark_logo' => $lightBase.'PEAK LOGO.png'],
            ['name' => 'Rexona', 'category' => 'Personal Care', 'logo' => $lightBase.'Rexona black.png', 'dark_logo' => $darkBase.'Rexona white.png'],
            ['name' => 'Smirnoff Ice', 'category' => 'Beverage', 'logo' => $lightBase.'Smirnoff ice.png', 'dark_logo' => $lightBase.'Smirnoff ice.png'],
            ['name' => 'Unilever', 'category' => 'FMCG', 'logo' => $lightBase.'Unilever black.png', 'dark_logo' => $darkBase.'Unilever white.png'],
        ];

        foreach ($brands as $brand) {
            $slug = Str::slug($brand['name']);
            $existing = DB::table('brands')
                ->where('slug', $slug)
                ->orWhere('name', $brand['name'])
                ->first();

            $payload = [
                'name' => $brand['name'],
                'slug' => $slug,
                'category' => $brand['category'],
                'headline' => $brand['name'].' activation intelligence',
                'description' => 'Activation planning, consumer capture, supporting staff execution, retail validation, reports and evidence for '.$brand['name'].'.',
                'primary_color' => '#e50914',
                'secondary_color' => '#ffffff',
                'accent_color' => '#facc15',
                'platform_status' => 'active',
                'logo_path' => $brand['logo'],
                'logo_dark_path' => $brand['dark_logo'],
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('brands')->where('id', $existing->id)->update([
                    'name' => $existing->name ?: $brand['name'],
                    'slug' => $existing->slug ?: $slug,
                    'category' => $existing->category ?: $brand['category'],
                    'headline' => $existing->headline ?: $payload['headline'],
                    'description' => $existing->description ?: $payload['description'],
                    'primary_color' => $existing->primary_color ?: $payload['primary_color'],
                    'secondary_color' => $existing->secondary_color ?: $payload['secondary_color'],
                    'accent_color' => $existing->accent_color ?: $payload['accent_color'],
                    'platform_status' => 'active',
                    'logo_path' => $brand['logo'],
                    'logo_dark_path' => $brand['dark_logo'],
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('brands')->insert(array_merge($payload, ['created_at' => $now]));
        }
    }

    public function down(): void
    {
        $slugs = [
            'axe',
            'baileys',
            'bii',
            'castle-milk-stout',
            'diageo',
            'friesland',
            'gordons',
            'johnnie-walker',
            'kpmg',
            'malta-guinness',
            'orijin',
            'peak',
            'smirnoff-ice',
            'unilever',
        ];

        DB::table('brands')->whereIn('slug', $slugs)->update([
            'platform_status' => 'inactive',
            'updated_at' => Carbon::now(),
        ]);
    }
};
