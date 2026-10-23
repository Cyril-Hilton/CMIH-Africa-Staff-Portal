<section class="panel">
<h2 class="text-lg font-semibold">KPI Definition Changes</h2>
<p class="muted mt-2">Reviewed changes apply from a future date. Earlier results keep their original definitions.</p>
@foreach($kpiRevisions as $revision)
<article class="mt-4 rounded-xl border border-brand-white/15 p-4">
<strong>{{ $revision->kpi->name }} · {{ ucfirst($revision->status) }}</strong>
<p class="muted mt-2">Effective {{ $revision->effective_on->format('d M Y') }} · {{ $revision->reason }}</p>
<details class="mt-3"><summary class="cursor-pointer">Proposed Changes</summary><dl class="mt-2 grid gap-3 sm:grid-cols-2">@foreach($revision->changes as $field=>$value)<div><dt class="muted">{{ ucwords(str_replace('_',' ',$field)) }}</dt><dd class="mt-1 whitespace-pre-line">{{ $value }}</dd></div>@endforeach</dl></details>
@if($revision->status==='pending' && (int)$revision->reviewer_id===$user->id && !in_array($user->id,[(int)$revision->submitted_by,(int)$revision->kpi->owner_id],true))
<form method="POST" action="{{ route('portal.performance.kpi.revisions.review',$revision) }}" class="mt-3 flex flex-wrap gap-3">@csrf
<label class="block flex-1">Review Notes<input name="reason" required class="mt-1 w-full rounded-lg bg-brand-black p-2"></label>
<button name="decision" value="approve">Approve New Version</button><button name="decision" value="return">Return for Correction</button></form>
@endif
</article>
@endforeach
@if($manages)
@foreach($kpis->whereNull('effective_until') as $definition)
@if((int)$definition->reviewer_id!==$user->id)
<details class="mt-4 rounded-xl border border-brand-white/15 p-4"><summary class="cursor-pointer font-semibold">Revise {{ $definition->name }} · v{{ $definition->version }}</summary>
<form method="POST" action="{{ route('portal.performance.kpi.revisions.store',$definition) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
@foreach(['definition','formula','source','required_action'] as $field)
@include('portal.performance.field',['name'=>$field,'options'=>null,'type'=>'textarea','value'=>$definition->{$field},'required'=>true,'label'=>ucwords(str_replace('_',' ',$field))])
@endforeach
@foreach(['green_threshold','amber_threshold','escalation_days'] as $field)
@include('portal.performance.field',['name'=>$field,'options'=>null,'type'=>'number','value'=>$definition->{$field},'required'=>true,'label'=>ucwords(str_replace('_',' ',$field))])
@endforeach
@include('portal.performance.field',['name'=>'effective_on','options'=>null,'type'=>'date','value'=>null,'required'=>true,'label'=>'Effective From'])
@include('portal.performance.field',['name'=>'reason','options'=>null,'type'=>'textarea','value'=>null,'required'=>true,'label'=>'Reason for Change'])
<button type="submit">Submit for Independent Review</button>
</form></details>
@endif
@endforeach
@endif
</section>
