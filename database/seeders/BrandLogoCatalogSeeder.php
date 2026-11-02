<?php
namespace Database\Seeders;
use App\Models\Brand;
use App\Support\BrandLogoCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BrandLogoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $existing = Brand::all();
            foreach (BrandLogoCatalog::entries() as $key => $entry) {
                $matches = $existing->filter(fn ($brand) => BrandLogoCatalog::key($brand->name) === $key);
                if ($matches->isEmpty()) {
                    $brand = new Brand();
                    $brand->name = $entry['name'];
                    if (Schema::hasColumn('brands', 'slug')) $brand->slug = $key;
                    if (Schema::hasColumn('brands', 'platform_status')) $brand->platform_status = 'active';
                    $matches = collect([$brand]);
                }
                foreach ($matches as $brand) {
                    $brand->logo_path = $entry['logo'];
                    $brand->logo_dark_path = $entry['logo'];
                    if ($brand->isDirty()) $brand->save();
                }
            }
        });
    }
}
