<section class="panel" id="source-gap" tabindex="-1">
<p class="muted">KPI FOLLOW-UP</p><h2>{{ $sourceGap }}</h2>
@if($gapBriefs->isEmpty())
<p class="mt-3">There are no active Creative briefs assigned to this person. There is no brief review form to open for this reporting gap.</p>
<p class="muted mt-2">Portal maintenance tasks do not supply Creative brief quality or revision results. The reviewing manager needs to agree role-specific targets in Targets & Responsibilities below. Do not create a fictitious brief or quality score to clear this warning.</p>
<a class="primary mt-3" href="#kpi-targets">Open Targets & Responsibilities</a>
@else
<p class="muted mt-2">Open the original brief to submit work, review quality or request corrections. Review controls follow the existing permissions.</p>
<div class="scroll mt-3"><table><thead><tr><th>Brief</th><th>Stage</th><th>HOD deadline</th><th>Action</th></tr></thead><tbody>
@foreach($gapBriefs as $brief)<tr><td>{{ $brief->title }}</td><td>{{ config('creative.stages.'.$brief->stage,$brief->stage) }}</td><td>{{ $brief->internal_eta?->format('d M Y, H:i') ?? 'Not recorded' }}</td><td><a class="primary" href="{{ route('portal.creative',['request'=>$brief->id]) }}">Open brief & review</a></td></tr>@endforeach
</tbody></table></div>
@endif
</section>
