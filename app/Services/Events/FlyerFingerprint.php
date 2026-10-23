<?php

namespace App\Services\Events;

class FlyerFingerprint
{
    public function scan(string $path): array
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'Flyer processing is temporarily unavailable. Please contact the portal administrator.']);
        }
        $size = @getimagesize($path);
        if (! $size || $size[0] * $size[1] > 12000000) {
            throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'Use a clear JPG, PNG or WebP image of no more than 12 megapixels.']);
        }
        $bytes = file_get_contents($path);
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw \Illuminate\Validation\ValidationException::withMessages(['image' => 'This image could not be read. Please export it as JPG, PNG or WebP.']);
        }
        $small = imagecreatetruecolor(17, 16);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 17, 16, imagesx($image), imagesy($image));
        $gray = [];
        $rgb = [0, 0, 0];
        for ($y = 0; $y < 16; $y++) {
            for ($x = 0; $x < 17; $x++) {
                $c = imagecolorat($small, $x, $y);
                $r = ($c >> 16) & 255;
                $g = ($c >> 8) & 255;
                $b = $c & 255;
                $rgb[0] += $r;
                $rgb[1] += $g;
                $rgb[2] += $b;
                $gray[$y][$x] = .299 * $r + .587 * $g + .114 * $b;
            }
        }
        $mean = array_sum(array_map('array_sum', $gray)) / 272;
        $d = '';
        $a = '';
        $variance = 0;
        for ($y = 0; $y < 16; $y++) {
            for ($x = 0; $x < 16; $x++) {
                $d .= $gray[$y][$x] > $gray[$y][$x + 1] ? '1' : '0';
                $a .= $gray[$y][$x] > $mean ? '1' : '0';
                $variance += ($gray[$y][$x] - $mean) ** 2;
            }
        }
        imagedestroy($small);
        imagedestroy($image);

        return ['sha256' => hash('sha256', $bytes), 'difference' => $d, 'average' => $a, 'colour' => array_map(fn ($v) => round($v / 272, 1), $rgb), 'contrast' => sqrt($variance / 256), 'aspect' => $size[0] / $size[1]];
    }

    public function similar(array $a, array $b): bool
    {
        if (empty($a['difference']) || empty($b['difference']) || min($a['contrast'] ?? 0, $b['contrast'] ?? 0) < 12) {
            return false;
        }
        if (abs($a['aspect'] - $b['aspect']) > .03) {
            return false;
        }
        $distance = fn ($x, $y) => strlen($x) === strlen($y) ? count(array_filter(str_split($x ^ $y), fn ($c) => ord($c) !== 0)) : 256;

        return $distance($a['difference'], $b['difference']) <= 5 && $distance($a['average'], $b['average']) <= 5 && max(array_map(fn ($i) => abs($a['colour'][$i] - $b['colour'][$i]), [0, 1, 2])) <= 12;
    }

    public function optimised(string $path): string
    {
        $image = imagecreatefromstring(file_get_contents($path));
        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, 1800 / max($w, $h));
        $out = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
        ob_start();
        imagewebp($out,null,84);
        $bytes = ob_get_clean();
        imagedestroy($out);
        imagedestroy($image);

        return $bytes;
    }
}
