@extends('layouts.app')
@php use App\Services\Security\LoginHistory; use Illuminate\Support\Carbon; @endphp
@section('title', 'Lịch sử đăng nhập')
@section('page-title', 'Quản trị — Lịch sử đăng nhập')

@section('content')
    <div class="page-header">
        <div>
            <h2>Lịch sử đăng nhập</h2>
            <p>Mọi lượt đăng nhập (thành công, sai mật khẩu, bị khoá, bị chặn), thời lượng phiên và nguồn truy cập. Dữ liệu chỉ ghi thêm.</p>
        </div>
        <a href="{{ route('login-history.index', request()->query() + ['export' => 'csv']) }}" class="btn btn-secondary">{!! icon('excel') !!} Xuất Excel</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon green">{!! icon('check-circle') !!}</div><div><div class="stat-value">{{ (int) $today->success }}</div><div class="stat-label">Đăng nhập thành công hôm nay · {{ (int) $today->users }} người</div></div></div>
        <a href="{{ route('login-history.index', ['status' => 'failed', 'from' => today()->toDateString()]) }}" class="stat-card"><div class="stat-icon yellow">{!! icon('x-circle') !!}</div><div><div class="stat-value">{{ (int) $today->failed }}</div><div class="stat-label">Sai mật khẩu hôm nay</div></div></a>
        <a href="{{ route('login-history.index', ['status' => 'locked', 'from' => today()->toDateString()]) }}" class="stat-card"><div class="stat-icon red">{!! icon('lock') !!}</div><div><div class="stat-value">{{ (int) $today->locked }}</div><div class="stat-label">Bị khoá / chặn hôm nay</div></div></a>
        <div class="stat-card"><div class="stat-icon blue">{!! icon('users') !!}</div><div><div class="stat-value">{{ $online->count() }}</div><div class="stat-label">Phiên đang mở</div></div></div>
        <div class="stat-card"><div class="stat-icon cyan">{!! icon('location') !!}</div><div><div class="stat-value">{{ (int) $today->ips }}</div><div class="stat-label">Địa chỉ IP hôm nay</div></div></div>
    </div>

    @if ($online->isNotEmpty() || $suspicious->isNotEmpty())
        <div class="grid grid-2 mb-16">
            <div class="card">
                <div class="card-header"><div class="card-title">{!! icon('users') !!} Đang trực tuyến</div><span class="text-xs text-muted">thao tác trong {{ config('edl.security.idle_timeout_minutes') }} phút gần đây</span></div>
                <div class="table-wrap">
                    <table>
                        <tbody>
                            @forelse ($online as $s)
                                <tr>
                                    <td><span class="fw-600">{{ $s->full_name ?? $s->userName }}</span><div class="cell-sub mono">{{ $s->userName }}</div></td>
                                    <td class="text-sm">{{ $s->browser }} · {{ $s->platform }}<div class="cell-sub mono">{{ $s->ip_address }}</div></td>
                                    <td class="text-sm text-right">Từ {{ Carbon::parse($s->login_at)->format('H:i') }}<div class="cell-sub">thao tác {{ Carbon::parse($s->last_seen_at)->locale('vi')->diffForHumans() }}</div></td>
                                </tr>
                            @empty
                                <tr><td class="text-muted text-sm">Không có phiên nào đang mở.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><div class="card-title">{!! icon('alert') !!} Nhập sai nhiều lần (24 giờ qua)</div></div>
                <div class="table-wrap">
                    <table>
                        <tbody>
                            @forelse ($suspicious as $s)
                                <tr>
                                    <td><a href="{{ route('login-history.index', ['user' => $s->userName, 'from' => today()->subDay()->toDateString()]) }}" class="mono fw-600">{{ $s->userName }}</a></td>
                                    <td><span class="badge badge-danger">{{ $s->attempts }} lần sai</span></td>
                                    <td class="text-sm">{{ $s->ips }} IP</td>
                                    <td class="text-sm text-right">lần cuối {{ Carbon::parse($s->last_at)->format('H:i d/m') }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-muted text-sm">Không có tài khoản nào bị nhập sai bất thường.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group"><label>Từ ngày</label><input type="date" name="from" class="form-control" value="{{ $from }}"></div>
            <div class="filter-group"><label>Đến ngày</label><input type="date" name="to" class="form-control" value="{{ $to }}"></div>
            <div class="filter-group">
                <label>Tài khoản</label>
                <select name="user" class="form-select"><option value="">Tất cả</option>@foreach ($users as $u)<option @selected(request('user') === $u)>{{ $u }}</option>@endforeach</select>
            </div>
            <div class="filter-group">
                <label>Bộ phận</label>
                <select name="department_id" class="form-select"><option value="">Tất cả</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->code }}</option>@endforeach</select>
            </div>
            <div class="filter-group">
                <label>Kết quả</label>
                <select name="status" class="form-select"><option value="">Tất cả</option>@foreach (LoginHistory::STATUSES as $k => $s)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $s['label'] }}</option>@endforeach</select>
            </div>
            <div class="filter-group"><label>Địa chỉ IP</label><input type="text" name="ip" class="form-control" value="{{ request('ip') }}" placeholder="vd: 192.168.1."></div>
            <div class="filter-group"><label>&nbsp;</label><div class="btn-group"><button class="btn btn-primary">{!! icon('filter') !!} Lọc</button><a href="{{ route('login-history.index') }}" class="btn btn-secondary">Xoá lọc</a></div></div>
        </form>

        <div class="toolbar"><span class="toolbar-info">Tổng cộng <b>{{ number_format($logs->total()) }}</b> lượt đăng nhập</span></div>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Thời điểm</th><th>Tài khoản</th><th>Kết quả</th><th>Địa chỉ IP</th><th>Trình duyệt / HĐH</th><th>Kết thúc phiên</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="nowrap">{{ Carbon::parse($log->login_at)->format('d/m/Y H:i:s') }}<div class="cell-sub">{{ Carbon::parse($log->login_at)->locale('vi')->diffForHumans() }}</div></td>
                            <td>
                                <a href="{{ route('login-history.index', ['user' => $log->userName] + request()->only('from', 'to')) }}" class="mono fw-700">{{ $log->userName }}</a>
                                <div class="cell-sub">{{ $log->full_name ?? 'Tài khoản không tồn tại' }}@if ($log->department_code) · {{ $log->department_code }}@endif</div>
                            </td>
                            <td>
                                <span class="badge {{ LoginHistory::STATUSES[$log->status]['badge'] ?? 'badge-secondary' }}">{{ LoginHistory::STATUSES[$log->status]['label'] ?? $log->status }}</span>
                                @if ($log->reason)<div class="cell-sub">{{ $log->reason }}</div>@endif
                            </td>
                            <td class="mono text-sm">{{ $log->ip_address }}</td>
                            <td class="text-sm" title="{{ $log->user_agent }}">{{ $log->browser ?? '—' }}<div class="cell-sub">{{ $log->platform }}</div></td>
                            <td class="text-sm">@include('admin.login-history._session', ['log' => $log])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state">{!! icon('key') !!}<p>Không có lượt đăng nhập trong khoảng thời gian này.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
@endsection
