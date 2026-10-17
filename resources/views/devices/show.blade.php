@extends('layouts.app')
@php use App\Support\Labels; use App\Support\FileType; @endphp
@section('title', $device->code . ' — ' . $device->name)
@section('page-title', 'Quản lý tài liệu thiết bị')

@section('content')
    <div class="page-header">
        <div>
            <div class="breadcrumb"><a href="{{ route('devices.index') }}">Thiết bị</a> {!! icon('chevron-right', '', ['style' => 'width:12px;height:12px']) !!} <span>{{ $device->code }}</span></div>
            <h2><span class="mono">{{ $device->code }}</span> — {{ $device->name }}</h2>
            <p>
                <span class="badge {{ Labels::deviceStatusBadge($device->status) }}">{{ Labels::deviceStatus($device->status) }}</span>
                Bộ phận lắp đặt: {{ $device->department_name }}
            </p>
        </div>
        <div class="btn-group">
            <a href="{{ route('devices.qr', ['ids' => [$device->id]]) }}" target="_blank" class="btn btn-secondary">{!! icon('printer') !!} In nhãn QR</a>
            @if ($canManage && user_can('devices.create'))
                <a href="{{ route('devices.index', ['copy' => $device->id]) }}" class="btn btn-secondary" title="Tạo thiết bị mới từ thông tin thiết bị này">{!! icon('copy') !!} Nhân bản</a>
            @endif
            @if ($canManage && user_can('devices.update'))
                <a href="{{ route('devices.index', ['edit' => $device->id]) }}" class="btn btn-secondary">{!! icon('edit') !!} Sửa</a>
            @endif
            @if ($canManage && user_can('documents.upload'))
                <a href="{{ route('uploads.create', ['device_id' => $device->id]) }}" class="btn btn-primary">{!! icon('cloud-up') !!} Tải tài liệu lên</a>
            @endif
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon blue">{!! icon('file') !!}</div><div><div class="stat-value">{{ number_format($stats->total) }}</div><div class="stat-label">Tài liệu</div></div></div>
        <div class="stat-card"><div class="stat-icon cyan">{!! icon('database') !!}</div><div><div class="stat-value" style="font-size:1.3rem">{{ FileType::humanSize($stats->bytes) }}</div><div class="stat-label">Dung lượng</div></div></div>
        <div class="stat-card"><div class="stat-icon green">{!! icon('eye') !!}</div><div><div class="stat-value">{{ number_format($stats->views) }}</div><div class="stat-label">Lượt xem tài liệu</div></div></div>
        @if (user_can('documents.restore') && $canManage)<a href="{{ route('trash.index', ['device_id' => $device->id]) }}" class="stat-card"><div class="stat-icon yellow">{!! icon('archive') !!}</div><div><div class="stat-value">{{ (int) $stats->obsolete }}</div><div class="stat-label">Hết hiệu lực</div></div></a>@else<div class="stat-card"><div class="stat-icon yellow">{!! icon('archive') !!}</div><div><div class="stat-value">{{ (int) $stats->obsolete }}</div><div class="stat-label">Hết hiệu lực</div></div></div>@endif
    </div>

    <div class="grid" style="grid-template-columns: 280px minmax(0,1fr)">
        {{-- Cột trái: folder theo loại + thông tin thiết bị --}}
        <div style="display:flex;flex-direction:column;gap:16px;min-width:0">
            <div class="card">
                <div class="card-header"><div class="card-title">{!! icon('folder') !!} Thư mục tài liệu</div></div>
                <div class="folder-list">
                    <a href="{{ route('devices.show', $device->id) }}" class="folder-item {{ ! $activeFolder ? 'active' : '' }}">{!! icon('library') !!} Tất cả tài liệu <span class="count">{{ $stats->total }}</span></a>
                    @foreach ($folders as $f)
                        @php $key = $f->document_type_id ?? 'none'; @endphp
                        <a href="{{ route('devices.show', [$device->id, 'folder' => $key]) }}" class="folder-item {{ (string) $activeFolder === (string) $key ? 'active' : '' }}">
                            {!! icon('folder', '', ['style' => 'color:' . ($f->color ?? '#94a3b8')]) !!} <span class="truncate">{{ $f->name }}</span> <span class="count">{{ $f->total }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><div class="card-title">{!! icon('device') !!} Thông tin thiết bị</div></div>
                <div class="card-body">
                    <dl class="detail-list" style="grid-template-columns:105px 1fr;font-size:.82rem">
                        <dt>Mã thiết bị</dt><dd class="mono fw-700">{{ $device->code }}</dd>
                        <dt>Tên thiết bị</dt><dd>{{ $device->name }}</dd>
                        <dt>Bộ phận lắp đặt</dt><dd>{{ $device->department_code }} — {{ $device->department_name }}</dd>
                        <dt>Tình trạng</dt><dd><span class="badge {{ Labels::deviceStatusBadge($device->status) }}">{{ Labels::deviceStatus($device->status) }}</span></dd>
                        <dt>Tài liệu</dt><dd>{{ number_format($stats->total) }}</dd>
                        <dt>Người tạo</dt><dd>{{ $device->creator_name ?? '—' }}</dd>
                        <dt>Ngày tạo</dt><dd>{{ Labels::datetime($device->created_at) }}</dd>
                    </dl>
                    <hr class="divider">
                    <div style="display:flex;gap:12px;align-items:center">
                        <div style="width:92px;height:92px;flex-shrink:0">{!! $qr !!}</div>
                        <div class="text-xs text-muted">Quét mã QR để mở hồ sơ thiết bị trên điện thoại / máy tính bảng tại hiện trường.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cột phải: danh sách tài liệu + tải lên nhanh --}}
        <div style="display:flex;flex-direction:column;gap:16px;min-width:0">
            @if ($canManage && user_can('documents.upload'))
                <a href="{{ route('uploads.create', ['device_id' => $device->id, 'document_type_id' => is_numeric($activeFolder) ? $activeFolder : null]) }}"
                    class="dropzone compact" id="device-drop" style="text-decoration:none;color:inherit">
                    {!! icon('cloud-up') !!}
                    <div><b>Tải tài liệu lên</b> cho {{ $device->code }}
                        <div class="text-xs text-muted">Có thể chọn nhiều file cùng lúc — thông tin thiết bị được điền sẵn.</div></div>
                </a>
            @endif

            @php
                $filtered = request()->hasAny(['q', 'file_group', 'status']) && collect(request()->only(['q', 'file_group', 'status']))->filter()->isNotEmpty();
                $folderName = $activeFolder ? optional($folders->first(fn ($f) => (string) ($f->document_type_id ?? 'none') === (string) $activeFolder))->name : null;
            @endphp
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{!! icon('library') !!} Tài liệu của {{ $device->code }}@if ($folderName) <span class="text-muted" style="font-weight:400">· {{ $folderName }}</span>@endif</div>
                    @if (user_can('documents.export'))
                        <a href="{{ route('documents.export', ['device_id' => $device->id] + request()->only(['q', 'in', 'file_group', 'status']) + (is_numeric($activeFolder) ? ['document_type_id' => $activeFolder] : [])) }}" class="btn btn-sm btn-secondary">{!! icon('excel') !!} Xuất Excel</a>
                    @endif
                </div>
                <form method="GET" class="filter-bar" id="device-doc-filter">
                    @if ($activeFolder)<input type="hidden" name="folder" value="{{ $activeFolder }}">@endif
                    <input type="hidden" name="view" value="{{ $view }}">
                    <div class="filter-group grow">
                        <label>Từ khoá</label>
                        <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Tên, mã tài liệu, tên file, nội dung file…">
                    </div>
                    <div class="filter-group">
                        <label>Tìm trong</label>
                        <select name="in" class="form-select">
                            <option value="all" @selected(request('in', 'all') === 'all')>Thông tin + nội dung</option>
                            <option value="title" @selected(request('in') === 'title')>Chỉ thông tin</option>
                            <option value="content" @selected(request('in') === 'content')>Chỉ nội dung file</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Định dạng</label>
                        <select name="file_group" class="form-select" data-autosubmit>
                            <option value="">Tất cả</option>
                            @foreach (config('edl.file_groups') as $key => $g)
                                <option value="{{ $key }}" @selected(request('file_group') === $key)>{{ $g['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Tình trạng</label>
                        <select name="status" class="form-select" data-autosubmit>
                            <option value="">Tất cả</option>
                            @foreach (config('edl.document_statuses') as $key => $s)
                                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Sắp xếp</label>
                        <select name="sort" class="form-select" data-autosubmit>
                            <option value="">Theo loại tài liệu</option>
                            @foreach (\App\Support\DocumentSearch::SORTS as $key => $label)
                                @continue($key === 'device')
                                <option value="{{ $key }}" @selected(request('sort') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <div class="btn-group">
                            <button class="btn btn-primary">{!! icon('search') !!} Tìm</button>
                            @if ($filtered)
                                <a href="{{ route('devices.show', array_filter([$device->id, 'folder' => $activeFolder, 'view' => $view === 'grid' ? 'grid' : null])) }}" class="btn btn-secondary">Xoá lọc</a>
                            @endif
                        </div>
                    </div>
                </form>

                <div class="toolbar">
                    <span class="toolbar-info">Hiển thị <b>{{ number_format($documents->total()) }}</b> / {{ number_format($stats->total) }} tài liệu</span>
                    <div class="btn-group">
                        <select class="form-select" style="width:auto;padding:5px 8px;font-size:.8rem" onchange="location.href=this.value">
                            @foreach ([20, 50, 100] as $n)
                                <option value="{{ query_url(['per_page' => $n]) }}" @selected((int) request('per_page', 20) === $n)>{{ $n }} / trang</option>
                            @endforeach
                        </select>
                        <div class="view-toggle">
                            <a href="{{ query_url(['view' => 'list']) }}" class="{{ $view === 'list' ? 'active' : '' }}" title="Dạng danh sách">{!! icon('list') !!}</a>
                            <a href="{{ query_url(['view' => 'grid']) }}" class="{{ $view === 'grid' ? 'active' : '' }}" title="Dạng lưới">{!! icon('grid') !!}</a>
                        </div>
                    </div>
                </div>

                @include('documents.partials._bulk-bar')
                @php $emptyText = $filtered ? 'Không có tài liệu phù hợp. Thử bỏ bớt bộ lọc.' : ($activeFolder ? 'Thư mục này chưa có tài liệu.' : 'Thiết bị chưa có tài liệu nào.'); @endphp
                @if ($view === 'grid')
                    @include('documents.partials._grid', ['showDevice' => false, 'term' => request('q'), 'emptyText' => $emptyText])
                @else
                    @include('documents.partials._table', ['showDevice' => false, 'term' => request('q'), 'emptyText' => $emptyText])
                @endif
                {{ $documents->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('#device-doc-filter [data-autosubmit]').forEach(function (s) {
            s.addEventListener('change', function () { s.form.submit(); });
        });
    </script>
@endpush
