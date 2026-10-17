/* ============================================================
   EDL — Trình xem PDF nội bộ (PDF.js đặt sẵn trong public/vendor/pdfjs — không cần internet)
   Thay trình xem PDF của trình duyệt: không có nút "Lưu vào Drive", có toàn màn hình,
   phóng to / thu nhỏ, vừa chiều rộng / vừa trang, xoay, chuyển trang, in, tải xuống.
   Trang được vẽ khi cuộn tới (lazy) để mở nhanh file nhiều trang.
   ============================================================ */
(function () {
    'use strict';

    var root = document.querySelector('[data-pdf-viewer]');
    if (!root || !window.pdfjsLib) { return; }

    var base = root.dataset.vendor;
    pdfjsLib.GlobalWorkerOptions.workerSrc = base + '/pdf.worker.min.js';

    var $ = function (sel) { return root.querySelector(sel); };
    var pagesEl = $('.pv-pages'), pageInput = $('.pv-page-input'), pageCount = $('.pv-page-count'), zoomLabel = $('.pv-zoom');
    var pdf = null, pages = [], scale = 1, mode = 'width', rotation = 0, current = 1, renderSeq = 0;
    var DPR = Math.min(window.devicePixelRatio || 1, 2);
    var MIN = 0.25, MAX = 4;

    var setStatus = function (text) { pagesEl.innerHTML = '<div class="pv-status">' + text + '</div>'; };

    /* ---------- Tính tỉ lệ theo chế độ vừa chiều rộng / vừa trang ---------- */
    var fitScale = function (m) {
        var vp = pages[0].page.getViewport({ scale: 1, rotation: rotation });
        var w = pagesEl.clientWidth - 32, h = pagesEl.clientHeight - 32;
        return m === 'page' ? Math.min(w / vp.width, h / vp.height) : w / vp.width;
    };

    /* ---------- Dựng khung trang (chưa vẽ) ---------- */
    var layout = function () {
        if (mode !== 'custom') { scale = Math.max(MIN, Math.min(MAX, fitScale(mode))); }
        zoomLabel.textContent = Math.round(scale * 100) + '%';
        renderSeq++;
        pages.forEach(function (p) {
            var vp = p.page.getViewport({ scale: scale, rotation: rotation });
            p.box.style.width = Math.floor(vp.width) + 'px';
            p.box.style.height = Math.floor(vp.height) + 'px';
            p.rendered = false;
            if (p.task) { p.task.cancel(); p.task = null; }
            p.canvas.width = 0; p.canvas.height = 0;
        });
        renderVisible();
    };

    var renderPage = function (p) {
        if (p.rendered) { return; }
        p.rendered = true;
        var seq = renderSeq;
        var vp = p.page.getViewport({ scale: scale * DPR, rotation: rotation });
        p.canvas.width = Math.floor(vp.width);
        p.canvas.height = Math.floor(vp.height);
        p.task = p.page.render({ canvasContext: p.canvas.getContext('2d'), viewport: vp });
        p.task.promise.then(function () { p.task = null; }).catch(function () { if (seq === renderSeq) { p.rendered = false; } });
    };

    // Vẽ các trang đang nằm trong (hoặc gần) vùng nhìn thấy
    var renderVisible = function () {
        var top = pagesEl.scrollTop, bottom = top + pagesEl.clientHeight, margin = pagesEl.clientHeight;
        var best = null, bestVisible = -1;
        pages.forEach(function (p, i) {
            var t = p.box.offsetTop, b = t + p.box.offsetHeight;
            if (b >= top - margin && t <= bottom + margin) { renderPage(p); }
            var visible = Math.min(b, bottom) - Math.max(t, top);
            if (visible > bestVisible) { bestVisible = visible; best = i + 1; }
        });
        if (best && best !== current) { current = best; pageInput.value = current; }
    };

    var goTo = function (n) {
        n = Math.max(1, Math.min(pages.length, n | 0));
        pagesEl.scrollTop = pages[n - 1].box.offsetTop - 12;
        current = n; pageInput.value = n;
    };

    var zoomTo = function (s, keepPage) {
        var page = current;
        mode = 'custom';
        scale = Math.max(MIN, Math.min(MAX, s));
        layout();
        if (keepPage !== false) { goTo(page); }
    };

    /* ---------- Thanh công cụ ---------- */
    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pv]');
        if (!btn || !pdf) { return; }
        var page = current;
        switch (btn.dataset.pv) {
            case 'prev': goTo(current - 1); break;
            case 'next': goTo(current + 1); break;
            case 'zoom-in': zoomTo(scale * 1.2); break;
            case 'zoom-out': zoomTo(scale / 1.2); break;
            case 'fit-width': mode = 'width'; layout(); goTo(page); break;
            case 'fit-page': mode = 'page'; layout(); goTo(page); break;
            case 'rotate': rotation = (rotation + 90) % 360; layout(); goTo(page); break;
            case 'print': printAll(btn); break;
        }
    });
    pageInput.addEventListener('change', function () { goTo(parseInt(pageInput.value, 10) || 1); });
    pagesEl.addEventListener('scroll', function () { window.requestAnimationFrame(renderVisible); }, { passive: true });

    // Ctrl + lăn chuột để phóng to / thu nhỏ
    pagesEl.addEventListener('wheel', function (e) {
        if (!e.ctrlKey || !pdf) { return; }
        e.preventDefault();
        zoomTo(scale * (e.deltaY < 0 ? 1.1 : 1 / 1.1));
    }, { passive: false });

    // Phím tắt khi đang xem (không áp khi đang gõ trong ô nhập)
    document.addEventListener('keydown', function (e) {
        if (!pdf || /input|textarea|select/i.test(e.target.tagName)) { return; }
        if (e.key === 'ArrowRight' || e.key === 'PageDown') { if (!root.contains(document.activeElement) && e.key === 'PageDown') { return; } goTo(current + 1); e.preventDefault(); }
        else if (e.key === 'ArrowLeft' || e.key === 'PageUp') { if (!root.contains(document.activeElement) && e.key === 'PageUp') { return; } goTo(current - 1); e.preventDefault(); }
        else if ((e.key === '+' || e.key === '=') && e.ctrlKey) { zoomTo(scale * 1.2); e.preventDefault(); }
        else if (e.key === '-' && e.ctrlKey) { zoomTo(scale / 1.2); e.preventDefault(); }
    });

    // Khung đổi kích thước (toàn màn hình, thu / phóng cửa sổ, xoay điện thoại…) thì vừa lại trang
    var resizeTimer, lastSize = '';
    var refit = function () {
        var size = pagesEl.clientWidth + 'x' + pagesEl.clientHeight;
        if (!pdf || !pages.length || size === lastSize) { return; }
        lastSize = size;
        if (mode !== 'custom') { var p = current; layout(); goTo(p); }
    };
    if (window.ResizeObserver) {
        new ResizeObserver(function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(refit, 120); }).observe(pagesEl);
    } else {
        window.addEventListener('resize', function () { clearTimeout(resizeTimer); resizeTimer = setTimeout(refit, 150); });
    }

    /* ---------- In: vẽ lại mọi trang thành ảnh trong cửa sổ riêng rồi in ---------- */
    var printAll = function (btn) {
        var win = window.open('', '_blank');
        if (!win) { if (window.EDL) { EDL.toast('Trình duyệt chặn cửa sổ in — hãy cho phép cửa sổ bật lên.', 'error'); } return; }
        win.document.write('<!doctype html><title>' + document.title + '</title><style>body{margin:0}img{display:block;width:100%;page-break-after:always}@page{margin:0}p{font:14px Arial;padding:20px}</style><p>Đang chuẩn bị bản in…</p>');
        btn.disabled = true;
        var imgs = [], i = 0;
        var next = function () {
            if (i >= pages.length) {
                win.document.body.innerHTML = imgs.join('');
                setTimeout(function () { win.focus(); win.print(); }, 300);
                btn.disabled = false;
                return;
            }
            var page = pages[i++].page, vp = page.getViewport({ scale: 2, rotation: rotation });
            var c = document.createElement('canvas');
            c.width = vp.width; c.height = vp.height;
            page.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise.then(function () {
                imgs.push('<img src="' + c.toDataURL('image/jpeg', 0.92) + '">');
                next();
            });
        };
        next();
    };

    /* ---------- Mở file ---------- */
    setStatus('Đang mở tài liệu…');
    pdfjsLib.getDocument({
        url: root.dataset.src,
        cMapUrl: base + '/cmaps/', cMapPacked: true,
        standardFontDataUrl: base + '/standard_fonts/',
        isEvalSupported: false,
        withCredentials: true
    }).promise.then(function (doc) {
        pdf = doc;
        pageCount.textContent = doc.numPages;
        pageInput.max = doc.numPages;
        pagesEl.innerHTML = '';
        var loads = [];
        for (var n = 1; n <= doc.numPages; n++) { loads.push(doc.getPage(n)); }
        return Promise.all(loads);
    }).then(function (list) {
        list.forEach(function (page, i) {
            var box = document.createElement('div');
            box.className = 'pv-page';
            box.dataset.page = i + 1;
            var canvas = document.createElement('canvas');
            box.appendChild(canvas);
            pagesEl.appendChild(box);
            pages.push({ page: page, box: box, canvas: canvas, rendered: false, task: null });
        });
        root.classList.add('is-ready');
        lastSize = pagesEl.clientWidth + 'x' + pagesEl.clientHeight;
        layout();
        goTo(1);
    }).catch(function (err) {
        setStatus('Không mở được file PDF' + (err && err.name === 'PasswordException' ? ' — file được đặt mật khẩu.' : '.') + ' Vui lòng tải file xuống để xem.');
    });
})();
