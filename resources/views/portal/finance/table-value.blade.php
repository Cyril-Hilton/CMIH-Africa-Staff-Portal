@php $value=$row->data[$field]??null;if($row->kind==='revenue'&&$field==='target'&&($row->data['revenue_type']??'monthly_total')!=='direct'){$value=$f_monthlyTargets->get($row->period_start->format('Y-m'))?->data['target']??$value;}$type=config('department_reporting.finance.'.$row->kind.'.'.$field); @endphp
@if($field==='period'){{ $row->period_start->format('d M Y') }}@if($row->period_start->ne($row->period_end))<p class="fd-muted">to {{ $row->period_end->format('d M Y') }}</p>@endif
@elseif(in_array($field,['title','client','project'])){{ $row->$field?:'—' }}@if($field==='title')<p class="fd-muted">{{ $row->reference }}</p>@endif
@elseif($field==='status'){{ ucwords(str_replace('_',' ',$row->status)) }}
@elseif(in_array($field,['achievement','revenue_status']))
@php $target=(float)($f_monthlyTargets->get($row->period_start->format('Y-m'))?->data['target']??$row->data['target']??0);$actual=(float)($row->data['actual']??0); @endphp
@if(($row->data['revenue_type']??'monthly_total')==='direct')<span class="fd-muted">Individual entry</span>
@elseif(!isset($row->data['actual']))<span class="fd-muted">Actual not recorded</span>
@elseif($target>0){{ $field==='achievement'?number_format($actual/$target*100,2).'%':($actual>=$target?'Target achieved':'Below target') }}
@else<span class="fd-muted">Target not recorded</span>@endif
@elseif($field==='gross_profit' && !isset($row->data['revenue'],$row->data['direct_cost'],$row->data['variable_cost']))<span class="fd-muted">Not measured</span>
@elseif($field==='gross_profit'){{ $fMoney((float)($row->data['revenue']??0)-(float)($row->data['direct_cost']??0)-(float)($row->data['variable_cost']??0)) }}
@elseif($value===null||$value==='')<span class="fd-muted">—</span>
@elseif($type==='money'){{ $fMoney($value) }}
@elseif($type==='date'){{ \Carbon\Carbon::parse($value)->format('d M Y') }}
@else<span class="fd-cell-text">{{ $value }}</span>@endif
