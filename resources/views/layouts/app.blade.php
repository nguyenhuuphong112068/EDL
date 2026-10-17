<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="EDL - Equipment Document Library - Thư viện tài liệu thiết bị">
    <title>@yield('title', 'Trang chủ') — EDL</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('edl.version') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/iconstella.svg') }}">
    @stack('styles')
</head>

<body>
    @php
        $authUser    = auth()->user();
        $currentDept = current_department_id();
        $departments = access_control()->selectableDepartments($authUser->id);
        $canAllDepts = access_control()->canAccessDepartment($authUser->id, 0);
    @endphp

    <div class="layout">
        @include('layouts.partials.sidebar')

        <main class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <button type="button" class="topbar-toggle" data-sidebar-toggle aria-label="Mở menu">{!! icon('menu') !!}</button>
                <div class="topbar-title">@yield('page-title', 'Trang chủ')</div>

                {{-- Tìm nhanh toàn hệ thống: tài liệu + thiết bị, gõ là ra (Ctrl+K) --}}
                @if (user_can('documents.view'))
                    <form class="quick-search" method="GET" action="{{ route('documents.index') }}" data-quick-search
                        data-url="{{ route('search.quick') }}" autocomplete="off">
                        {!! icon('search', 'qs-icon') !!}
                        <input type="search" name="q" placeholder="Tìm tài liệu, mã thiết bị, nội dung file…"
                            value="{{ request()->routeIs('documents.index') ? request('q') : '' }}" aria-label="Tìm nhanh">
                        <kbd>Ctrl K</kbd>
                        <div class="qs-panel" hidden></div>
                    </form>
                @endif

                <div class="topbar-actions">
                    {{-- Bộ phận đang làm việc — quyền & dữ liệu hiển thị theo bộ phận này (mô hình WMS) --}}
                    <form method="POST" action="{{ route('department.switch') }}" class="dept-switch">
                        @csrf
                        <label for="dept-switch">Bộ phận</label>
                        <select id="dept-switch" name="department_id" onchange="this.form.submit()"
                            title="Bộ phận đang làm việc">
                            @if ($canAllDepts)
                                <option value="0" @selected($currentDept === 0)>★ Tất cả bộ phận</option>
                            @endif
                            @foreach ($departments as $dept)
                                <option value="{{ $dept->id }}" @selected($currentDept === (int) $dept->id)>{{ $dept->code }} — {{ $dept->name }}</option>
                            @endforeach
                            @if ($departments->isEmpty() && ! $canAllDepts)
                                <option value="">(Chưa được phân bộ phận)</option>
                            @endif
                        </select>
                    </form>

                    <div class="user-menu">
                        <button type="button" class="user-menu-btn" data-dropdown-toggle="user-dropdown">
                            <span class="avatar">{{ mb_strtoupper(mb_substr(collect(preg_split('/\s+/u', trim($authUser->name)))->last() ?: $authUser->userName, 0, 1)) }}</span>
                            <span class="user-menu-name">{{ $authUser->name }}<small>{{ $authUser->userName }}</small></span>
                        </button>
                        <div class="dropdown" id="user-dropdown">
                            <a href="{{ route('documents.index', ['scope' => 'mine']) }}">{!! icon('upload') !!} Tài liệu tôi tải lên</a>
                            <a href="{{ route('login-history.mine') }}">{!! icon('history') !!} Lịch sử đăng nhập của tôi</a>
                            <a href="{{ route('password.change') }}">{!! icon('key') !!} Đổi mật khẩu</a>
                            <hr>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">{!! icon('logout') !!} Đăng xuất</button>
                            </form>
                        </div>
                    </div>

                    <a href="{{ route('password.change') }}" class="topbar-password {{ request()->routeIs('password.change') ? 'active' : '' }}" title="Đổi mật khẩu">{!! icon('key') !!}<span>Đổi mật khẩu</span></a>

                    <form method="POST" action="{{ route('logout') }}" class="topbar-logout">
                        @csrf
                        <button type="submit" title="Đăng xuất">{!! icon('logout') !!}<span>Đăng xuất</span></button>
                    </form>
                </div>
            </header>

            <!-- Page Body -->
            <div class="page-body">
                @if (session('success'))
                    <div class="flash-alert success">{!! icon('check-circle') !!}<div>{{ session('success') }}</div></div>
                @endif
                @if (session('error'))
                    <div class="flash-alert error">{!! icon('x-circle') !!}<div>{{ session('error') }}</div></div>
                @endif
                @if (session('warning'))
                    <div class="flash-alert warning">{!! icon('alert') !!}<div>{{ session('warning') }}</div></div>
                @endif
                @if ($errors->any() && ! isset($hideErrorSummary))
                    <div class="flash-alert error">{!! icon('x-circle') !!}
                        <div>Vui lòng kiểm tra lại thông tin:
                            <ul style="margin:4px 0 0 18px;list-style:disc">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if ($currentDept === -1)
                    <div class="flash-alert warning">{!! icon('alert') !!}
                        <div>Tài khoản của bạn chưa được gán bộ phận làm việc nên chưa xem được thiết bị / tài liệu nội bộ. Vui lòng liên hệ quản trị hệ thống.</div>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <div class="toast-wrap" id="toast-wrap"></div>

    <script>
        window.EDL = {
            csrf: document.querySelector('meta[name="csrf-token"]').content,
            idleMinutes: {{ (int) config('edl.security.idle_timeout_minutes') }},
            loginUrl: @json(route('login')),
        };
    </script>
    <script src="{{ asset('js/app.js') }}?v={{ config('edl.version') }}"></script>
    @stack('scripts')
</body>

</html>
