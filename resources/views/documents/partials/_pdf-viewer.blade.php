{{-- Trình xem PDF nội bộ (public/js/pdf-viewer.js + PDF.js trong public/vendor/pdfjs) — chạy offline, không có nút lưu vào Drive --}}
<div class="pdf-viewer" data-pdf-viewer data-src="{{ route('documents.preview', $doc->id) }}" data-vendor="{{ asset('vendor/pdfjs') }}">
    <div class="pv-toolbar">
        <div class="pv-group">
            <button type="button" class="pv-btn" data-pv="prev" title="Trang trước (←)">{!! icon('chevron-left') !!}</button>
            <input type="number" class="pv-page-input" value="1" min="1" aria-label="Trang">
            <span class="pv-sep">/</span><span class="pv-page-count">…</span>
            <button type="button" class="pv-btn" data-pv="next" title="Trang sau (→)">{!! icon('chevron-right') !!}</button>
        </div>
        <div class="pv-group">
            <button type="button" class="pv-btn" data-pv="zoom-out" title="Thu nhỏ (Ctrl −)">{!! icon('zoom-out') !!}</button>
            <span class="pv-zoom">100%</span>
            <button type="button" class="pv-btn" data-pv="zoom-in" title="Phóng to (Ctrl +)">{!! icon('zoom-in') !!}</button>
            <button type="button" class="pv-btn pv-text" data-pv="fit-width" title="Vừa chiều rộng">{!! icon('fit-width') !!}<span>Vừa rộng</span></button>
            <button type="button" class="pv-btn pv-text" data-pv="fit-page" title="Vừa cả trang">{!! icon('file') !!}<span>Vừa trang</span></button>
            <button type="button" class="pv-btn" data-pv="rotate" title="Xoay 90°">{!! icon('rotate') !!}</button>
        </div>
        <div class="pv-group">
            @if (user_can('documents.download'))
                <button type="button" class="pv-btn" data-pv="print" title="In tài liệu">{!! icon('printer') !!}</button>
                <a href="{{ route('documents.download', $doc->id) }}" class="pv-btn" title="Tải xuống">{!! icon('download') !!}</a>
            @endif
            <button type="button" class="pv-btn pv-fs" data-fullscreen="#doc-preview" title="Toàn màn hình (F)">{!! icon('maximize') !!}</button>
        </div>
    </div>
    <div class="pv-pages" tabindex="0"></div>
</div>

@push('scripts')
    <script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
    <script src="{{ asset('js/pdf-viewer.js') }}?v={{ config('edl.version') }}"></script>
@endpush
