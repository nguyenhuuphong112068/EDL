@extends('layouts.app')
@section('title', 'Quyền riêng — ' . $user->userName)
@section('page-title', 'Quản trị — Quyền riêng người dùng')

@section('content')
    <div style="max-width:1100px">
        <div class="page-header">
            <div>
                <div class="breadcrumb"><a href="{{ route('users.index') }}">Người dùng</a></div>
                <h2>Quyền riêng: {{ $user->name }} <span class="mono text-muted" style="font-size:.9rem">({{ $user->userName }})</span></h2>
                <p>Mỗi quyền có 3 trạng thái: <b>Theo vai trò</b> (mặc định) · <b>Cấp</b> (cho dù vai trò không có) · <b>Chặn</b> (dù vai trò có). Quyền riêng áp dụng ở mọi bộ phận.</p>
            </div>
            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-secondary">← Về tài khoản</a>
        </div>

        @if ($isAdmin)
            <div class="flash-alert info">{!! icon('info') !!}<div>Người dùng có vai trò <b>Quản trị hệ thống</b> — luôn có toàn quyền, quyền riêng không có tác dụng.</div></div>
        @endif

        <form method="POST" action="{{ route('users.permissions.update', $user->id) }}">
            @csrf @method('PUT')
            @foreach ($groups as $group)
                <div class="perm-group">
                    <div class="perm-group-head">{{ $group->name }}</div>
                    <table>
                        <tbody>
                            @foreach ($group->permissions as $p)
                                <tr>
                                    <td>
                                        <div class="fw-600">{{ $p->display_name }}</div>
                                        <div class="cell-sub"><span class="mono">{{ $p->slug }}</span>@if ($p->description) · {{ $p->description }}@endif</div>
                                    </td>
                                    <td style="width:150px">
                                        @if ($p->from_role)
                                            <span class="badge badge-success">Vai trò có quyền</span>
                                        @else
                                            <span class="badge badge-secondary">Vai trò không có</span>
                                        @endif
                                    </td>
                                    <td style="width:260px">
                                        <div class="tri-state">
                                            <label><input type="radio" name="perm[{{ $p->id }}]" value="inherit" @checked($p->state === 'inherit')><span>Theo vai trò</span></label>
                                            <label class="allow"><input type="radio" name="perm[{{ $p->id }}]" value="allow" @checked($p->state === 'allow')><span>Cấp</span></label>
                                            <label class="deny"><input type="radio" name="perm[{{ $p->id }}]" value="deny" @checked($p->state === 'deny')><span>Chặn</span></label>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
            <div class="form-actions">
                <button class="btn btn-primary">{!! icon('check') !!} Lưu quyền riêng</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Hủy</a>
            </div>
        </form>
    </div>
@endsection
