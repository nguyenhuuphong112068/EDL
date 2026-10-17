@extends('layouts.app')
@php $isEdit = $user !== null; @endphp
@section('title', $isEdit ? 'Sửa người dùng' : 'Thêm người dùng')
@section('page-title', 'Quản trị — Người dùng')

@section('content')
    @php $hideErrorSummary = true; @endphp
    <div style="max-width:960px">
        <div class="page-header">
            <div>
                <div class="breadcrumb"><a href="{{ route('users.index') }}">Người dùng</a></div>
                <h2>{{ $isEdit ? 'Sửa tài khoản ' . $user->userName : 'Thêm người dùng' }}</h2>
            </div>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">← Quay lại</a>
        </div>

        <form method="POST" action="{{ $isEdit ? route('users.update', $user->id) : route('users.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <div class="card mb-16">
                <div class="card-body">
                    <div class="form-section-title">Thông tin tài khoản</div>
                    <div class="form-row cols-3">
                        <div class="form-group"><label class="form-label required">Họ tên</label><input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name ?? '') }}" required>@error('name')<div class="form-error">{{ $message }}</div>@enderror</div>
                        <div class="form-group"><label class="form-label required">Tên đăng nhập</label><input type="text" name="userName" class="form-control mono @error('userName') is-invalid @enderror" value="{{ old('userName', $user->userName ?? '') }}" required>@error('userName')<div class="form-error">{{ $message }}</div>@enderror</div>
                        <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email ?? '') }}"></div>
                        <div class="form-group"><label class="form-label">Chức vụ</label><input type="text" name="position" class="form-control" value="{{ old('position', $user->position ?? '') }}"></div>
                        <div class="form-group">
                            <label class="form-label">Bộ phận chính</label>
                            <select name="department_id" class="form-select">
                                <option value="">— Không —</option>
                                @foreach ($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id', $user->department_id ?? '') == $d->id)>{{ $d->code }} — {{ $d->name }}</option>@endforeach
                            </select>
                            <div class="form-hint">Bộ phận mặc định khi đăng nhập.</div>
                        </div>
                        <div class="form-group" style="align-self:center">
                            <input type="hidden" name="is_active" value="0">
                            <label class="form-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true))> <b>Đang hoạt động</b></label>
                        </div>
                    </div>

                    @unless ($isEdit)
                        <div class="form-row cols-2">
                            <div class="form-group"><label class="form-label required">Mật khẩu ban đầu</label><input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">@error('password')<div class="form-error">{{ $message }}</div>@enderror</div>
                            <div class="form-group"><label class="form-label required">Nhập lại mật khẩu</label><input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
                        </div>
                        <div class="form-hint" style="margin-top:-8px;margin-bottom:12px">Người dùng sẽ bị buộc đổi mật khẩu ở lần đăng nhập đầu tiên.</div>
                    @endunless

                    <div class="form-section-title">Vai trò theo bộ phận</div>
                    <p class="text-sm text-muted mb-16">Mỗi dòng: một vai trò áp dụng tại một bộ phận (hoặc mọi bộ phận). Quyền thực tế = quyền của các vai trò áp cho bộ phận đang làm việc + quyền cấp riêng.</p>
                    <table style="max-width:720px">
                        <thead><tr><th>Vai trò</th><th>Áp dụng tại bộ phận</th><th style="width:50px"></th></tr></thead>
                        <tbody id="assignments"></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-secondary mt-8" id="add-assignment">{!! icon('plus') !!} Thêm vai trò</button>
                    @error('assignments.*.role_id')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{!! icon('check') !!} Lưu</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Hủy</a>
            </div>
        </form>

        @if ($isEdit && user_can('users.reset_password'))
            <form method="POST" action="{{ route('users.reset-password', $user->id) }}" class="card mt-16" data-confirm="Đặt lại mật khẩu cho {{ $user->userName }}?">
                @csrf
                <div class="card-header"><div class="card-title">{!! icon('key') !!} Đặt lại mật khẩu</div></div>
                <div class="card-body d-flex gap-12 align-center flex-wrap">
                    <input type="password" name="new_password" class="form-control @error('new_password') is-invalid @enderror" style="max-width:280px" placeholder="Mật khẩu mới" autocomplete="new-password" required>
                    <button class="btn btn-warning">Đặt lại</button>
                    <span class="text-xs text-muted">Người dùng sẽ phải đổi mật khẩu khi đăng nhập lần tới.</span>
                    @error('new_password')<div class="form-error w-100">{{ $message }}</div>@enderror
                </div>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    @php
        $jsRoles   = $roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'admin' => $r->slug === 'admin'])->values();
        $jsDepts   = $departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->code . ' — ' . $d->name])->values();
        $jsInitial = array_values(old('assignments', $assignments->map(fn ($a) => ['role_id' => $a->role_id, 'department_id' => $a->department_id])->all()));
    @endphp
    <script>
        (function () {
            var roles = @json($jsRoles);
            var depts = @json($jsDepts);
            var canAdmin = @json($canAssignAdmin);
            var initial = @json($jsInitial);
            var body = document.getElementById('assignments'), n = 0;
            var add = function (a) {
                a = a || {};
                var i = n++;
                var tr = document.createElement('tr');
                tr.innerHTML = '<td><select class="form-select" name="assignments[' + i + '][role_id]" required>' +
                    roles.map(function (r) { return '<option value="' + r.id + '"' + (String(r.id) === String(a.role_id) ? ' selected' : '') + (r.admin && !canAdmin ? ' disabled' : '') + '>' + r.name + '</option>'; }).join('') +
                    '</select></td><td><select class="form-select" name="assignments[' + i + '][department_id]"><option value="">★ Mọi bộ phận</option>' +
                    depts.map(function (d) { return '<option value="' + d.id + '"' + (String(d.id) === String(a.department_id) ? ' selected' : '') + '>' + d.name + '</option>'; }).join('') +
                    '</select></td><td><button type="button" class="btn btn-xs btn-outline-danger">✕</button></td>';
                tr.querySelector('button').addEventListener('click', function () { tr.remove(); });
                body.appendChild(tr);
            };
            (initial.length ? initial : [{}]).forEach(add);
            document.getElementById('add-assignment').addEventListener('click', function () { add(); });
        })();
    </script>
@endpush
