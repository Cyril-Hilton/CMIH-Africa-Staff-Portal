<section class="panel" id="manager-reviews"><h2>Submission & Manager Review</h2><p class="muted mt-2">Approve or return by the end of the next working day. Weekends, company holidays and approved reviewer leave are excluded. An overdue manager response affects review turnaround; it does not change the staff submission timestamp. Financial and quality results remain unverified until approval.</p>
<div class="scroll mt-3"><table><thead><tr><th>Work / department</th><th>Staff submitted</th><th>Review due</th><th>Response</th><th>Responsibility</th><th>Action</th></tr></thead><tbody>
@forelse($reviewRows as $review)
@php
$source=match($review['type']){
 'task'=>route('portal.tasks.edit',$review['record_id']),
 'commitment'=>route('portal.performance.index',['view'=>'commitments','item'=>$review['record_id'],'from'=>$from,'to'=>$to]).'#commitment-'.$review['record_id'],
 'brands_submission'=>route('dashboard',['weekly_department'=>'brands_marketing','brands_submission'=>$review['record_id']]).'#staff-weekly-performance',
 'crm_submission'=>route('portal.crm.submissions'),
 'operations_submission'=>route('portal.operations',['ops_review'=>$review['record_id']]).'#operations-reviews',
 'operations_handover'=>route('portal.operations.handovers.index',['handover'=>$review['record_id']]),
 'warehouse_asset_suggestion'=>route('portal.assets.warehouse.index'),
 'hr_report'=>route('portal.hr-reporting.index'),
 'admin_weekly'=>route('portal.admin-weekly.index'),
 default=>route('portal.performance.index',['view'=>$review['type']==='register'?'registers':'kpis','department'=>$review['department'],'register_kind'=>$review['type']==='register'?($review['kind']??null):null,'from'=>$review['period_start']??$from,'to'=>$review['period_end']??$to]).($review['type']==='register'?'#register-'.$review['record_id']:'')};
@endphp
<tr id="review-{{ $review['event_id'] }}"><td>{{ $review['title'] }}<br><small>{{ config('performance.departments.'.$review['department'],$review['department']) }}</small></td><td>{{ $review['submitted_at']->format('d M Y, H:i') }}</td><td>{{ $review['due_at']->format('d M Y, H:i') }}</td><td>{{ $review['decided_at']?->format('d M Y, H:i') ?? 'Awaiting response' }}<br>{{ ucfirst($review['decision']??'') }}</td><td>{{ $review['reviewer_id'] ? (\App\Models\User::find($review['reviewer_id'])?->name ?? 'Former reviewer') : 'Reviewing manager needs assigning' }}<br><span class="tag">{{ $review['decided_at'] ? ($review['timely']?'Responded on time':'Responded late') : ($review['overdue']?'Manager review overdue':'Within review window') }}</span></td><td><a class="primary" href="{{ $source }}">Open source & review</a></td></tr>
@empty<tr><td colspan="6">No recorded review obligations in this period.</td></tr>@endforelse
</tbody></table></div></section>
