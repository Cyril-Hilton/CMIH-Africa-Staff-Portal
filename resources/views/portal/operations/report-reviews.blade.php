<section class="ops-entry" id="operations-reviews" aria-label="Operations submissions and reviews">
    <header class="br-table-heading"><h4>{{ $manager ? 'Team Entries & Reviews' : 'My Entries & Reviews' }}</h4><a class="ops-button" href="{{ $workspaceUrl() }}">Close</a></header>
    <p class="br-help">Pending entries await HOD review. Approved entries appear on the shared dashboard in their reporting period.</p>
    <nav class="br-actions my-3" aria-label="Filter submission reviews">
        @foreach(['pending'=>'Pending Review','returned'=>'Returned for Correction','published'=>'Published','all'=>'All Entries'] as $state=>$label)
            <a class="ops-button" href="{{ $workspaceUrl(['ops_review'=>0,'ops_review_state'=>$state]) }}" @if($reviewState === $state) aria-current="page" @endif>
                {{ $label }} · {{ $state === 'all' ? array_sum($reviewCounts) : ($reviewCounts[$state] ?? 0) }}
            </a>
        @endforeach
    </nav>
    @if(session('operations_saved_kind'))
        <div class="br-actions my-3">
            <a class="ops-button ops-primary" href="{{ $workspaceUrl(['ops_add'=>session('operations_saved_kind')]) }}">Add Another Entry</a>
        </div>
    @endif
    @forelse($submissions as $entry)
        @php
            $reviewSource = $reviewSources->get($entry->register_id);
            $reviewVersion = $reviewSource ? \App\Services\OperationsReporting::version($reviewSource) : null;
            $publishedChanged = $reviewVersion && !hash_equals($reviewVersion, $entry->source_version ?? '');
        @endphp
        <details class="ops-review" @if((int) request('ops_review') === $entry->id) open @endif>
            <summary><strong>#{{ $entry->id }} · {{ $entry->payload['title'] }}</strong> <span>{{ $entry->submitter?->name }} · {{ $labels[$entry->kind] }} · {{ $entry->payload['period_start'] }}</span> <span class="br-badge" data-ops-status="{{ $entry->state }}">{{ ucfirst($entry->state) }}</span></summary>
            @if(! empty($entry->payload['data']['collaborator_ids']))
                <div class="my-3"><strong class="mr-2 text-xs">Supporting Staff</strong>@include('portal.operations.collaborator-badges', ['people' => $reviewCollaborators->whereIn('id', $entry->payload['data']['collaborator_ids'])])</div>
            @endif
            @if($publishedChanged && in_array($entry->state, ['pending','returned'], true))
                <aside class="br-correction my-3" role="note">
                    <strong>The published report has changed since this submission.</strong>
                    <p>Compare the latest report below with the submitted details before approving or resubmitting. Approval applies the submitted details to the published report.</p>
                    <details class="ops-review">
                        <summary>Latest Published Report</summary>
                        <strong>{{ $reviewSource->title }}</strong>
                        @include('portal.operations.report-fields', ['detailFields' => array_intersect_key($reviewSource->data ?? [], $safeFields($entry->kind)) + ['client'=>$reviewSource->client, 'project'=>$reviewSource->project, 'status'=>$editStatus($reviewSource->status)]])
                    </details>
                </aside>
            @endif
            @if(filled($entry->review_note))<p class="br-correction">{{ $entry->review_note }}</p>@endif
            @if($entry->state === 'returned' && $entry->submitted_by === auth()->id())
                @include('portal.operations.report-form', ['formKind' => $entry->kind, 'formRecord' => null, 'formSubmission' => $entry])
            @else
                @include('portal.operations.report-fields', ['detailFields' => array_intersect_key($entry->payload['data'], $safeFields($entry->kind)) + ['client' => $entry->payload['client'], 'project' => $entry->payload['project'], 'status' => $editStatus($entry->payload['status'])]])
            @endif
            @if($manager && $entry->state === 'pending')
                <details class="ops-review"><summary>Edit &amp; Publish</summary>
                    @include('portal.operations.report-form', ['formKind' => $entry->kind, 'formRecord' => null, 'formSubmission' => $entry])
                </details>
                <form method="post" class="ops-form" action="{{ route('portal.operations.reporting.review', $entry) }}" data-no-ajax>
                    @csrf
                    <input type="hidden" name="revision" value="{{ $entry->revision }}">
                    @if($publishedChanged)
                        <input type="hidden" name="source_version" value="{{ $reviewVersion }}">
                        <label class="ops-narrative flex items-start gap-2">
                            <input type="checkbox" name="confirm_updated_report" value="1" class="!h-4 !w-4 !min-h-0">
                            <span>I reviewed this submission against the latest published report.</span>
                        </label>
                    @endif
                    <label class="ops-narrative">Correction note <small>(required when returning)</small><textarea rows="2" name="note" maxlength="3000"></textarea></label>
                    <div class="br-actions ops-narrative"><button name="decision" value="approve" class="ops-button ops-primary">Approve &amp; Publish</button><button name="decision" value="return" class="ops-button">Return for Correction</button></div>
                </form>
            @endif
        </details>
    @empty
        <p class="br-empty">No {{ $reviewState === 'all' ? 'saved' : $reviewState }} submissions to show.</p>
    @endforelse
    @include('portal.operations.report-pagination', ['page' => $submissions, 'paginationLabel' => 'Report reviews'])
</section>
