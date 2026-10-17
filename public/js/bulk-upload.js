/* ============================================================
   EDL — Tải lên hàng loạt
   1) Chọn / kéo thả nhiều file → mỗi file tải ngay lên vùng tạm (3 file song song, có tiến trình)
   2) Server trả gợi ý (thiết bị, loại tài liệu) + cảnh báo trùng → điền vào lưới
   3) Người dùng chỉnh từng dòng (✕ để bỏ file) → "Lưu tất cả" gửi một lần, báo kết quả từng dòng
   Mở từ một thiết bị (C.fixedDevice) thì mọi file vào thiết bị đó, không có cột chọn thiết bị.
   ============================================================ */
(function () {
    'use strict';
    var C = window.EDL_UPLOAD;
    var rows = [];          // {key, file, token, state, el, data}
    var queue = [], active = 0, seq = 0;
    var CONCURRENCY = 3;

    var $ = function (sel, root) { return (root || document).querySelector(sel); };
    var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };
    var human = function (b) { var u = ['B', 'KB', 'MB', 'GB']; var i = 0; while (b >= 1024 && i < 3) { b /= 1024; i++; } return (i ? b.toFixed(1) : b) + ' ' + u[i]; };

    var dropzone = $('#dropzone'), input = $('#file-input'), tbody = $('#upload-rows'), card = $('#upload-card');

    var options = function (list, selected, empty) {
        var html = empty !== undefined ? '<option value="">' + esc(empty) + '</option>' : '';
        list.forEach(function (o) { html += '<option value="' + o.id + '"' + (String(o.id) === String(selected) ? ' selected' : '') + '>' + esc(o.label) + '</option>'; });
        return html;
    };
    var mapOptions = function (map, selected) {
        return Object.keys(map).map(function (k) { return '<option value="' + k + '"' + (k === selected ? ' selected' : '') + '>' + esc(map[k]) + '</option>'; }).join('');
    };

    /* ---------- Thêm file ---------- */
    var addFiles = function (fileList) {
        var files = Array.prototype.slice.call(fileList);
        var pending = rows.filter(function (r) { return r.state !== 'saved'; }).length;
        if (pending + files.length > C.maxFiles) {
            EDL.toast('Mỗi lượt tối đa ' + C.maxFiles + ' file — đã bỏ bớt file thừa.', 'error');
            files = files.slice(0, Math.max(0, C.maxFiles - pending));
        }
        files.forEach(function (file) {
            var ext = (file.name.split('.').pop() || '').toLowerCase();
            var row = { key: 'r' + (++seq), file: file, token: null, state: 'queued', data: {} };
            rows.push(row);
            renderRow(row);
            if (C.allowed.indexOf(ext) === -1 || file.name.indexOf('.') === -1) {
                setError(row, 'Định dạng .' + esc(ext) + ' không được phép lưu.');
                return;
            }
            if (file.size > C.maxBytes) {
                setError(row, 'File vượt quá dung lượng cho phép (' + human(C.maxBytes) + ').');
                return;
            }
            queue.push(row);
        });
        card.hidden = false;
        dropzone.classList.add('is-compact');
        pump();
        summary();
    };

    var pump = function () {
        while (active < CONCURRENCY && queue.length) {
            upload(queue.shift());
        }
    };

    var upload = function (row) {
        active++;
        row.state = 'uploading';
        var fd = new FormData();
        fd.append('file', row.file);
        if (C.fixedDevice) { fd.append('device_id', C.fixedDevice); }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', C.tempUrl);
        xhr.setRequestHeader('X-CSRF-TOKEN', EDL.csrf);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) { $('.progress > span', row.el).style.width = Math.round(e.loaded / e.total * 100) + '%'; }
        };
        xhr.onload = function () {
            active--;
            var res = {};
            try { res = JSON.parse(xhr.responseText); } catch (err) { }
            if (xhr.status === 200 && res.success) {
                row.token = res.data.token;
                row.state = 'ready';
                applySuggestion(row, res.data);
            } else {
                setError(row, res.message || (xhr.status === 413 ? 'File vượt giới hạn upload của máy chủ.' : 'Tải lên thất bại (lỗi ' + xhr.status + ').'));
            }
            pump();
            summary();
        };
        xhr.onerror = function () { active--; setError(row, 'Mất kết nối khi tải file.'); pump(); summary(); };
        xhr.send(fd);
    };

    /* ---------- Vẽ một dòng ---------- */
    var renderRow = function (row) {
        var tr = document.createElement('tr');
        tr.dataset.key = row.key;
        var ext = (row.file.name.split('.').pop() || '').toLowerCase();
        var deviceCell = C.fixedDevice
            ? ''
            : '<td><select class="form-select" data-f="device_id">' + options(C.devices, '', '— Chọn thiết bị —') + '</select><div class="auto-hint" data-hint="device_id"></div></td>';
        tr.innerHTML =
            '<td><div class="upload-file"><span class="file-icon">' + esc(ext.substring(0, 4)) + '</span><div style="min-width:0">' +
            '<div class="name">' + esc(row.file.name) + '</div><div class="meta">' + human(row.file.size) + '</div>' +
            '<div class="progress"><span></span></div><div class="row-msg"></div></div></div></td>' +
            '<td><input type="text" class="form-control" data-f="title" maxlength="500" value="' + esc(row.file.name.replace(/\.[^.]+$/, '')) + '">' +
            (C.fixedDevice ? '<input type="hidden" data-f="device_id" value="' + C.fixedDevice + '">' : '') + '<div class="auto-hint" data-hint="title"></div></td>' +
            deviceCell +
            '<td><select class="form-select" data-f="document_type_id">' + options(C.types, C.presetType || '', '— Chưa phân loại —') + '</select><div class="auto-hint" data-hint="document_type_id"></div></td>' +
            '<td><select class="form-select" data-f="status">' + mapOptions(C.statuses, 'active') + '</select></td>' +
            '<td><textarea class="form-control" data-f="description" rows="1" style="resize:vertical"></textarea></td>' +
            '<td class="text-center"><button type="button" class="row-remove" data-remove title="Bỏ file này — không lưu" aria-label="Bỏ file này">✕</button></td>';
        tbody.appendChild(tr);
        row.el = tr;
        $('.file-icon', tr).style.setProperty('--fi', '#64748b');

        $$('input, select, textarea', tr).forEach(function (el) {
            el.addEventListener('input', function () { el.dataset.touched = '1'; el.classList.remove('is-invalid'); if (row.state === 'error-save') { row.state = 'ready'; tr.classList.remove('row-error'); } });
        });
        $('[data-remove]', tr).addEventListener('click', function () { removeRow(row); });
    };

    var hint = function (row, field, text) { var h = $('[data-hint="' + field + '"]', row.el); if (h) { h.textContent = text ? '✦ ' + text : ''; } };

    var applySuggestion = function (row, data) {
        var tr = row.el, s = data.suggest || {};
        $('.progress', tr).classList.add('done');
        $('.progress > span', tr).style.width = '100%';
        var icon = $('.file-icon', tr);
        icon.style.setProperty('--fi', data.color);
        $('.meta', tr).textContent = data.size_label + ' · ' + data.group;

        var set = function (field, value, label) {
            var el = $('[data-f="' + field + '"]', tr);
            if (value && !el.dataset.touched && (!el.value || field === 'title')) { el.value = value; if (label) { hint(row, field, label); } }
        };
        set('title', s.title);
        if (!$('[data-f=device_id]', tr).value && s.device_id) { set('device_id', s.device_id, s.device_detected ? 'Nhận diện từ tên file' : ''); }
        if (!$('[data-f=document_type_id]', tr).value && s.document_type_id) { set('document_type_id', s.document_type_id, 'Nhận diện từ tên file'); }

        var msg = $('.row-msg', tr);
        if (data.duplicate) {
            tr.classList.add('row-dup');
            msg.className = 'row-msg warn';
            msg.innerHTML = '⚠ Trùng nội dung với <a href="' + esc(data.duplicate.url) + '" target="_blank">' + esc(data.duplicate.code) + '</a> (' + esc(data.duplicate.device) + ')';
        } else {
            msg.className = 'row-msg ok';
            msg.textContent = 'Sẵn sàng lưu';
        }
    };

    var setError = function (row, message) {
        row.state = 'error';
        row.el.classList.add('row-error');
        $('.progress', row.el).classList.add('fail');
        $('.progress > span', row.el).style.width = '100%';
        var msg = $('.row-msg', row.el);
        msg.className = 'row-msg err';
        msg.textContent = message;
    };

    var removeRow = function (row) {
        if (row.token && row.state !== 'saved') { EDL.request(C.discardUrl + '/' + row.token, { method: 'DELETE' }); }
        queue = queue.filter(function (q) { return q !== row; });
        rows = rows.filter(function (r) { return r !== row; });
        row.el.remove();
        if (!rows.length) { card.hidden = true; dropzone.classList.remove('is-compact'); }
        summary();
    };

    var summary = function () {
        var count = function (st) { return rows.filter(function (r) { return st.indexOf(r.state) !== -1; }).length; };
        var html = '<span><b>' + rows.length + '</b> file</span>' +
            '<span class="text-primary">Đang tải: <b>' + count(['queued', 'uploading']) + '</b></span>' +
            '<span class="text-success">Sẵn sàng: <b>' + count(['ready', 'error-save']) + '</b></span>' +
            '<span class="text-success">Đã lưu: <b>' + count(['saved']) + '</b></span>' +
            '<span class="text-danger">Lỗi: <b>' + count(['error', 'error-save']) + '</b></span>';
        $('#upload-summary').innerHTML = html;
        $('#save-all').disabled = count(['ready', 'error-save']) === 0 || count(['queued', 'uploading']) > 0;
    };

    /* ---------- Lưu tất cả ---------- */
    $('#save-all').addEventListener('click', function () {
        var targets = rows.filter(function (r) { return r.state === 'ready' || r.state === 'error-save'; });
        var missing = targets.filter(function (r) { return !$('[data-f=device_id]', r.el).value || !$('[data-f=title]', r.el).value.trim(); });
        missing.forEach(function (r) {
            ['device_id', 'title'].forEach(function (f) { var el = $('[data-f="' + f + '"]', r.el); if (!el.value.trim()) { el.classList.add('is-invalid'); } });
        });
        if (missing.length) {
            EDL.toast(missing.length + ' dòng chưa có tiêu đề hoặc thiết bị.', 'error');
            missing[0].el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }

        var items = targets.map(function (r) {
            var item = { key: r.key, token: r.token };
            $$('[data-f]', r.el).forEach(function (el) { item[el.dataset.f] = el.value; });
            return item;
        });

        var btn = $('#save-all');
        btn.disabled = true;
        btn.textContent = 'Đang lưu ' + items.length + ' tài liệu…';

        EDL.request(C.saveUrl, { method: 'POST', json: { items: items } }).then(function (res) {
            btn.innerHTML = '✓ Lưu tất cả';
            var results = (res.data && res.data.results) || [];
            results.forEach(function (r) {
                var row = rows.filter(function (x) { return x.key === r.key; })[0];
                if (!row) { return; }
                var msg = $('.row-msg', row.el);
                if (r.success) {
                    row.state = 'saved';
                    row.el.classList.remove('row-error', 'row-dup');
                    row.el.classList.add('row-done');
                    msg.className = 'row-msg ok';
                    msg.innerHTML = '✓ ' + esc(r.message) + ' — <a href="' + esc(r.url) + '" target="_blank">mở</a>';
                    $$('input, select, textarea', row.el).forEach(function (el) { el.disabled = true; });
                    $('[data-remove]', row.el).hidden = true;
                } else {
                    row.state = 'error-save';
                    row.el.classList.add('row-error');
                    msg.className = 'row-msg err';
                    msg.textContent = r.message;
                    if (r.field) { var el = $('[data-f="' + r.field + '"]', row.el); if (el) { el.classList.add('is-invalid'); } }
                }
            });
            EDL.toast(res.message || 'Đã xử lý', res.success ? 'success' : 'error');
            summary();
        }).catch(function () {
            btn.innerHTML = '✓ Lưu tất cả';
            EDL.toast('Không lưu được — kiểm tra kết nối.', 'error');
            summary();
        });
    });

    $('#add-more').addEventListener('click', function () { input.click(); });

    /* ---------- Kéo thả ---------- */
    input.addEventListener('change', function () { addFiles(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) {
        document.addEventListener(ev, function (e) { e.preventDefault(); dropzone.classList.add('drag'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        document.addEventListener(ev, function (e) { e.preventDefault(); if (ev === 'drop' || e.target === dropzone) { dropzone.classList.remove('drag'); } });
    });
    document.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files.length) { addFiles(e.dataTransfer.files); }
    });

    // Cảnh báo khi rời trang còn file chưa lưu
    window.addEventListener('beforeunload', function (e) {
        if (rows.some(function (r) { return ['queued', 'uploading', 'ready', 'error-save'].indexOf(r.state) !== -1; })) {
            e.preventDefault(); e.returnValue = '';
        }
    });
})();
