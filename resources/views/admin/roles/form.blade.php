@extends('layouts.app')
@php $isEdit = $role !== null; $isAdminRole = $isEdit && $role->slug === 'admin'; @endphp
@section('title', $isEdit ? 'Phân quyền vai trò' : 'Thêm vai trò')
@section('page-title', 'Quản trị — Vai trò & phân quyền')

@section('content')
    @php $hideErrorSummary = true; @endphp
    <div style="max-width:1100px">
        <div class="page-header">
            <div>
                <div class="breadcrumb"><a href="{{ route('roles.index') }}">Vai trò & phân quyền</a></div>
                <h2>{{ $isEdit ? 'Vai trò: ' . $role->name : 'Thêm vai trò' }}</h2>
            </div>
            <a href="{{ route('roles.index') }}" class="btn btn-secondary">← Quay lại</a>
        </div>

        <form method="POST" action="{{ $isEdit ? route('roles.update', $role->id) : route('roles.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <div class="card mb-16">
                <div class="card-body">
                    <div class="form-row cols-3">
                        <div class="form-group"><label class="form-label required">Tên vai trò</label><input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $role->name ?? '') }}" required>@error('name')<div class="form-error">{{ $message }}</div>@enderror</div>
                        <div class="form-group"><label class="form-label">Mã vai trò</label><input type="text" name="slug" class="form-control mono @error('slug') is-invalid @enderror" value="{{ old('slug', $role->slug ?? '') }}" @readonly($isEdit && $role->is_system) placeholder="Tự sinh từ tên">@error('slug')<div class="form-error">{{ $message }}</div>@enderror</div>
                        <div class="form-group"><label class="form-label">Mô tả</label><input type="text" name="description" class="form-control" value="{{ old('description', $role->description ?? '') }}"></div>
                    </div>
                </div>
            </div>

            @if ($isAdminRole)
                <div class="flash-alert info">{!! icon('shield') !!}<div>Vai trò <b>Quản trị hệ thống</b> luôn có toàn bộ quyền ở mọi bộ phận — không cần phân quyền chi tiết.</div></div>
            @else
                <div class="d-flex justify-between align-center mb-8">
                    <div class="fw-700">Ma trận quyền</div>
                    <div class="btn-group"><button type="button" class="btn btn-xs btn-secondary" data-all="1">Chọn tất cả</button><button type="button" class="btn btn-xs btn-secondary" data-all="0">Bỏ chọn tất cả</button></div>
                </div>
                @foreach ($groups as $group)
                    <div class="perm-group">
                        <div class="perm-group-head">
                            <span>{{ $group->name }}</span>
                            <label class="form-check text-sm" style="font-weight:500"><input type="checkbox" data-group-toggle="{{ $group->id }}"> Chọn cả nhóm</label>
                        </div>
                        <div class="perm-items">
                            @foreach ($group->permissions as $p)
                                <label>
                                    <input type="checkbox" name="permissions[]" value="{{ $p->id }}" data-group="{{ $group->id }}" @checked(in_array($p->id, old('permissions', $selected)))>
                                    <span>{{ $p->display_name }}<small>{{ $p->slug }}</small></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endif

            <div class="form-actions">
                <button class="btn btn-primary">{!! icon('check') !!} Lưu</button>
                <a href="{{ route('roles.index') }}" class="btn btn-secondary">Hủy</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-group-toggle]').forEach(function (t) {
            var boxes = document.querySelectorAll('[data-group="' + t.dataset.groupToggle + '"]');
            var sync = function () { t.checked = Array.prototype.every.call(boxes, function (b) { return b.checked; }); };
            t.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = t.checked; }); });
            boxes.forEach(function (b) { b.addEventListener('change', sync); });
            sync();
        });
        document.querySelectorAll('[data-all]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('input[name="permissions[]"], [data-group-toggle]').forEach(function (b) { b.checked = btn.dataset.all === '1'; });
            });
        });
    </script>
@endpush
