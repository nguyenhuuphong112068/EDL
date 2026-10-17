@extends(auth()->check() ? 'layouts.app' : 'layouts.auth')
@section('title', 'Không có quyền')
@section('page-title', 'Không có quyền truy cập')

@section('content')
    <div class="card card-body" style="max-width:640px;margin:40px auto">
        <div class="empty-state">
            {!! icon('lock', '', ['style' => 'opacity:.6;color:#dc2626']) !!}
            <h2 style="color:var(--text-main);margin-bottom:8px">403 — Không có quyền</h2>
            <p>{{ $exception->getMessage() ?: 'Bạn không có quyền truy cập chức năng này.' }}</p>
            <p class="text-sm" style="margin-top:6px">Nếu bạn làm việc ở nhiều bộ phận, hãy kiểm tra ô <b>Bộ phận</b> trên thanh trên cùng.</p>
            <div class="btn-group" style="margin-top:18px;justify-content:center">
                <a href="{{ url()->previous() }}" class="btn btn-secondary">← Quay lại</a>
                <a href="{{ route('home') }}" class="btn btn-primary">Trang chủ</a>
            </div>
        </div>
    </div>
@endsection
