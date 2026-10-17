@php use App\Support\Labels; $term = $term ?? ''; $showDevice = $showDevice ?? true; $snippets = $snippets ?? []; @endphp
@if ($documents->isEmpty())
    <div class="empty-state">{!! icon('search') !!}<p>{{ $emptyText ?? 'Không tìm thấy tài liệu phù hợp.' }}</p></div>
@else
    <div class="doc-grid">
        @foreach ($documents as $doc)
            <div class="doc-card">
                <input type="checkbox" class="doc-check" data-check-item value="{{ $doc->id }}">
                <div class="doc-card-top">
                    {!! file_icon($doc->extension, 'lg') !!}
                    <div style="min-width:0">
                        <a href="{{ route('documents.show', $doc->id) }}" class="doc-card-title">{!! highlight($doc->title, $term) !!}</a>
                        <div class="text-xs text-muted mono" style="margin-top:4px">{{ $doc->code }}</div>
                    </div>
                </div>
                <div class="doc-card-meta">
                    @if ($showDevice)<span>{!! icon('device', '', ['style' => 'width:12px;height:12px;vertical-align:-2px']) !!} {{ $doc->device_code }} — {{ $doc->device_name }}</span>@endif
                    @if ($doc->type_name)
                        <span class="type-pill"><i style="background:{{ $doc->type_color }}"></i>{{ $doc->type_name }}</span>
                    @endif
                    <span>{{ strtoupper($doc->extension) }} · {{ \App\Support\FileType::humanSize($doc->file_size) }}</span>
                </div>
                @if (! empty($snippets[$doc->id]))
                    <div class="snippet">{!! highlight($snippets[$doc->id], $term) !!}</div>
                @endif
                <div class="doc-card-foot">
                    <span class="badge {{ Labels::documentStatusBadge($doc->status) }}">{{ Labels::documentStatus($doc->status) }}</span>
                    @if (user_can('documents.download'))
                        <a href="{{ route('documents.download', $doc->id) }}" class="btn btn-xs btn-secondary" title="Tải xuống">{!! icon('download') !!}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
