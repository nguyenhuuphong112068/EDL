@extends('layouts.app')
@php use App\Support\Labels; @endphp
@section('title', 'Người dùng')
@section('page-title', 'Quản trị — Người dùng')

@section('content')
    <div class="page-header">
        <div>
            <h2>Người dùng</h2>
            <p>Gán vai trò theo từng bộ phận, cấp / chặn quyền riêng, đặt lại mật khẩu, mở khoá tài khoản.</p>
        </div>
        @if (user_can('users.manage'))
            <a href="{{ route('users.create') }}" class="btn btn-primary">{!! icon('plus') !!} Thêm người dùng</a>
        @endif
    </div>

    <div class="card">
        <form method="GET" class="filter-bar">
            <div class="filter-group grow"><label>Tìm kiếm</label><input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Họ tên, tên đăng nhập, email…"></div>
            <div class="filter-group">
                <label>Bộ phận chính</label>
                <select name="department_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->code }} — {{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="filter-group">
                <label>Trạng thái</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Tất cả</option>
                    <option value="active" @selected(request('status') === 'active')>Đang hoạt động</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Đã vô hiệu</option>
                    <option value="locked" @selected(request('status') === 'locked')>Đang bị khoá</option>
                </select>
            </div>
            <div class="filter-group"><label>&nbsp;</label><button class="btn btn-primary">{!! icon('search') !!} Lọc</button></div>
        </form>

        <div class="table-wrap">
            <table>
                <thead><tr><th>Người dùng</th><th>Bộ phận chính</th><th>Vai trò @ bộ phận</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th>Mật khẩu hết hạn</th><th class="col-actions"></th></tr></thead>
                <tbody>
                    @foreach ($users as $u)
                        @php $locked = $u->locked_until && now()->lt($u->locked_until); @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-center gap-8">
                                    <span class="avatar" style="width:30px;height:30px;font-size:.75rem">{{ mb_strtoupper(mb_substr(collect(preg_split('/\s+/u', trim($u->name)))->last(), 0, 1)) }}</span>
                                    <div><div class="fw-600">{{ $u->name }}</div><div class="cell-sub mono">{{ $u->userName }}@if ($u->position) · {{ $u->position }}@endif</div></div>
                                </div>
                            </td>
                            <td>{{ $u->department_code ? $u->department_code . ' — ' . $u->department_name : '—' }}</td>
                            <td>
                                @foreach ($roles[$u->id] ?? [] as $r)
                                    <span class="badge {{ $r->slug === 'admin' ? 'badge-danger' : 'badge-primary' }}" style="margin:1px">{{ $r->name }} @ {{ $r->department_code ?? 'Mọi bộ phận' }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if (! $u->is_active)<span class="badge badge-secondary">Đã vô hiệu</span>
                                @elseif ($locked)<span class="badge badge-danger">{!! icon('lock', '', ['style' => 'width:11px;height:11px']) !!} Khoá tới {{ \Illuminate\Support\Carbon::parse($u->locked_until)->format('H:i') }}</span>
                                @else<span class="badge badge-success badge-dot">Hoạt động</span>@endif
                                @if ($u->must_change_password)<div class="cell-sub text-warning">Chờ đổi mật khẩu</div>@endif
                            </td>
                            <td class="text-sm">
                                @if (user_can('login_history.view'))
                                    <a href="{{ route('login-history.index', ['user' => $u->userName, 'from' => now()->subDays(30)->toDateString()]) }}" title="Xem lịch sử đăng nhập">{{ Labels::datetime($u->last_login_at) }}</a>
                                @else
                                    {{ Labels::datetime($u->last_login_at) }}
                                @endif
                                <div class="cell-sub mono">{{ $u->last_login_ip }}</div>
                            </td>
                            @php $pwDays = Labels::daysUntil($u->password_expires_at); @endphp
                            <td class="text-sm">
                                @if ($pwDays === null)
                                    <span class="expiry none">—</span>
                                @else
                                    <span class="expiry {{ $pwDays < 0 ? 'expired' : ($pwDays <= 14 ? 'soon' : 'ok') }}">
                                        {{ Labels::date($u->password_expires_at) }}
                                        <br><small>{{ $pwDays < 0 ? 'Đã hết hạn' : 'Còn ' . $pwDays . ' ngày' }}</small>
                                    </span>
                                @endif
                            </td>
                            <td class="col-actions">
                                @if (user_can('users.manage'))
                                    <div class="btn-group">
                                        <a href="{{ route('users.edit', $u->id) }}" class="btn btn-xs btn-outline-primary">{!! icon('edit') !!} Sửa</a>
                                        <a href="{{ route('users.permissions', $u->id) }}" class="btn btn-xs btn-secondary" title="Quyền riêng">{!! icon('shield') !!}</a>
                                        @if ($locked)
                                            <form method="POST" action="{{ route('users.unlock', $u->id) }}">@csrf<button class="btn btn-xs btn-warning" title="Mở khoá">{!! icon('unlock') !!}</button></form>
                                        @endif
                                        @if ($u->id !== auth()->id())
                                            <form method="POST" action="{{ route('users.toggle', $u->id) }}" data-confirm="{{ $u->is_active ? 'Vô hiệu hoá' : 'Kích hoạt' }} tài khoản {{ $u->userName }}?">@csrf
                                                <button class="btn btn-xs {{ $u->is_active ? 'btn-outline-danger' : 'btn-success' }}" title="{{ $u->is_active ? 'Vô hiệu hoá' : 'Kích hoạt' }}">{!! icon($u->is_active ? 'x-circle' : 'check-circle') !!}</button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
@endsection
