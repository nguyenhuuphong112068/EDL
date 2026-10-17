@extends(auth()->check() ? 'layouts.app' : 'layouts.auth')
@section('title', 'Không tìm thấy')
@section('page-title', 'Không tìm thấy')

@section('content')
    <div class="card card-body" style="max-width:640px;margin:40px auto">
        <div class="empty-state">
            {!! icon('search', '', ['style' => 'opacity:.5']) !!}
            <h2 style="color:var(--text-main);margin-bottom:8px">404 — Không tìm thấy</h2>
            <p>{{ $exception->getMessage() ?: 'Trang hoặc dữ liệu bạn tìm không tồn tại, hoặc thuộc bộ phận bạn không có quyền xem.' }}</p>
            <div class="btn-group" style="margin-top:18px;justify-content:center">
                <a href="{{ url()->previous() }}" class="btn btn-secondary">← Quay lại</a>
                <a href="{{ route('home') }}" class="btn btn-primary">Trang chủ</a>
            </div>
        </div>
    </div>
@endsection
