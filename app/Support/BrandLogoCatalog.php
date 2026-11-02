<?php
namespace App\Support;

use Illuminate\Support\Collection;

final class BrandLogoCatalog
{
    public static function entries(): array { return require config_path('brand_logo_catalog.php'); }
    private static function normalize(string $name): string { return strtolower(preg_replace('/[^a-z0-9]/i', '', $name)); }
    public static function key(string $name): string {
        $key=self::normalize($name);
        foreach(self::entries() as $id=>$entry) foreach(array_merge([$entry['name']],$entry['aliases']) as $alias) if(self::normalize($alias)===$key) return $id;
        return $key;
    }
    public static function find(string $name): ?array { return self::entries()[self::key($name)] ?? null; }
    public static function entry(string $name): ?array { return self::find($name); }
    public static function surface(string $name): string { return (self::find($name)['dark'] ?? false) ? '#151b23' : '#f5f5f2'; }
    public static function logo(string $name): ?string {
        $entry=self::find($name);
        if(!$entry || !is_file(public_path($entry['logo']))) return null;
        return asset($entry['logo']).'?v='.filemtime(public_path($entry['logo']));
    }
    // Deduplicate only the display collection. Linked database IDs are preserved.
    public static function forDisplay($brands): Collection {
        return collect($brands)->reject(fn($b)=>in_array(self::normalize(is_string($b)?$b:$b->name),['cmih','cmihafrica']))
            ->unique(fn($b)=>self::key(is_string($b)?$b:$b->name))->map(function($brand){
                if(is_string($brand)) return $brand;
                $copy=clone $brand;$entry=self::find($brand->name);if($entry)$copy->name=$entry['name'];return $copy;
            })->values();
    }
}
