/* ============================================================
   EDL — JavaScript dùng chung (thuần, không thư viện ngoài)
   ============================================================ */
(function () {
    'use strict';

    var EDL = window.EDL || (window.EDL = {});

    /* ---------- Gọi API JSON (chuẩn trả về {success, data, message}) ---------- */
    EDL.request = function (url, options) {
        options = options || {};
        var headers = Object.assign({
            'X-CSRF-TOKEN': EDL.csrf,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }, options.headers || {});
        if (options.json !== undefined) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.json);
        }
        return fetch(url, { method: options.method || 'GET', headers: headers, body: options.body, credentials: 'same-origin' })
            .then(function (res) {
                if (res.status === 419) {
                    EDL.toast('Phiên làm việc đã hết hạn, vui lòng đăng nhập lại.', 'error');
                    setTimeout(function () { location.href = EDL.loginUrl; }, 1500);
                }
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (!res.ok && data.success === undefined) { data.success = false; }
                    if (!res.ok && !data.message) {
                        data.message = data.errors ? Object.values(data.errors).flat().join(' ') : 'Lỗi ' + res.status;
                    }
                    return data;
                });
            });
    };

    /* ---------- Toast ---------- */
    EDL.toast = function (message, type) {
        var wrap = document.getElementById('toast-wrap');
        if (!wrap) { alert(message); return; }
        var el = document.createElement('div');
        el.className = 'toast ' + (type || '');
        el.textContent = message;
        wrap.appendChild(el);
        setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 3500);
        setTimeout(function () { el.remove(); }, 3900);
    };

    /* ---------- Modal ---------- */
    EDL.modal = {
        open: function (id) {
            var el = document.getElementById(id);
            if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; var f = el.querySelector('[autofocus], input:not([type=hidden]), select, textarea'); if (f) { setTimeout(function () { f.focus(); }, 50); } }
        },
        close: function (id) {
            var el = document.getElementById(id);
            if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
        }
    };

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-modal-open]');
        if (opener) { e.preventDefault(); EDL.modal.open(opener.dataset.modalOpen); return; }
        var closer = e.target.closest('[data-modal-close]');
        if (closer) { e.preventDefault(); EDL.modal.close(closer.closest('.modal').id); return; }
        if (e.target.classList && e.target.classList.contains('modal')) { EDL.modal.close(e.target.id); }

        // Dropdown
        var toggle = e.target.closest('[data-dropdown-toggle]');
        document.querySelectorAll('.dropdown.open').forEach(function (d) {
            if (!toggle || d.id !== toggle.dataset.dropdownToggle) { d.classList.remove('open'); }
        });
        if (toggle) { document.getElementById(toggle.dataset.dropdownToggle).classList.toggle('open'); }

        // Mở / đóng sidebar trên màn hình nhỏ
        if (e.target.closest('[data-sidebar-toggle]')) { document.body.classList.toggle('sidebar-open'); }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.open').forEach(function (m) { EDL.modal.close(m.id); });
            document.querySelectorAll('.dropdown.open').forEach(function (d) { d.classList.remove('open'); });
        }
    });

    /* ---------- Xác nhận trước thao tác (form / link có data-confirm) ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        var msg = form.dataset.confirm || (e.submitter && e.submitter.dataset.confirm);
        if (msg && !confirm(msg)) { e.preventDefault(); }
    });
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-confirm]');
        if (link && !confirm(link.dataset.confirm)) { e.preventDefault(); }
    });

    /* ---------- Sidebar: gập / mở từng nhóm, nhớ trạng thái ---------- */
    document.querySelectorAll('.nav-section[data-nav-section]').forEach(function (section) {
        var key = 'edl-nav:' + section.dataset.navSection;
        var isActive = section.querySelector('.nav-link.active') !== null;
        try { if (!isActive && localStorage.getItem(key) === 'collapsed') { section.classList.add('collapsed'); } } catch (err) { }
        var t = section.querySelector('[data-nav-toggle]');
        if (!t) { return; }
        t.addEventListener('click', function () {
            section.classList.toggle('collapsed');
            try { localStorage.setItem(key, section.classList.contains('collapsed') ? 'collapsed' : 'expanded'); } catch (err) { }
        });
    });

    /* ---------- Tìm nhanh (Ctrl+K) ---------- */
    var qs = document.querySelector('[data-quick-search]');
    if (qs) {
        var input = qs.querySelector('input');
        var panel = qs.querySelector('.qs-panel');
        var timer = null, seq = 0, activeIdx = -1;

        var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };
        var mark = function (text, term) {
            var safe = esc(text);
            var words = term.split(/\s+/).filter(function (w) { return w.length >= 2; }).map(function (w) { return esc(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
            return words.length ? safe.replace(new RegExp('(' + words.join('|') + ')', 'gi'), '<mark>$1</mark>') : safe;
        };

        var render = function (data, term) {
            var html = '';
            if (data.documents && data.documents.length) {
                html += '<div class="qs-group">Tài liệu</div>';
                data.documents.forEach(function (d) {
                    html += '<a class="qs-item" href="' + esc(d.url) + '">' + d.icon + '<div><div class="qs-title">' + mark(d.title, term) + '</div><div class="qs-meta">' + mark(d.code, term) + ' · ' + mark(d.device, term) + (d.type ? ' · ' + esc(d.type) : '') + (d.in_content ? ' · <b>khớp trong nội dung</b>' : '') + '</div></div></a>';
                });
            }
            if (data.devices && data.devices.length) {
                html += '<div class="qs-group">Thiết bị</div>';
                data.devices.forEach(function (d) {
                    html += '<a class="qs-item" href="' + esc(d.url) + '"><span class="file-icon" style="--fi:#0f766e">TB</span><div><div class="qs-title">' + mark(d.code + ' — ' + d.name, term) + '</div><div class="qs-meta">' + esc(d.meta) + '</div></div></a>';
                });
            }
            if (!html) { html = '<div class="qs-empty">Không tìm thấy kết quả cho “' + esc(term) + '”</div>'; }
            html += '<a class="qs-footer" href="' + esc(qs.action) + '?q=' + encodeURIComponent(term) + '">Xem tất cả kết quả & lọc nâng cao →</a>';
            panel.innerHTML = html;
            panel.hidden = false;
            activeIdx = -1;
        };

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var term = input.value.trim();
            if (term.length < 2) { panel.hidden = true; return; }
            timer = setTimeout(function () {
                var my = ++seq;
                EDL.request(qs.dataset.url + '?q=' + encodeURIComponent(term), { headers: { 'X-EDL-Background': '1' } }).then(function (res) {
                    if (my === seq && res.success) { render(res.data, term); }
                });
            }, 220);
        });
        input.addEventListener('keydown', function (e) {
            var items = panel.querySelectorAll('.qs-item');
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) { return; }
                activeIdx = (activeIdx + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items.forEach(function (it, i) { it.classList.toggle('active', i === activeIdx); });
                items[activeIdx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && activeIdx >= 0 && items[activeIdx]) {
                e.preventDefault();
                location.href = items[activeIdx].href;
            } else if (e.key === 'Escape') { panel.hidden = true; input.blur(); }
        });
        document.addEventListener('click', function (e) { if (!qs.contains(e.target)) { panel.hidden = true; } });
        input.addEventListener('focus', function () { if (panel.innerHTML && input.value.trim().length >= 2) { panel.hidden = false; } });
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); input.focus(); input.select(); }
            if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { e.preventDefault(); input.focus(); }
        });
    }

    /* ---------- Chọn nhiều dòng + thanh thao tác hàng loạt ---------- */
    EDL.initBulk = function (root) {
        root = root || document;
        var master = root.querySelector('[data-check-all]');
        var bar = root.querySelector('.bulk-bar');
        var boxes = function () { return root.querySelectorAll('[data-check-item]'); };
        var refresh = function () {
            var checked = Array.prototype.filter.call(boxes(), function (b) { return b.checked; });
            if (bar) {
                bar.classList.toggle('show', checked.length > 0);
                var c = bar.querySelector('.bulk-count');
                if (c) { c.textContent = 'Đã chọn ' + checked.length; }
            }
            boxes().forEach(function (b) { var row = b.closest('tr, .doc-card'); if (row) { row.classList.toggle('selected', b.checked); } });
            if (master) { master.checked = checked.length > 0 && checked.length === boxes().length; master.indeterminate = checked.length > 0 && checked.length < boxes().length; }
        };
        if (master) { master.addEventListener('change', function () { boxes().forEach(function (b) { b.checked = master.checked; }); refresh(); }); }
        root.addEventListener('change', function (e) { if (e.target.matches('[data-check-item]')) { refresh(); } });
        EDL.selectedIds = function () { return Array.prototype.filter.call(boxes(), function (b) { return b.checked; }).map(function (b) { return b.value; }); };
        refresh();
    };
    if (document.querySelector('[data-check-item]')) { EDL.initBulk(); }

    /* ---------- Sao chép liên kết ---------- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn) { return; }
        e.preventDefault();
        var text = btn.dataset.copy;
        (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
            EDL.toast('Đã sao chép liên kết', 'success');
        }).catch(function () { prompt('Sao chép liên kết:', text); });
    });

    /* ---------- Tự đăng xuất phía trình duyệt khi không thao tác (đồng bộ với server) ---------- */
    if (EDL.idleMinutes) {
        var idleTimer;
        var resetIdle = function () {
            clearTimeout(idleTimer);
            idleTimer = setTimeout(function () { location.href = EDL.loginUrl; }, (EDL.idleMinutes * 60 + 5) * 1000);
        };
        ['click', 'keydown', 'mousemove', 'scroll'].forEach(function (ev) { document.addEventListener(ev, resetIdle, { passive: true }); });
        resetIdle();
    }

    /* ---------- Xem toàn màn hình: [data-fullscreen="#id"] — bấm lại hoặc Esc để thoát, phím F để bật / tắt ---------- */
    var fsTarget = function () { var b = document.querySelector('[data-fullscreen]'); return b ? document.querySelector(b.dataset.fullscreen) : null; };
    var toggleFullscreen = function (el) {
        if (!el) { return; }
        if (document.fullscreenElement) { document.exitFullscreen(); return; }
        (el.requestFullscreen ? el.requestFullscreen() : Promise.reject()).catch(function () {
            el.classList.toggle('is-pseudo-fullscreen'); // trình duyệt không hỗ trợ API → phủ kín cửa sổ
            document.body.classList.toggle('no-scroll', el.classList.contains('is-pseudo-fullscreen'));
        });
    };
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-fullscreen]');
        if (!btn) { return; }
        var el = document.querySelector(btn.dataset.fullscreen);
        if (el && el.classList.contains('is-pseudo-fullscreen')) { el.classList.remove('is-pseudo-fullscreen'); document.body.classList.remove('no-scroll'); return; }
        toggleFullscreen(el);
    });
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'f' || e.key === 'F') && !e.ctrlKey && !e.metaKey && !e.altKey && !/input|textarea|select/i.test(e.target.tagName) && !e.target.isContentEditable) {
            var el = fsTarget();
            if (el) { e.preventDefault(); toggleFullscreen(el); }
        }
        if (e.key === 'Escape') {
            document.querySelectorAll('.is-pseudo-fullscreen').forEach(function (el) { el.classList.remove('is-pseudo-fullscreen'); });
            document.body.classList.remove('no-scroll');
        }
    });
    document.addEventListener('fullscreenchange', function () {
        var on = !!document.fullscreenElement;
        document.querySelectorAll('[data-fullscreen]').forEach(function (b) {
            b.classList.toggle('is-on', on);
            b.title = on ? 'Thoát toàn màn hình (Esc)' : 'Xem toàn màn hình (phím F)';
            var label = b.querySelector('span');
            if (label) { label.textContent = on ? 'Thoát toàn màn hình' : 'Toàn màn hình'; }
        });
    });

    /* ---------- Dòng bảng bấm được (tr[data-href]) — bỏ qua khi bấm vào nút, ô chọn, link, form ---------- */
    var rowTarget = function (e) {
        var row = e.target.closest('tr[data-href]');
        if (!row || e.target.closest('a, button, input, select, textarea, label, form, .col-actions, .col-check')) { return null; }
        if (window.getSelection && String(window.getSelection()).length) { return null; }
        return row;
    };
    document.addEventListener('click', function (e) {
        var row = rowTarget(e);
        if (!row) { return; }
        if (e.ctrlKey || e.metaKey) { window.open(row.dataset.href, '_blank'); return; }
        location.href = row.dataset.href;
    });
    document.addEventListener('auxclick', function (e) {
        var row = e.button === 1 ? rowTarget(e) : null;
        if (row) { window.open(row.dataset.href, '_blank'); }
    });
})();
