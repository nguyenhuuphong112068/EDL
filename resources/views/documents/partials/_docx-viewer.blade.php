{{-- Trình xem Word nội bộ (public/js/docx-viewer.js + docx-preview, JSZip trong public/vendor) — chạy offline.
     Dựng lại bảng, định dạng, ảnh, đầu / chân trang và ngắt trang như bản gốc. --}}
<div class="pdf-viewer docx-viewer" data-docx-viewer data-src="{{ route('documents.preview', $doc->id) }}">
    <div class="pv-toolbar">
        <div class="pv-group">
            <span class="pv-info">{!! icon('file') !!} <span class="dv-pages">Đang mở…</span></span>
        </div>
        <div class="pv-group">
            <button type="button" class="pv-btn" data-dv="zoom-out" title="Thu nhỏ">{!! icon('zoom-out') !!}</button>
            <span class="pv-zoom">100%</span>
            <button type="button" class="pv-btn" data-dv="zoom-in" title="Phóng to">{!! icon('zoom-in') !!}</button>
            <button type="button" class="pv-btn pv-text" data-dv="fit-width" title="Vừa chiều rộng">{!! icon('fit-width') !!}<span>Vừa rộng</span></button>
            <button type="button" class="pv-btn pv-text" data-dv="actual" title="Đúng kích thước trang">{!! icon('file') !!}<span>100%</span></button>
        </div>
        <div class="pv-group">
            @if (user_can('documents.download'))
                <a href="{{ route('documents.download', $doc->id) }}" class="pv-btn" title="Tải xuống">{!! icon('download') !!}</a>
            @endif
            <button type="button" class="pv-btn pv-fs" data-fullscreen="#doc-preview" title="Toàn màn hình (F)">{!! icon('maximize') !!}</button>
        </div>
    </div>
    <div class="pv-pages dv-body" tabindex="0">
        <div class="pv-status">Đang dựng tài liệu Word…</div>
    </div>

    {{-- Dự phòng khi không dựng được (file hỏng, định dạng lạ): chỉ hiện phần chữ đọc ở máy chủ --}}
    <template class="dv-fallback">
        <div class="preview-doc">
            @forelse ($preview['paragraphs'] as $p)
                @if ($p['style'] === 'h')
                    <h3 style="margin:12px 0 8px">{{ $p['text'] }}</h3>
                @elseif ($p['style'] === 'b')
                    <p><b>{{ $p['text'] }}</b></p>
                @else
                    <p>{{ $p['text'] }}</p>
                @endif
            @empty
                <p class="text-muted">Không đọc được nội dung để xem trước. Vui lòng tải file xuống.</p>
            @endforelse
        </div>
    </template>
</div>

@push('scripts')
    <script src="{{ asset('vendor/docx-preview/jszip.min.js') }}"></script>
    <script src="{{ asset('vendor/docx-preview/docx-preview.min.js') }}"></script>
    <script src="{{ asset('js/docx-viewer.js') }}?v={{ config('edl.version') }}"></script>
@endpush
