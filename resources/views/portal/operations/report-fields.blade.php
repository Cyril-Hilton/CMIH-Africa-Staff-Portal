<dl class="ops-detail">
    @foreach($detailFields as $field => $value)
        @continue($value === null || $value === '')
        <div><dt>{{ \Illuminate\Support\Str::headline($field) }}</dt>
        <dd class="weekly-rich-content">
            @if(is_bool($value)){{ $value ? 'Yes' : 'No' }}
            @elseif(is_scalar($value)){!! \App\Support\ReportRichText::html((string) $value) !!}@endif
        </dd></div>
    @endforeach
</dl>
