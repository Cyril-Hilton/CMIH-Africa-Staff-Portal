<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $title }}</title>
<style>
    body{font-family:DejaVu Sans,sans-serif;color:#17243a;font-size:11px;margin:28px}
    header{border-bottom:3px solid #df2428;margin-bottom:20px;padding-bottom:12px}
    h1{font-size:21px;margin:6px 0}
    table{border-collapse:collapse;width:100%;table-layout:fixed}
    th,td{border-bottom:1px solid #dce4ee;padding:9px;vertical-align:top;text-align:left;word-wrap:break-word;white-space:pre-wrap}
    th{width:30%;background:#f0f5fa}
</style></head><body>
<header><strong>CMIH AFRICA</strong><h1>{{ $title }}</h1></header>
<table><tbody>
@foreach($fields as [$label, $value])
    <tr><th scope="row">{{ $label }}</th><td>{{ filled($value) ? $value : '—' }}</td></tr>
@endforeach
</tbody></table>
</body></html>
