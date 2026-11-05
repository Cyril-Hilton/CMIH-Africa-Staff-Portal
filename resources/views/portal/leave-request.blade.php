<x-app-layout>
    <div class="mx-auto max-w-5xl space-y-5 p-4 sm:p-6">
        <a class="underline text-sm" href="{{ route('portal.leaves') }}">All Leave Requests</a>
        <h1 class="text-2xl font-bold">Leave Request #{{ $leave->id }}</h1>
        <p>{{ $leave->user?->name }} · {{ $leave->start_date->format('d M Y') }} – {{ $leave->end_date->format('d M Y') }}</p>
        <p>Status: <strong>{{ ucwords(str_replace('_', ' ', $leave->status)) }}</strong></p>
        @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        @if($canReview)
            @include('portal.partials.leave-review-card', ['approval' => $leave])
        @else
            @if($canReadReview && filled($leave->comments))
                <p class="whitespace-pre-wrap">{{ $leave->comments }}</p>
            @endif
            @include('portal.partials.leave-handover', ['handover' => $leave])
        @endif
        @if($isApplicant)
            <a class="inline-block underline" href="{{ route('portal.leaves', ['leave_focus' => $leave->id]) }}#my-leave-requests">Open this request in My Leave History</a>
        @endif
    </div>
</x-app-layout>
