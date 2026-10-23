<section class="mt-5 min-w-0 rounded-2xl border border-sky-400/20 bg-sky-400/5 p-4" aria-label="Client Relations weekly progress">
<div class="flex flex-wrap items-center justify-between gap-3"><div><h4 class="text-lg font-semibold text-brand-white">Client Relations · Weekly Pipeline Progress</h4><p class="mt-1 text-xs text-brand-ash">Track projects, sales stages and next steps. Financial amounts are masked.</p></div>@if(\App\Services\ClientRelationsPipeline::member(auth()->user()))<a href="{{ route('portal.crm.index') }}" class="rounded-xl bg-brand-red px-4 py-3 text-xs font-semibold text-white">Open Client Relations Pipeline</a>@endif</div>
<div class="mt-4 overflow-x-auto rounded-xl border border-brand-white/10"><table class="w-full text-left text-xs text-brand-white"><thead><tr>
@foreach(['Week','Client','Project','Description','Team Member','Sales Stage','Progression Rate','Bottlenecks','Next Line of Action','Comments','Proposed Amount','PO Amount','Stage Probability','Weighted Forecast','Financial Visibility'] as $heading)
<th class="whitespace-nowrap border-b border-brand-white/20 px-3 py-3">{{ $heading }}</th>
@endforeach
</tr></thead><tbody>
@forelse($publicCrmProgress as $row)
<tr class="border-b border-brand-white/10 align-top" x-data="{ amounts: null, loading: false, error: '', timer: null,
hide() { this.amounts = null; clearTimeout(this.timer); },
async reveal() {
if (this.amounts) { this.hide(); return; }
if (this.loading) return;
this.loading = true; this.error = '';
try {
const response = await fetch(@js(route('portal.crm.reveal', $row)), { headers: { Accept: 'application/json' }, cache: 'no-store' });
if (!response.ok) throw new Error();
const values = await response.json();
if (!document.hidden) { this.amounts = values; this.timer = setTimeout(() => this.hide(), 120000); }
} catch (_) { this.error = 'Unable to reveal. Try again.'; } finally { this.loading = false; }
}, destroy() { this.hide(); } }" @visibilitychange.document="if(document.hidden) hide()" @pagehide.window="hide()">
<td class="px-3 py-3 whitespace-nowrap">{{ $row->week_start->format('d M Y') }}</td>
<td class="min-w-[9rem] px-3 py-3">{{ $row->client_name }}</td>
<td class="min-w-[10rem] px-3 py-3">{{ $row->project_name }}</td>
<td class="min-w-[12rem] max-w-xs whitespace-pre-line break-words px-3 py-3">{{ $row->description ?: 'Not Recorded' }}</td>
<td class="min-w-[9rem] px-3 py-3">{{ $row->owner?->name ?? 'Unassigned' }}</td>
<td class="whitespace-nowrap px-3 py-3">{{ config('crm_pipeline.stages.'.$row->stage.'.label') }}</td>
<td class="px-3 py-3">{{ $row->achievement_percent===null?'Not Measured':number_format((float)$row->achievement_percent,1).'%' }}</td>
<td class="min-w-[12rem] max-w-xs whitespace-pre-line break-words px-3 py-3">{{ $row->shared_bottlenecks ?: 'Not Recorded' }}</td>
<td class="min-w-[12rem] max-w-xs whitespace-pre-line break-words px-3 py-3">{{ $row->shared_next_action ?: config('crm_pipeline.public_actions.'.$row->public_action,'Not Recorded') }}</td>
<td class="min-w-[12rem] max-w-xs whitespace-pre-line break-words px-3 py-3">{{ $row->comments ?: 'Not Recorded' }}</td>
<td class="whitespace-nowrap px-3 py-3"><span x-text="amounts?.proposed_amount ?? @js($row->has_proposed_amount ? '******' : 'Not Recorded')">{{ $row->has_proposed_amount ? '******' : 'Not Recorded' }}</span></td>
<td class="min-w-[9rem] px-3 py-3"><span class="whitespace-nowrap" x-text="amounts?.po_amount ?? @js($row->has_po_amount ? '******' : 'Not Recorded')">{{ $row->has_po_amount ? '******' : 'Not Recorded' }}</span><span class="mt-1 block text-brand-ash">{{ ucwords(str_replace('_',' ',$row->po_state)) }}</span></td>
<td class="px-3 py-3">{{ $row->stage_percent===null?'Not Measured':number_format((float)$row->stage_percent,1).'%' }}</td>
<td class="whitespace-nowrap px-3 py-3"><span x-text="amounts?.weighted_forecast ?? @js($row->has_weighted_forecast ? '******' : 'Not Recorded')">{{ $row->has_weighted_forecast ? '******' : 'Not Recorded' }}</span></td>
<td class="min-w-[11rem] px-3 py-3">
@if(\App\Services\ClientRelationsPipeline::canReveal(auth()->user()))
<button type="button" @click="reveal()" :disabled="loading" :aria-expanded="!!amounts" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-sky-300/40 px-3 py-2 text-sky-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-300">
<svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
<span x-text="loading ? 'Loading…' : (amounts ? 'Hide Amounts' : 'Show Amounts')">Show Amounts</span></button>
<p x-show="amounts" style="display:none" class="mt-2 text-brand-ash">Hides after 2 minutes.</p><p x-show="error" x-text="error" role="alert" class="mt-2 text-red-300"></p>
@else<span class="text-brand-ash">Restricted</span>@endif
</td></tr>
@empty<tr><td colspan="15" class="p-5 text-brand-ash">No pipeline progress published for this selection.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $publicCrmProgress->links() }}</div>
<p class="mt-2 text-xs text-brand-ash">Progression = actual ÷ target. Stage probability estimates the likelihood of a sale.</p>
</section>
