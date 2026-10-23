<?php
namespace App\Support;
final class DisplayCurrency
{
    public static function unit(?string $unit): string { return in_array(strtoupper(trim($unit??'')),['GHS','GHC','GH₵','₵'],true)?'GH₵':($unit??''); }
    public static function amount($value, ?string $unit='GHS'): string { return $value===null?'Not Recorded':self::unit($unit).' '.number_format((float)$value,2); }
}
