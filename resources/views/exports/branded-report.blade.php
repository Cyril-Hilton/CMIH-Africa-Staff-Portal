<!doctype html><html><head><meta charset="UTF-8"><style>
@page { margin: 64px 28px 40px; } body {font-family:DejaVu Sans,sans-serif;font-size:9px;color:#17243a;} header {position:fixed;top:-48px;left:0;right:0;height:36px;border-bottom:3px solid #df2428;} header strong{font-size:17px;} header span{float:right;font-size:9px;color:#63758a;} footer{position:fixed;bottom:-24px;left:0;right:0;color:#63758a;font-size:8px;} footer .page:after{content:counter(page);} table{width:100%;border-collapse:collapse;table-layout:fixed;} td,th{padding:7px 6px;border-bottom:1px solid #dce4ee;vertical-align:top;overflow-wrap:break-word;word-wrap:break-word;} th{background:#17243a;color:white;text-align:left;font-weight:bold;} tr:nth-child(even) td{background:#f0f5fa;} tr{page-break-inside:avoid;} thead{display:table-header-group;} .metadata td{background:#fff;font-weight:bold;padding:8px 6px;} h1{font-size:15px;margin:0 0 12px;}
</style></head><body><header><strong>CMIH AFRICA</strong><span>Performance & Accountability Report</span></header><footer>Confidential · Generated {{ now()->format('d M Y, H:i') }}<span style="float:right">Page <span class="page"></span></span></footer>
@php
$knownHeaders=collect($rows)->contains(fn($row)=>in_array($row[0]??'', ['KPI','Commitment ID']));
$metadata=[]; $sections=[]; $notes=[]; $section=null;
foreach($rows as $row) {
    if (!$row) continue;
    if (($row[0]??'')==='Methodology') { $notes[]=implode(' ',array_slice($row,1)); continue; }
    if(count($row)>=3 && ((!$knownHeaders && $section===null && !in_array($row[0]??'', ['Reporting period','Reporting Period'])) || in_array($row[0]??'', ['KPI','Commitment ID']))) {
        $sections[]=['headings'=>$row,'rows'=>[]]; $section=count($sections)-1;
    } elseif($section!==null) { $sections[$section]['rows'][]=$row; }
    else { $metadata[]=$row; }
}
@endphp
@if($metadata)<table class="metadata"><tbody>@foreach($metadata as $row)@if(count($row)===1)<tr><td colspan="2" style="font-size:14px">{{ $row[0] }}</td></tr>@else<tr><td style="width:18%">{{ $row[0] }}</td><td>{{ implode(' · ',array_slice($row,1)) }}</td></tr>@endif
@endforeach</tbody></table>@endif
@foreach($sections as $section)<table style="margin-top:18px"><thead><tr>@foreach($section['headings'] as $value)<th>{{ $value }}</th>@endforeach</tr></thead><tbody>@foreach($section['rows'] as $row)<tr>@foreach(array_pad($row,count($section['headings']),'') as $value)<td>{{ $value===null?'Not Measured':$value }}</td>@endforeach</tr>@endforeach</tbody></table>@endforeach
@foreach($notes as $note)<p style="margin-top:16px;font-size:8px;color:#63758a">{{ $note }}</p>@endforeach
</body></html>