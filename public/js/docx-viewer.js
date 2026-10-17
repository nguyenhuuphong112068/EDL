/* ============================================================
   EDL — Trình xem Word (.docx) nội bộ, dùng docx-preview + JSZip đặt sẵn trong public/vendor (offline)
   Dựng lại tài liệu gần như bản gốc: bảng, căn lề, phông, màu, ảnh, đầu / chân trang, ngắt trang.
   Phóng to / thu nhỏ, vừa chiều rộng; không dựng được thì hiện bản chữ đọc ở máy chủ.
   ============================================================ */
(function () {
    'use strict';

    var root = document.querySelector('[data-docx-viewer]');
    if (!root) { return; }

    var body = root.querySelector('.dv-body'), zoomLabel = root.querySelector('.pv-zoom'), pagesLabel = root.querySelector('.dv-pages');
    var wrapper = null, zoom = 1, mode = 'width', basePageWidth = 0;
    var MIN = 0.3, MAX = 3;

    var fallback = function (reason) {
        var tpl = root.querySelector('.dv-fallback');
        body.innerHTML = '';
        body.classList.add('dv-plain');
        if (tpl) { body.appendChild(tpl.content.cloneNode(true)); }
        pagesLabel.textContent = reason || 'Chỉ hiển thị phần chữ';
        root.querySelector('.pv-toolbar .pv-group:nth-child(2)').hidden = true;
    };

    if (!window.docx || !window.JSZip) { fallback('Thiếu thư viện xem Word — hiển thị phần chữ'); return; }

    // Khổ trang phổ biến nhất của tài liệu (đo một lần lúc chưa phóng to) — tài liệu dọc có vài trang
    // ngang thì vẫn vừa theo trang dọc, trang ngang cuộn ngang được
    var measure = function () {
        var count = {}, best = 0, bestCount = 0;
        wrapper.querySelectorAll('section.docx').forEach(function (s) {
            var w = s.offsetWidth;
            count[w] = (count[w] || 0) + 1;
            if (count[w] > bestCount) { bestCount = count[w]; best = w; }
        });
        basePageWidth = best || 794;
    };

    var apply = function () {
        if (!wrapper) { return; }
        // Vừa chiều rộng nhưng không phóng quá 150% (file không khai khổ giấy thì trang rất hẹp)
        if (mode === 'width') { zoom = Math.max(MIN, Math.min(1.5, (body.clientWidth - 40) / basePageWidth)); }
        wrapper.style.zoom = zoom;
        zoomLabel.textContent = Math.round(zoom * 100) + '%';
    };

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-dv]');
        if (!btn || !wrapper) { return; }
        switch (btn.dataset.dv) {
            case 'zoom-in': mode = 'custom'; zoom = Math.min(MAX, zoom * 1.15); break;
            case 'zoom-out': mode = 'custom'; zoom = Math.max(MIN, zoom / 1.15); break;
            case 'fit-width': mode = 'width'; break;
            case 'actual': mode = 'custom'; zoom = 1; break;
        }
        apply();
    });
    body.addEventListener('wheel', function (e) {
        if (!e.ctrlKey || !wrapper) { return; }
        e.preventDefault();
        mode = 'custom';
        zoom = Math.max(MIN, Math.min(MAX, zoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1)));
        apply();
    }, { passive: false });

    // Khung đổi kích thước (toàn màn hình, thu / phóng cửa sổ) → vừa lại chiều rộng
    var t;
    if (window.ResizeObserver) {
        new ResizeObserver(function () { clearTimeout(t); t = setTimeout(function () { if (mode === 'width') { apply(); } }, 100); }).observe(body);
    } else {
        window.addEventListener('resize', function () { if (mode === 'width') { apply(); } });
    }

    fetch(root.dataset.src, { credentials: 'same-origin' })
        .then(function (res) { if (!res.ok) { throw new Error('HTTP ' + res.status); } return res.blob(); })
        .then(function (blob) {
            body.innerHTML = '';
            return docx.renderAsync(blob, body, null, {
                className: 'docx',
                inWrapper: true,
                ignoreWidth: false,
                ignoreHeight: false,
                ignoreFonts: false,
                breakPages: true,
                ignoreLastRenderedPageBreak: false,
                experimental: true,
                trimXmlDeclaration: true,
                renderHeaders: true,
                renderFooters: true,
                renderFootnotes: true,
                renderEndnotes: true,
                useBase64URL: true
            });
        })
        .then(function () {
            wrapper = body.querySelector('.docx-wrapper');
            if (!wrapper) { throw new Error('empty'); }
            var n = wrapper.querySelectorAll('section.docx').length;
            pagesLabel.textContent = n + ' trang';
            measure();
            apply();
        })
        .catch(function () { fallback('Không dựng được bố cục — hiển thị phần chữ'); });
})();
