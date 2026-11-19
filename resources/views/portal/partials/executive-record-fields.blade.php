<dl class="mt-3 grid gap-3 border-t border-brand-white/10 pt-3 sm:grid-cols-2 xl:grid-cols-3">
    @foreach($fields as $label => $value)
        <div class="min-w-0 rounded-lg bg-brand-white/5 px-3 py-2">
            <dt class="text-xs font-semibold text-brand-ash">{{ $label }}</dt>
            <dd class="mt-1 break-words whitespace-pre-wrap text-sm">{{ match (true) {
                $value instanceof \DateTimeInterface => $value->format('d M Y'.($value->format('H:i') === '00:00' ? '' : ', H:i')),
                is_bool($value) => $value ? 'Yes' : 'No',
                is_array($value) => collect($value)->flatten()->filter(fn ($item) => is_scalar($item))->implode(' · '),
                filled($value) => html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], "\n", (string) $value)), ENT_QUOTES, 'UTF-8'),
                default => 'Setup needed',
            } }}</dd>
        </div>
    @endforeach
</dl>
