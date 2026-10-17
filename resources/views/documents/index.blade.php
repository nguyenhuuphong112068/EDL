@extends('layouts.app')
@php
    $scope  = request('scope');
    $titles = ['recent' => 'Xem gần đây', 'mine' => 'Tài liệu tôi tải lên'];
    $title  = $titles[$scope] ?? 'Tra cứu tài liệu';
    $advancedOpen = collect(['department_id', 'status', 'uploaded_by', 'date_from', 'date_to'])
        ->contains(fn ($k) => request()->filled($k));
@endphp
@section('title', $title)
@section('page-title', 'Tra cứu tài liệu')

@section('content')
    <div class="page-header">
        <div>
            <h2>{{ $title }}</h2>
            <p>Tìm theo tên, mã tài liệu, mã / tên thiết bị và cả <b>nội dung bên trong file</b> (PDF, Word, Excel, PowerPoint, văn bản).
                @if (user_can('devices.view'))Muốn thêm tài liệu, vào <a href="{{ route('devices.index') }}">Danh sách thiết bị</a> và chọn thiết bị.@endif</p>
        </div>
        <div class="btn-group">
            @if (user_can('documents.export'))
                <a href="{{ route('documents.export', request()->query()) }}" class="btn btn-secondary">{!! icon('excel') !!} Xuất Excel</a>
            @endif
        </div>
    </div>

    <div class="card">
        {{-- Tab phạm vi --}}
        <div class="page-tabs">
            @foreach ([null => ['Tất cả', 'library', $counts['all']], 'recent' => ['Xem gần đây', 'clock', null], 'mine' => ['Tôi tải lên', 'upload', $counts['mine']]] as $key => [$label, $ic, $count])
                <a href="{{ query_url(['scope' => $key ?: null]) }}" class="page-tab {{ $scope == $key ? 'active' : '' }}">
                    {!! icon($ic, '', ['style' => 'width:15px;height:15px']) !!} {{ $label }}
                    @if ($count !== null)<span class="count">{{ number_format($count) }}</span>@endif
                </a>
            @endforeach
        </div>

        {{-- Ô tìm kiếm chính --}}
        <form method="GET" action="{{ route('documents.index') }}" id="search-form">
            @if ($scope)<input type="hidden" name="scope" value="{{ $scope }}">@endif
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="filter-bar">
                <div class="filter-group grow">
                    <label>Từ khoá</label>
                    <input type="search" name="q" class="form-control" value="{{ request('q') }}"
                        placeholder='Vd: hiệu chuẩn HPLC · "vệ sinh máy" · MDV-01 · DEAB912345' autofocus>
                </div>
                <div class="filter-group">
                    <label>Tìm trong</label>
                    <select name="in" class="form-select">
                        <option value="all" @selected(request('in', 'all') === 'all')>Thông tin + nội dung file</option>
                        <option value="title" @selected(request('in') === 'title')>Chỉ thông tin tài liệu</option>
                        <option value="content" @selected(request('in') === 'content')>Chỉ nội dung file</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Thiết bị</label>
                    <select name="device_id" class="form-select" style="max-width:240px">
                        <option value="">Tất cả thiết bị</option>
                        @foreach ($lookups['devices'] as $id => $label)
                            <option value="{{ $id }}" @selected(request('device_id') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label>Loại tài liệu</label>
                    <select name="document_type_id" class="form-select">
                        <option value="">Tất cả loại</option>
                        @foreach ($lookups['documentTypes'] as $id => $label)
                            <option value="{{ $id }}" @selected(request('document_type_id') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label>Định dạng</label>
                    <select name="file_group" class="form-select">
                        <option value="">Mọi định dạng</option>
                        @foreach (config('edl.file_groups') as $key => $g)
                            <option value="{{ $key }}" @selected(request('file_group') === $key)>{{ $g['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">{!! icon('search') !!} Tìm</button>
                        <button type="button" class="btn btn-secondary" id="toggle-advanced">{!! icon('filter') !!} Nâng cao</button>
                    </div>
                </div>
            </div>

            {{-- Bộ lọc nâng cao --}}
            <div class="filter-advanced" id="advanced-filters" @if (! $advancedOpen) hidden @endif>
                @foreach ([
                    'department_id'   => ['Bộ phận lắp đặt', $lookups['departments']],
                    'uploaded_by'     => ['Người tải lên', $lookups['uploaders']],
                    'status'          => ['Tình trạng', collect(config('edl.document_statuses'))->map(fn ($s) => $s['label'])->all()],
                ] as $name => [$label, $options])
                    <div class="filter-group">
                        <label>{{ $label }}</label>
                        <select name="{{ $name }}" class="form-select">
                            <option value="">Tất cả</option>
                            @foreach ($options as $value => $text)
                                <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div class="filter-group">
                    <label>Tải lên từ ngày</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>
                <div class="filter-group">
                    <label>Đến ngày</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>
                <div class="filter-group">
                    <label>Sắp xếp</label>
                    <select name="sort" class="form-select">
                        @foreach (\App\Support\DocumentSearch::SORTS as $key => $label)
                            <option value="{{ $key }}" @selected(request('sort', 'newest') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Áp dụng bộ lọc</button>
                </div>
            </div>
        </form>

        {{-- Bộ lọc đang áp dụng + bộ lọc đã lưu --}}
        @if ($chips || request()->filled('q') || $savedSearches->isNotEmpty())
            <div class="filter-chips">
                @if (request()->filled('q'))
                    <span class="filter-chip">Từ khoá: “{{ request('q') }}” <a href="{{ query_url(['q' => null, 'in' => null]) }}" title="Bỏ">&times;</a></span>
                @endif
                @foreach ($chips as $key => $label)
                    <span class="filter-chip">{{ $label }} <a href="{{ query_url([$key => null] + ($key === 'date_from' ? ['date_to' => null] : [])) }}" title="Bỏ">&times;</a></span>
                @endforeach
                @if ($chips || request()->filled('q'))
                    <a href="{{ route('documents.index', array_filter(['scope' => $scope, 'view' => $view])) }}" class="btn btn-xs btn-ghost">Xoá tất cả</a>
                    <button type="button" class="btn btn-xs btn-outline-primary" data-modal-open="save-search-modal">{!! icon('bookmark') !!} Lưu bộ lọc</button>
                @endif

                @if ($savedSearches->isNotEmpty())
                    <span class="text-xs text-muted" style="margin-left:auto">Bộ lọc đã lưu:</span>
                    @foreach ($savedSearches as $saved)
                        <span class="filter-chip" style="background:#f8fafc;border-color:var(--border);color:var(--text-main)">
                            <a href="{{ route('documents.index', json_decode($saved->query, true)) }}" style="width:auto;height:auto;border-radius:0">{!! icon('bookmark', '', ['style' => 'width:12px;height:12px']) !!} {{ $saved->name }}</a>
                            <form method="POST" action="{{ route('saved-searches.destroy', $saved->id) }}" style="display:inline" data-confirm="Xoá bộ lọc đã lưu &quot;{{ $saved->name }}&quot;?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-ghost" style="border:none;background:none;cursor:pointer;color:var(--text-muted)">&times;</button>
                            </form>
                        </span>
                    @endforeach
                @endif
            </div>
        @endif

        <div class="toolbar">
            <span class="toolbar-info">
                Tìm thấy <b>{{ number_format($documents->total()) }}</b> tài liệu
            </span>
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

        @if ($view === 'grid')
            @include('documents.partials._grid', ['term' => request('q')])
        @else
            @include('documents.partials._table', ['term' => request('q'), 'emptyText' => request()->hasAny(['q', 'device_id', 'document_type_id']) ? 'Không tìm thấy tài liệu phù hợp. Thử bỏ bớt bộ lọc hoặc tìm trong nội dung file.' : 'Chưa có tài liệu nào.'])
        @endif

        {{ $documents->links() }}
    </div>

    {{-- Lưu bộ lọc --}}
    <div class="modal" id="save-search-modal">
        <form method="POST" action="{{ route('saved-searches.store') }}" class="modal-dialog">
            @csrf
            @foreach (request()->only(\App\Support\DocumentSearch::FILTER_KEYS) as $k => $v)
                @if (! is_array($v) && $v !== null && $v !== '')
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach
            <div class="modal-header"><div class="modal-title">Lưu bộ lọc tìm kiếm</div><button type="button" class="modal-close" data-modal-close>&times;</button></div>
            <div class="modal-body">
                <label class="form-label required">Tên bộ lọc</label>
                <input type="text" name="name" class="form-control" maxlength="100" required placeholder="Vd: Hiệu chuẩn sắp hết hạn - QC">
                <div class="form-hint">Bộ lọc đã lưu hiện ở đầu trang tra cứu, bấm để mở lại ngay.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
                <button type="submit" class="btn btn-primary">Lưu</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('toggle-advanced').addEventListener('click', function () {
            var box = document.getElementById('advanced-filters');
            box.hidden = !box.hidden;
        });
        // Đổi bộ lọc dạng chọn là tìm ngay
        document.querySelectorAll('#search-form select').forEach(function (s) {
            s.addEventListener('change', function () { if (!document.getElementById('advanced-filters').contains(s)) { s.form.submit(); } });
        });
    </script>
@endpush
