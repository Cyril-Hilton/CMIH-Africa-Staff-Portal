<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\KeyDistributor;
use App\Models\Outlet;
use App\Models\Sku;
use App\Models\SiteContent;
use Illuminate\Database\Seeder;

class MerchandiserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Regions with Timezones
        $regions = [
            ['name' => 'ACCRA', 'timezone' => 'Africa/Accra'],
            ['name' => 'NORTH', 'timezone' => 'Africa/Accra'],
            ['name' => 'SOUTHWEST', 'timezone' => 'Africa/Accra'],
            ['name' => 'SOUTHEAST', 'timezone' => 'Africa/Accra'],
            ['name' => 'MIDGHANA', 'timezone' => 'Africa/Accra'],
            ['name' => 'NIGERIA', 'timezone' => 'Africa/Lagos'],
        ];

        foreach ($regions as $r) {
            Region::updateOrCreate(['name' => $r['name']], ['timezone' => $r['timezone']]);
        }

        // 2. Seed 29+ SKUs
        $skus = [
            'Malta Guinness Bottle 330ml',
            'Malta Guinness Can 330ml',
            'Guinness Stout FES Bottle 325ml',
            'Guinness Stout FES Can 330ml',
            'Guinness Smooth 330ml',
            'Alvaro Pear Bottle 330ml',
            'Alvaro Passion Bottle 330ml',
            'Alvaro Pineapple Bottle 330ml',
            'Smirnoff Ice Double Black 330ml',
            'Smirnoff Ice Red Bottle 300ml',
            'Orijin Bitters Bottle 200ml',
            'Orijin Bitters Bottle 750ml',
            'Orijin Ready-To-Drink Can 330ml',
            'Baileys Original Irish Cream 750ml',
            'Johnnie Walker Red Label 750ml',
            'Johnnie Walker Black Label 750ml',
            'Johnnie Walker Double Black 750ml',
            'Ciroc Ultra Premium Vodka 750ml',
            'Singleton 12 Years Single Malt 750ml',
            'Gordon\'s Dry Gin 750ml',
            'Smirnoff Red Vodka 750ml',
            'Captain Morgan Spiced Gold 750ml',
            'Malta Guinness Premium 330ml',
            'Lipton Ice Tea Peach 500ml',
            'Lipton Ice Tea Lemon 500ml',
            'Club Premium Lager 625ml',
            'Club Premium Lager 330ml',
            'Star Lager Beer 600ml',
            'Ruut Extra Premium Beer 625ml',
            'Gulder Extra Lager Beer 600ml'
        ];

        foreach ($skus as $s) {
            Sku::updateOrCreate(['name' => $s]);
        }

        // 3. Seed site setting for geofencing radius (default 30 meters)
        SiteContent::updateOrCreate(
            ['key' => 'merchandiser_radius'],
            [
                'value' => '30',
                'type' => 'text',
                'updated_by' => 1
            ]
        );

        // Fetch seeded structures to map KDs and Outlets
        $accraRegion = Region::where('name', 'ACCRA')->first();
        $nigeriaRegion = Region::where('name', 'NIGERIA')->first();

        // 4. Seed Key Distributors (KDs)
        $kds = [
            [
                'name' => 'Ama Jessica Dist',
                'region_id' => $accraRegion->id,
                'address' => 'Accra Central, Ghana',
                'latitude' => 5.55602,
                'longitude' => -0.20453,
            ],
            [
                'name' => 'Bisvel Ltd',
                'region_id' => $accraRegion->id,
                'address' => 'East Legon, Accra, Ghana',
                'latitude' => 5.63220,
                'longitude' => -0.16550,
            ],
            [
                'name' => 'Ecotel Logistics',
                'region_id' => $accraRegion->id,
                'address' => 'Spintex Road, Accra, Ghana',
                'latitude' => 5.61830,
                'longitude' => -0.09840,
            ],
            [
                'name' => 'Lagos Central KD',
                'region_id' => $nigeriaRegion->id,
                'address' => 'Ikeja, Lagos, Nigeria',
                'latitude' => 6.52440,
                'longitude' => 3.37920,
            ]
        ];

        foreach ($kds as $kd) {
            KeyDistributor::updateOrCreate(
                ['name' => $kd['name']],
                [
                    'region_id' => $kd['region_id'],
                    'address' => $kd['address'],
                    'latitude' => $kd['latitude'],
                    'longitude' => $kd['longitude'],
                ]
            );
        }

        // 5. Seed Outlets
        $amaJessica = KeyDistributor::where('name', 'Ama Jessica Dist')->first();
        $bisvel = KeyDistributor::where('name', 'Bisvel Ltd')->first();
        $lagosKd = KeyDistributor::where('name', 'Lagos Central KD')->first();

        $outlets = [
            [
                'name' => 'Accra Mall Shoprite',
                'code' => 'ACC-SR-001',
                'kd_id' => $amaJessica->id,
                'channel_type' => 'SSM',
                'address' => 'Accra Mall, Tetteh Quarshie Interchange, Accra',
                'latitude' => 5.61745,
                'longitude' => -0.16812, // Near Accra Mall Shoprite
            ],
            [
                'name' => 'A&C Mall Melcom',
                'code' => 'ACC-MC-002',
                'kd_id' => $bisvel->id,
                'channel_type' => 'SSM',
                'address' => 'A&C Mall, East Legon, Accra',
                'latitude' => 5.63412,
                'longitude' => -0.15024,
            ],
            [
                'name' => 'Osu High Street GT Store',
                'code' => 'OSU-GT-003',
                'kd_id' => $amaJessica->id,
                'channel_type' => 'GT',
                'address' => 'Osu Oxford Street, Accra',
                'latitude' => 5.55832,
                'longitude' => -0.18341,
            ],
            [
                'name' => 'Ikeja Shoprite Lagos',
                'code' => 'LOS-SR-004',
                'kd_id' => $lagosKd->id,
                'channel_type' => 'SSM',
                'address' => 'Ikeja City Mall, Lagos',
                'latitude' => 6.61189,
                'longitude' => 3.35245,
            ]
        ];

        foreach ($outlets as $o) {
            Outlet::updateOrCreate(
                ['code' => $o['code']],
                [
                    'name' => $o['name'],
                    'kd_id' => $o['kd_id'],
                    'channel_type' => $o['channel_type'],
                    'address' => $o['address'],
                    'latitude' => $o['latitude'],
                    'longitude' => $o['longitude'],
                ]
            );
        }
    }
}
