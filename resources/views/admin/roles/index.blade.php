@extends('layouts.app')
@section('title', 'Vai trò & phân quyền')
@section('page-title', 'Quản trị — Vai trò & phân quyền')

@section('content')
    <div class="page-header">
        <div>
            <h2>Vai trò & phân quyền</h2>
            <p>Vai trò là nhóm quyền; khi gán cho người dùng sẽ chọn vai trò áp dụng ở bộ phận nào.</p>
        </div>
        @if (user_can('roles.manage'))
            <a href="{{ route('roles.create') }}" class="btn btn-primary">{!! icon('plus') !!} Thêm vai trò</a>
        @endif
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Vai trò</th><th>Mã</th><th>Mô tả</th><th>Số quyền</th><th>Người dùng</th><th class="col-actions"></th></tr></thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="fw-600">{{ $role->name }} @if ($role->is_system)<span class="badge badge-secondary">Hệ thống</span>@endif</td>
                            <td class="mono">{{ $role->slug }}</td>
                            <td class="text-muted text-sm">{{ $role->description }}</td>
                            <td>
                                @if ($role->slug === 'admin')
                                    <span class="badge badge-danger">Toàn quyền</span>
                                @else
                                    <span class="badge badge-primary">{{ $role->permissions_count }} / {{ $totalPermissions }}</span>
                                @endif
                            </td>
                            <td><span class="badge badge-secondary">{{ $role->users_count }}</span></td>
                            <td class="col-actions">
                                @if (user_can('roles.manage'))
                                    <div class="btn-group">
                                        <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-xs btn-outline-primary">{!! icon('edit') !!} Phân quyền</a>
                                        @unless ($role->is_system)
                                            <form method="POST" action="{{ route('roles.destroy', $role->id) }}" data-confirm="Xoá vai trò {{ $role->name }}?">@csrf @method('DELETE')<button class="btn btn-xs btn-outline-danger">{!! icon('trash') !!}</button></form>
                                        @endunless
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
