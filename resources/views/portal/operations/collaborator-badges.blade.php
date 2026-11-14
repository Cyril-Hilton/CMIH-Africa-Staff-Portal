<div class="flex flex-wrap gap-1">
    @forelse($people as $person)
        <span class="br-badge !whitespace-normal">{{ $person->name }}</span>
    @empty
        <span aria-label="No supporting staff">—</span>
    @endforelse
</div>
