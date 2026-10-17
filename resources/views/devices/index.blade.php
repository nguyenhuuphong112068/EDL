@extends('layouts.app')
@php use App\Support\Labels; @endphp
@section('title', 'Danh sách thiết bị')
@section('page-title', 'Thiết bị')

@section('content')
    @php
        $canCreate = user_can('devices.create');
        $canUpdate = user_can('devices.update');
        $canDelete = user_can('devices.delete');
        // Dữ liệu mỗi dòng cho modal sửa / nhân bản
        $modalFields = ['id', 'code', 'name', 'department_id', 'status', 'creator_name', 'created_at'];
        $openData    = $openDevice ? collect((array) $openDevice)->only($modalFields)->all() : null;
        $defaultDept = current_department_id() > 0 ? current_department_id() : null;
    @endphp

    <div class="page-header">
        <div>
            <h2>Danh sách thiết bị</h2>
            <p>Bấm vào một thiết bị để mở trang quản lý tài liệu của thiết bị đó. Thêm, sửa, nhân bản, ngừng sử dụng và kích hoạt lại ngay trên trang này.</p>
        </div>
        <div class="btn-group">
            @if (user_can('documents.upload'))
                <a href="{{ route('uploads.create') }}" class="btn btn-secondary" title="Tải nhiều file cho nhiều thiết bị cùng lúc — tự nhận thiết bị theo tên file">{!! icon('cloud-up') !!} Tải tài liệu hàng loạt</a>
            @endif
            @if ($canCreate)
                <button type="button" class="btn btn-primary" data-device-open="create">{!! icon('plus') !!} Thêm thiết bị</button>
            @endif
        </div>
    </div>

    <div class="card">
        {{-- Trạng thái hồ sơ thiết bị --}}
        <div class="page-tabs">
            @foreach (['1' => ['Đang hoạt động', 'device', null], '0' => ['Đã ngừng sử dụng', 'archive', $inactiveCount], 'all' => ['Tất cả', 'list', null]] as $key => [$label, $ic, $count])
                <a href="{{ query_url(['active' => $key === '1' ? null : $key]) }}" class="page-tab {{ $active === (string) $key ? 'active' : '' }}">
                    {!! icon($ic, '', ['style' => 'width:15px;height:15px']) !!} {{ $label }}
                    @if ($count !== null)<span class="count">{{ $count }}</span>@endif
                </a>
            @endforeach
        </div>

        <form method="GET" class="filter-bar">
            @if ($active !== '1')<input type="hidden" name="active" value="{{ $active }}">@endif
            <div class="filter-group grow">
                <label>Tìm kiếm</label>
                <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Mã hoặc tên thiết bị…">
            </div>
            @if ($departments->count() > 1)
                <div class="filter-group">
                    <label>Bộ phận lắp đặt</label>
                    <select name="department_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Tất cả</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->code }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="filter-group">
                <label>Tình trạng</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    @foreach (config('edl.device_statuses') as $key => $s)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $s['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Sắp xếp</label>
                <select name="sort" class="form-select" onchange="this.form.submit()">
                    @foreach (['code' => 'Mã thiết bị', 'name' => 'Tên thiết bị', 'documents' => 'Nhiều tài liệu nhất', 'newest' => 'Mới thêm'] as $k => $v)
                        <option value="{{ $k }}" @selected(request('sort', 'code') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <div class="btn-group">
                    <button class="btn btn-primary">{!! icon('search') !!} Lọc</button>
                    <a href="{{ route('devices.index', $active !== '1' ? ['active' => $active] : []) }}" class="btn btn-secondary">Xoá lọc</a>
                </div>
            </div>
        </form>

        <div class="toolbar"><span class="toolbar-info">Tổng cộng <b>{{ number_format($devices->total()) }}</b> thiết bị</span></div>

        <form method="GET" action="{{ route('devices.qr') }}" target="_blank" class="bulk-bar" id="qr-form">
            <span class="bulk-count">Đã chọn 0</span>
            <button type="submit" class="btn btn-sm btn-primary">{!! icon('qr') !!} In nhãn QR</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="col-check"><input type="checkbox" data-check-all></th>
                        <th>Mã thiết bị</th>
                        <th>Tên thiết bị</th>
                        <th>Bộ phận lắp đặt</th>
                        <th>Tình trạng</th>
                        <th class="text-center">Tài liệu</th>
                        <th>Người tạo</th>
                        <th>Ngày tạo</th>
                        <th class="col-actions">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $d)
                        @php
                            $inactive  = $d->deleted_at !== null;
                            $manage    = works_on_department($d->department_id);
                            $rowData   = collect((array) $d)->only($modalFields)->all();
                        @endphp
                        <tr @if ($inactive) class="row-inactive" @else class="row-link" data-href="{{ route('devices.show', $d->id) }}" title="Mở trang quản lý tài liệu của {{ $d->code }}" @endif>
                            <td class="col-check">@unless ($inactive)<input type="checkbox" data-check-item value="{{ $d->id }}">@endunless</td>
                            <td class="nowrap">
                                @if ($inactive)
                                    <span class="mono fw-700">{{ $d->code }}</span>
                                @else
                                    <a href="{{ route('devices.show', $d->id) }}" class="cell-title mono">{{ $d->code }}</a>
                                @endif
                            </td>
                            <td>@if ($inactive){{ $d->name }}@else<a href="{{ route('devices.show', $d->id) }}" class="row-link-target">{{ $d->name }}</a>@endif</td>
                            <td><span class="badge badge-secondary">{{ $d->department_code }}</span> <span class="text-sm">{{ $d->department_name }}</span></td>
                            <td>
                                @if ($inactive)
                                    <span class="badge badge-secondary badge-dot">Đã ngừng sử dụng</span>
                                    <div class="cell-sub">{{ Labels::date($d->deleted_at) }}</div>
                                @else
                                    <span class="badge {{ Labels::deviceStatusBadge($d->status) }}">{{ Labels::deviceStatus($d->status) }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge {{ $d->documents_count ? 'badge-primary' : 'badge-secondary' }}">{{ $d->documents_count }}</span>
                            </td>
                            <td class="text-sm">{{ $d->creator_name ?? '—' }}</td>
                            <td class="text-sm nowrap">{{ Labels::datetime($d->created_at) }}</td>
                            <td class="col-actions">
                                <div class="btn-group">
                                    @if ($inactive)
                                        @if ($canDelete && $manage)
                                            <form method="POST" action="{{ route('devices.restore', [$d->id] + request()->query()) }}"
                                                data-confirm="Kích hoạt lại thiết bị {{ $d->code }}? Thiết bị và {{ $d->documents_count }} tài liệu sẽ hiện lại.">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-success">{!! icon('restore') !!} Kích hoạt lại</button>
                                            </form>
                                        @endif
                                    @else
                                        <a href="{{ route('devices.show', $d->id) }}" class="btn btn-xs btn-secondary" title="Quản lý tài liệu">{!! icon('folder') !!}</a>
                                        @if (user_can('documents.upload') && $manage)
                                            <a href="{{ route('uploads.create', ['device_id' => $d->id]) }}" class="btn btn-xs btn-secondary" title="Tải tài liệu lên">{!! icon('upload') !!}</a>
                                        @endif
                                        @if ($canUpdate && $manage)
                                            <button type="button" class="btn btn-xs btn-outline-primary" title="Sửa" data-device-open="edit" data-device='@json($rowData)'>{!! icon('edit') !!}</button>
                                        @endif
                                        @if ($canCreate)
                                            <button type="button" class="btn btn-xs btn-secondary" title="Nhân bản — tạo thiết bị mới từ thông tin thiết bị này" data-device-open="copy" data-device='@json($rowData)'>{!! icon('copy') !!}</button>
                                        @endif
                                        @if ($canDelete && $manage)
                                            <form method="POST" action="{{ route('devices.destroy', [$d->id] + request()->query()) }}"
                                                data-confirm="Ngừng sử dụng thiết bị {{ $d->code }}?{{ $d->documents_count ? ' ' . $d->documents_count . ' tài liệu của thiết bị sẽ được ẩn theo.' : '' }} Có thể kích hoạt lại bất cứ lúc nào.">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-xs btn-outline-danger" title="Ngừng sử dụng (xoá mềm)">{!! icon('x-circle') !!}</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty-state">{!! icon('device') !!}<p>
                            {{ $active === '0' ? 'Không có thiết bị nào đã ngừng sử dụng.' : 'Chưa có thiết bị nào.' }}
                            @if ($canCreate && $active !== '0')<a href="#" data-device-open="create">Thêm thiết bị</a>@endif
                        </p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $devices->links() }}
    </div>

    {{-- ================= MODAL THÊM / SỬA / NHÂN BẢN THIẾT BỊ ================= --}}
    @if ($canCreate || $canUpdate)
        <div class="modal" id="device-modal">
            <form class="modal-dialog" id="device-form" novalidate
                data-store-url="{{ route('devices.store') }}" data-update-url="{{ url('devices') }}">
                <div class="modal-header">
                    <div class="modal-title" id="device-modal-title">Thêm thiết bị</div>
                    <button type="button" class="modal-close" data-modal-close>&times;</button>
                </div>
                <div class="modal-body">
                    <div class="flash-alert error" id="device-form-error" hidden>{!! icon('x-circle') !!}<div></div></div>

                    <div class="form-row cols-2">
                        <div class="form-group">
                            <label class="form-label required">Mã thiết bị</label>
                            <input type="text" name="code" class="form-control mono" maxlength="50" placeholder="Vd: MDV-01" required>
                            <div class="form-hint">Đưa mã này vào tên file để hệ thống tự nhận diện khi tải lên.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Tên thiết bị</label>
                            <input type="text" name="name" class="form-control" maxlength="255" required>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label required">Bộ phận lắp đặt</label>
                            <select name="department_id" class="form-select" required>
                                @foreach ($departments as $dep)
                                    <option value="{{ $dep->id }}">{{ $dep->code }} — {{ $dep->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label required">Tình trạng</label>
                            <select name="status" class="form-select">
                                @foreach (config('edl.device_statuses') as $key => $s)
                                    <option value="{{ $key }}">{{ $s['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-hint" id="device-audit-info" style="margin-top:14px"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
                    <button type="submit" class="btn btn-primary" id="device-submit">{!! icon('check') !!} <span>Lưu</span></button>
                </div>
            </form>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            // In nhãn QR cho các dòng đã chọn
            var qrForm = document.getElementById('qr-form');
            qrForm.addEventListener('submit', function () {
                qrForm.querySelectorAll('input[name="ids[]"]').forEach(function (i) { i.remove(); });
                EDL.selectedIds().forEach(function (id) {
                    var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; qrForm.appendChild(i);
                });
            });

            var form = document.getElementById('device-form');
            if (!form) { return; }

            var title = document.getElementById('device-modal-title');
            var errorBox = document.getElementById('device-form-error');
            var submitLabel = document.querySelector('#device-submit span');
            var defaultDept = @json($defaultDept);
            var mode = 'create', editId = null;

            var clearErrors = function () {
                errorBox.hidden = true;
                form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
                form.querySelectorAll('.form-error').forEach(function (el) { el.remove(); });
            };

            // mode: create | edit | copy — data: thông tin thiết bị (sửa / nhân bản)
            var open = function (m, data) {
                mode = m; data = data || {};
                editId = m === 'edit' ? data.id : null;
                form.reset();
                clearErrors();
                Object.keys(data).forEach(function (k) {
                    var el = form.elements[k];
                    if (el && data[k] !== null && data[k] !== undefined) { el.value = data[k]; }
                });
                if (m === 'create') {
                    if (defaultDept) { form.elements.department_id.value = defaultDept; }
                    form.elements.status.value = 'active';
                }
                if (m === 'copy') {
                    // Nhân bản: giữ tên / bộ phận / tình trạng, mã thiết bị phải nhập mới
                    form.elements.code.value = '';
                }
                // Người tạo / ngày tạo do hệ thống ghi — chỉ hiển thị khi sửa
                var audit = document.getElementById('device-audit-info');
                audit.textContent = m === 'edit' && data.created_at
                    ? 'Tạo bởi ' + (data.creator_name || '—') + ' lúc ' + new Date(data.created_at.replace(' ', 'T')).toLocaleString('vi-VN')
                    : 'Người tạo và ngày tạo được hệ thống ghi tự động.';
                title.textContent = m === 'edit' ? 'Sửa thiết bị ' + data.code : (m === 'copy' ? 'Nhân bản từ ' + data.code : 'Thêm thiết bị mới');
                submitLabel.textContent = m === 'edit' ? 'Lưu thay đổi' : 'Thêm thiết bị';
                EDL.modal.open('device-modal');
                setTimeout(function () { form.elements[m === 'edit' ? 'name' : 'code'].focus(); }, 60);
            };

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-device-open]');
                if (!btn) { return; }
                e.preventDefault();
                open(btn.dataset.deviceOpen, btn.dataset.device ? JSON.parse(btn.dataset.device) : null);
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                clearErrors();
                var fd = new FormData(form);
                var url = form.dataset.storeUrl;
                if (mode === 'edit') { url = form.dataset.updateUrl + '/' + editId; fd.append('_method', 'PUT'); }

                var btn = document.getElementById('device-submit');
                btn.disabled = true;
                fetch(url, {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': EDL.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) { return { status: res.status, data: data }; });
                }).then(function (r) {
                    btn.disabled = false;
                    if (r.status === 200 && r.data.success) {
                        EDL.modal.close('device-modal');
                        location.reload();
                        return;
                    }
                    var errors = r.data.errors || {};
                    Object.keys(errors).forEach(function (field) {
                        var el = form.elements[field];
                        if (!el) { return; }
                        el.classList.add('is-invalid');
                        var msg = document.createElement('div');
                        msg.className = 'form-error';
                        msg.textContent = errors[field][0];
                        el.parentNode.appendChild(msg);
                    });
                    errorBox.querySelector('div').textContent = Object.keys(errors).length ? 'Vui lòng kiểm tra lại các ô được đánh dấu.' : (r.data.message || 'Không lưu được (lỗi ' + r.status + ').');
                    errorBox.hidden = false;
                    var first = form.querySelector('.is-invalid');
                    if (first) { first.focus(); }
                }).catch(function () {
                    btn.disabled = false;
                    errorBox.querySelector('div').textContent = 'Mất kết nối, vui lòng thử lại.';
                    errorBox.hidden = false;
                });
            });

            // Mở sẵn khi đi từ trang hồ sơ thiết bị (?edit= / ?copy=)
            @if ($openData)
                open(@json($openMode), @json($openData));
            @endif
        })();
    </script>
@endpush
