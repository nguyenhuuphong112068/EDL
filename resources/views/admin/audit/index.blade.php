@extends('layouts.app')
@section('title', 'Nhật ký hệ thống')
@section('page-title', 'Quản trị — Nhật ký hệ thống')

@section('content')
    <div class="page-header">
        <div>
            <h2>Nhật ký hệ thống (Audit Trail)</h2>
            <p>Ghi lại đăng nhập, xem / tải / sửa / xoá tài liệu, thay đổi phân quyền… Nhật ký chỉ ghi thêm, không sửa / xoá được.</p>
        </div>
        <a href="{{ route('audit.index', request()->query() + ['export' => 'csv']) }}" class="btn btn-secondary">{!! icon('excel') !!} Xuất Excel</a>
    </div>

    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group"><label>Từ ngày</label><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
            <div class="filter-group"><label>Đến ngày</label><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
            <div class="filter-group">
                <label>Tài khoản</label>
                <select name="user" class="form-select"><option value="">Tất cả</option>@foreach ($users as $u)<option @selected(request('user') === $u)>{{ $u }}</option>@endforeach</select>
            </div>
            <div class="filter-group">
                <label>Hành động</label>
                <select name="action" class="form-select"><option value="">Tất cả</option>@foreach ($actions as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select>
            </div>
            <div class="filter-group">
                <label>Đối tượng</label>
                <select name="module" class="form-select"><option value="">Tất cả</option>@foreach ($modules as $m)<option @selected(request('module') === $m)>{{ $m }}</option>@endforeach</select>
            </div>
            <div class="filter-group grow"><label>Nội dung</label><input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Mô tả, mã bản ghi…"></div>
            <div class="filter-group"><label>&nbsp;</label><button class="btn btn-primary">{!! icon('filter') !!} Lọc</button></div>
        </form>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Thời gian</th><th>Tài khoản</th><th>Hành động</th><th>Đối tượng</th><th>Mô tả</th><th>Thay đổi</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="nowrap text-sm">{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                            <td><span class="mono fw-600">{{ $log->userName }}</span><div class="cell-sub">{{ $log->user_full_name }}</div></td>
                            <td><span class="badge {{ str_contains($log->action, 'thất bại') || str_contains($log->action, 'Khoá') || str_contains($log->action, 'Xoá') ? 'badge-danger' : 'badge-primary' }}">{{ $log->action }}</span></td>
                            <td class="text-sm">{{ $log->module }}@if ($log->record_id) <span class="text-muted">#{{ $log->record_id }}</span>@endif</td>
                            <td class="text-sm" style="max-width:340px">{{ $log->description }}</td>
                            <td style="max-width:320px">
                                @if ($log->old_values || $log->new_values)
                                    <details>
                                        <summary class="text-xs text-primary" style="cursor:pointer">Xem chi tiết</summary>
                                        @if ($log->old_values)<div class="text-xs" style="margin-top:4px"><b>Trước:</b> <code style="word-break:break-all">{{ $log->old_values }}</code></div>@endif
                                        @if ($log->new_values)<div class="text-xs" style="margin-top:4px"><b>Sau:</b> <code style="word-break:break-all">{{ $log->new_values }}</code></div>@endif
                                    </details>
                                @endif
                            </td>
                            <td class="mono text-xs">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty-state">{!! icon('history') !!}<p>Không có bản ghi trong khoảng thời gian này.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
@endsection
