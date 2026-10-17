@extends('layouts.app')
@section('title', $meta['title'])
@section('page-title', 'Dữ liệu gốc — ' . $meta['title'])

@section('content')
    <div class="page-header">
        <div>
            <div class="breadcrumb"><span>Dữ liệu gốc</span> {!! icon('chevron-right', '', ['style' => 'width:12px;height:12px']) !!} <span>{{ $meta['title'] }}</span></div>
            <h2>{{ $meta['title'] }}</h2>
            <p>{{ $meta['description'] }}</p>
        </div>
        @if ($meta['canManage'])
            <a href="{{ route($meta['route'] . '.create') }}" class="btn btn-primary">{!! icon('plus') !!} Thêm {{ $meta['singular'] }}</a>
        @endif
    </div>

    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group grow">
                <label>Tìm kiếm</label>
                <input type="search" name="search" class="form-control" placeholder="Mã, tên…" value="{{ request('search') }}">
            </div>
            @if ($hasActive)
                <div class="filter-group">
                    <label>Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="">Tất cả</option>
                        <option value="active" @selected(request('status') === 'active')>Đang dùng</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Ngừng dùng</option>
                    </select>
                </div>
            @endif
            <div class="filter-group">
                <label>&nbsp;</label>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">{!! icon('filter') !!} Lọc</button>
                    <a href="{{ route($meta['route'] . '.index') }}" class="btn btn-secondary">Xoá lọc</a>
                </div>
            </div>
        </form>

        <div class="toolbar">
            <span class="toolbar-info">Tổng cộng <b>{{ number_format($records->total()) }}</b> {{ $meta['singular'] }}</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach ($columns as $col)
                            <th @if ($col['type'] === 'swatch') style="width:28px" @endif>{{ $col['label'] }}</th>
                        @endforeach
                        @if ($meta['canManage'])
                            <th class="col-actions">Thao tác</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr>
                            @foreach ($columns as $key => $col)
                                @php $value = $record->{$key} ?? null; @endphp
                                <td>
                                    @switch($col['type'])
                                        @case('bold')
                                            <span class="fw-600">{{ $value }}</span>
                                        @break
                                        @case('mono')
                                            <span class="mono fw-700">{{ $value }}</span>
                                        @break
                                        @case('active')
                                            @if ($value)
                                                <span class="badge badge-success badge-dot">Đang dùng</span>
                                            @else
                                                <span class="badge badge-secondary badge-dot">Ngừng dùng</span>
                                            @endif
                                        @break
                                        @case('count')
                                            <span class="badge {{ $value ? 'badge-primary' : 'badge-secondary' }}">{{ number_format((int) $value) }}</span>
                                        @break
                                        @case('swatch')
                                            <span style="display:inline-block;width:16px;height:16px;border-radius:5px;background:{{ $value }}"></span>
                                        @break
                                        @default
                                            <span class="text-muted">{{ $value ?? '—' }}</span>
                                    @endswitch
                                </td>
                            @endforeach
                            @if ($meta['canManage'])
                                <td class="col-actions">
                                    <div class="btn-group">
                                        <a href="{{ route($meta['route'] . '.edit', $record->id) }}" class="btn btn-xs btn-outline-primary">{!! icon('edit') !!} Sửa</a>
                                        <form method="POST" action="{{ route($meta['route'] . '.destroy', $record->id) }}"
                                            data-confirm="Xoá {{ $meta['singular'] }} &quot;{{ $record->name }}&quot;?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger">{!! icon('trash') !!}</button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 1 }}">
                                <div class="empty-state">
                                    {!! icon($meta['icon']) !!}
                                    <p>Chưa có {{ $meta['singular'] }} nào.
                                        @if ($meta['canManage'])
                                            <a href="{{ route($meta['route'] . '.create') }}">Thêm ngay</a>
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $records->links() }}
    </div>
@endsection
