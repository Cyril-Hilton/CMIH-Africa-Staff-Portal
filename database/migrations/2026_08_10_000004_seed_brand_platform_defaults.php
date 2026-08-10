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

        $brands = [
            [
                'name' => 'Rexona',
                'category' => 'Personal Care',
                'headline' => 'Stay fresh. Keep moving.',
                'description' => 'Movement-led consumer sampling, trial and retail conversion experiences.',
                'activation_name' => 'Campus and Gym Sampling Activation 2026',
                'activation_type' => 'sampling',
                'activation_description' => 'Sampling activation across selected campuses and gym centres with consumer capture and field reporting.',
                'primary_color' => '#00656c',
                'secondary_color' => '#18e7ef',
                'accent_color' => '#ff2ba6',
                'logo_path' => 'images/brand-platform/rexona.png',
                'logo_dark_path' => 'images/brand-platform/rexona.png',
            ],
            [
                'name' => 'Guinness',
                'category' => 'Beverage',
                'headline' => 'Good things come together.',
                'description' => 'Premium social experiences, selected bars and event partnership sales activations.',
                'activation_name' => 'Night Trade Sales Activation',
                'activation_type' => 'sales',
                'activation_description' => 'Selected bar, event partnership, bottle-sales tracking and reward fulfilment activation.',
                'primary_color' => '#17130e',
                'secondary_color' => '#d7b45a',
                'accent_color' => '#f2e7d0',
                'logo_path' => 'images/brand-platform/guinness.png',
                'logo_dark_path' => 'images/brand-platform/guinness.png',
            ],
            [
                'name' => 'Gino',
                'category' => 'Food',
                'headline' => 'Flavour lives here.',
                'description' => 'Market and shopper experiences built around product trial and conversion.',
                'activation_name' => 'Flavour Market Tour',
                'activation_type' => 'sampling',
                'activation_description' => 'Market sampling, shopper profiling and retailer conversion.',
                'primary_color' => '#cf2920',
                'secondary_color' => '#f2c94c',
                'accent_color' => '#159447',
                'logo_path' => 'images/brand-platform/gino.png',
                'logo_dark_path' => 'images/brand-platform/gino.png',
            ],
            [
                'name' => 'OMO',
                'category' => 'Home Care',
                'headline' => 'Get out. Get dirty. Learn.',
                'description' => 'Product demonstrations and retail trial experiences for family and home-care shoppers.',
                'activation_name' => 'Clean Futures Tour',
                'activation_type' => 'sampling',
                'activation_description' => 'Retail demonstrations, product trial and family engagement.',
                'primary_color' => '#1d4ed8',
                'secondary_color' => '#ef4444',
                'accent_color' => '#f8fafc',
                'logo_path' => 'images/brand-platform/omo.png',
                'logo_dark_path' => 'images/brand-platform/omo.png',
            ],
            [
                'name' => 'Lush Hair',
                'category' => 'Beauty',
                'headline' => 'Your hair. Your crown. Your power.',
                'description' => 'Festival, campus and salon experiences around confidence and self-expression.',
                'activation_name' => 'Campus Festival',
                'activation_type' => 'sampling',
                'activation_description' => 'Hair trial, games, photo moments and product conversion.',
                'primary_color' => '#84216b',
                'secondary_color' => '#f973bd',
                'accent_color' => '#fde68a',
                'logo_path' => 'images/brand-platform/lush.png',
                'logo_dark_path' => 'images/brand-platform/lush.png',
            ],
            [
                'name' => 'Dove',
                'category' => 'Beauty and Personal Care',
                'headline' => 'Care that feels real.',
                'description' => 'Human-centred beauty, confidence, care and product trial experiences.',
                'activation_name' => 'Real Beauty Pop-up',
                'activation_type' => 'sampling',
                'activation_description' => 'Product trial, stories, registration and engagement.',
                'primary_color' => '#0f172a',
                'secondary_color' => '#fbbf24',
                'accent_color' => '#e0f2fe',
                'logo_path' => 'images/brand-platform/dove.png',
                'logo_dark_path' => 'images/brand-platform/dove.png',
            ],
            [
                'name' => 'Ovaltine',
                'category' => 'Nutrition',
                'headline' => 'Power up. Play on.',
                'description' => 'School and family energy activations with sampling and participation.',
                'activation_name' => 'Energy Schools Tour',
                'activation_type' => 'sampling',
                'activation_description' => 'School sampling, games, product education and family conversion.',
                'primary_color' => '#f97316',
                'secondary_color' => '#2563eb',
                'accent_color' => '#facc15',
                'logo_path' => 'images/brand-platform/ovaltine.png',
                'logo_dark_path' => 'images/brand-platform/ovaltine.png',
            ],
            [
                'name' => 'MTN',
                'category' => 'Telecommunications',
                'headline' => 'Connect home. Go further.',
                'description' => 'Fibre broadband acquisition through coverage checks, lead qualification and sales follow-up.',
                'activation_name' => 'Fibre Broadband Connect',
                'activation_type' => 'sales',
                'activation_description' => 'Neighbourhood coverage checks, qualified leads and installation intent.',
                'primary_color' => '#facc15',
                'secondary_color' => '#111827',
                'accent_color' => '#ffffff',
                'logo_path' => 'images/brand-platform/mtn.png',
                'logo_dark_path' => 'images/brand-platform/mtn.png',
            ],
        ];

        foreach ($brands as $brand) {
            $slug = Str::slug($brand['name']);
            $existing = DB::table('brands')
                ->where('slug', $slug)
                ->orWhere('name', $brand['name'])
                ->first();

            $payload = array_merge($brand, [
                'slug' => $slug,
                'platform_status' => 'active',
                'updated_at' => $now,
            ]);

            if ($existing) {
                DB::table('brands')->where('id', $existing->id)->update($payload);
                $brandId = $existing->id;
            } else {
                $brandId = DB::table('brands')->insertGetId(array_merge($payload, ['created_at' => $now]));
            }

            if (! DB::table('brand_activations')->where('brand_id', $brandId)->where('name', $brand['activation_name'])->exists()) {
                DB::table('brand_activations')->insert([
                    'brand_id' => $brandId,
                    'name' => $brand['activation_name'],
                    'activation_type' => $brand['activation_type'],
                    'status' => 'live',
                    'starts_at' => null,
                    'ends_at' => null,
                    'target_reach' => 20000,
                    'actual_reach' => 0,
                    'locations' => json_encode(['Accra', 'Kumasi', 'Takoradi']),
                    'description' => $brand['activation_description'],
                    'client_share_token' => Str::random(40),
                    'created_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $slugs = ['rexona', 'guinness', 'gino', 'omo', 'lush-hair', 'dove', 'ovaltine', 'mtn'];

        DB::table('brands')->whereIn('slug', $slugs)->update([
            'platform_status' => 'inactive',
            'updated_at' => Carbon::now(),
        ]);
    }
};
