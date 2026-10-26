<p class="dw-meta">Approved activity reports. KPI figures are reported results; approval of a report does not independently verify every measure.</p>
<div class="overflow-x-auto" tabindex="0" role="region" aria-label="Brand Activities Records">
<table class="text-left text-xs" style="min-width:960px;width:100%;table-layout:fixed">
<thead><tr><th style="width:21%">Activity & Accountability</th><th style="width:17%">Field Execution</th><th style="width:24%">Retail KPI Results</th><th style="width:20%">Insights & Next Actions</th><th style="width:18%">Analytics & Reporting</th></tr></thead>
<tbody>@forelse($section['rows'] as $row)
<tr>
<td style="vertical-align:top;overflow-wrap:break-word"><strong>{{ $row['title'] }}</strong><p>{{ $row['reference'] }} · {{ $row['status'] }}</p><dl class="mt-3 space-y-2">@foreach(['client'=>'Client','project'=>'Project','owner'=>'Accountable Owner','period'=>'Reporting Period','workstream'=>'Workstream','brand'=>'Brand','channel'=>'Channel','region'=>'Region'] as $field=>$label)@if(isset($row[$field]) && $row[$field]!=='')<div><dt class="dw-meta">{{ $label }}</dt><dd>{{ $row[$field] }}</dd></div>
@endif

@endforeach
</dl></td>
<td style="vertical-align:top"><dl class="space-y-3">@foreach(['outlets_planned'=>'Outlets Planned','outlets_visited'=>'Outlets Visited','retailers_supported'=>'Retailers Supported','merchandisers_deployed'=>'Merchandisers Deployed','promoters_deployed'=>'Promoters Deployed','training_completed'=>'Training Completions','assets_planned'=>'Assets Planned','assets_delivered'=>'Assets Delivered'] as $field=>$label)@if(isset($row[$field]))<div><dt class="dw-meta">{{ $label }}</dt><dd class="font-semibold">{{ number_format((float)$row[$field],0) }}</dd></div>
@endif

@endforeach
</dl>@unless(collect($row)->only(['outlets_planned','outlets_visited','retailers_supported','merchandisers_deployed','promoters_deployed','training_completed','assets_planned','assets_delivered'])->contains(fn($v)=>$v!==null))<span class="dw-meta">No field figures recorded.</span>@endunless</td>
<td style="vertical-align:top"><div class="space-y-4">@foreach(['coverage'=>'Coverage','osa'=>'On-Shelf Availability (OSA)','npd'=>'New Product Development (NPD)','must_have'=>'Must Have','perfect_store'=>'Perfect Store'] as $metric=>$label)
@if(isset($row[$metric.'_target_percent']) || isset($row[$metric.'_actual_percent']))
<div><strong>{{ $label }}</strong><p class="mt-1">Target: {{ isset($row[$metric.'_target_percent'])?rtrim(rtrim(number_format((float)$row[$metric.'_target_percent'],2),'0'),'.').'%':'Not Recorded' }}<br>Reported: {{ isset($row[$metric.'_actual_percent'])?rtrim(rtrim(number_format((float)$row[$metric.'_actual_percent'],2),'0'),'.').'%':'Not Recorded' }}</p>
@if(isset($row[$metric.'_previous_percent']))<p class="dw-meta">Previous: {{ $row[$metric.'_previous_percent'] }}% @if(isset($row[$metric.'_actual_percent']))· {{ sprintf('%+.2f',(float)$row[$metric.'_actual_percent']-(float)$row[$metric.'_previous_percent']) }} pp change
@endif
</p>
@endif

@if(isset($row[$metric.'_target_percent'],$row[$metric.'_actual_percent']))<p class="dw-meta">{{ sprintf('%+.2f',(float)$row[$metric.'_actual_percent']-(float)$row[$metric.'_target_percent']) }} pp vs target</p>
@endif
</div>

@endif

@endforeach

@unless(collect($row)->filter(fn($v,$k)=>str_ends_with($k,'_percent') && $v!==null)->isNotEmpty())<p class="dw-meta">No retail KPI figures recorded.</p>@endunless</div></td>
<td style="vertical-align:top;overflow-wrap:break-word"><dl class="space-y-3">@foreach(['insights'=>'Insights','bottleneck'=>'Bottleneck','next_action'=>'Next Action','action_due_on'=>'Action Due'] as $field=>$label)@if(isset($row[$field]) && $row[$field]!=='')<div><dt class="dw-meta">{{ $label }}</dt><dd style="white-space:pre-line">{{ $row[$field] }}</dd></div>
@endif

@endforeach
</dl></td>
<td style="vertical-align:top"><dl class="space-y-3">@foreach(['dashboard_refreshed_on'=>'Dashboard Refreshed','report_due_on'=>'Report Due','report_shared_on'=>'Report Shared','reach'=>'Reach','interactions'=>'Interactions','qualified_leads'=>'Qualified Leads','impressions'=>'Impressions'] as $field=>$label)@if(isset($row[$field]))<div><dt class="dw-meta">{{ $label }}</dt><dd>{{ $row[$field] }}</dd></div>
@endif

@endforeach
</dl></td>
</tr>
@empty<tr><td colspan="5">No new approved activity-register submissions for this period. Existing weekly reports are shown below.</td></tr>@endforelse</tbody></table></div>
