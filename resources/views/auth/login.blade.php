@extends('layouts.auth')
@section('title', 'Đăng nhập')

@section('content')
    <h1 class="auth-title">Đăng nhập</h1>

    @if (session('error'))
        <div class="flash-alert error">{!! icon('x-circle') !!}<div>{{ session('error') }}</div></div>
    @endif
    @if (session('success'))
        <div class="flash-alert success">{!! icon('check-circle') !!}<div>{{ session('success') }}</div></div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}" autocomplete="off">
        @csrf
        <div class="form-group">
            <label class="form-label" for="userName">Tên đăng nhập</label>
            <div class="input-icon">
                {!! icon('user', 'input-icon-lead') !!}
                <input type="text" id="userName" name="userName" class="form-control @error('userName') is-invalid @enderror"
                    value="{{ old('userName') }}" placeholder="Tên đăng nhập" required autofocus>
            </div>
            @error('userName')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu</label>
            <div class="input-icon">
                {!! icon('lock', 'input-icon-lead') !!}
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                    placeholder="Mật khẩu" required>
                <button type="button" class="input-icon-btn" data-toggle-password="password" title="Hiện / ẩn mật khẩu">{!! icon('eye') !!}</button>
            </div>
            @error('password')<div class="form-error">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary auth-submit">Đăng nhập {!! icon('chevron-right') !!}</button>
    </form>
@endsection
