@php
    /*
     * Menu trái — mỗi mục tự ẩn khi user không có quyền ở bộ phận đang làm việc.
     * active: mẫu tên route để tô sáng mục đang mở.
     */
    $trashCount = 0;

    if (user_can('documents.restore')) {
        $scope      = scope_department_ids();
        $trashCount = \Illuminate\Support\Facades\DB::table('documents')
            ->join('devices', 'devices.id', '=', 'documents.device_id')
            ->whereNotNull('documents.deleted_at')
            ->when($scope !== null, fn ($q) => $q->whereIn('devices.department_id', $scope))
            ->count();
    }

    $menu = [
        [
            'key'   => 'tong-quan',
            'label' => null,
            'items' => [
                ['route' => 'home', 'icon' => 'home', 'label' => 'Trang chủ', 'active' => 'home'],
            ],
        ],
        [
            'key'   => 'du-lieu-goc',
            'label' => 'Dữ liệu gốc',
            'items' => [
                ['route' => 'departments.index', 'icon' => 'building', 'label' => 'Bộ phận quản lý', 'perm' => 'departments.manage', 'active' => 'departments.*'],
                ['route' => 'document-types.index', 'icon' => 'file', 'label' => 'Loại tài liệu', 'perm' => 'master_data.view', 'active' => 'document-types.*'],
            ],
        ],
        [
            'key'   => 'thiet-bi',
            'label' => 'Thiết bị',
            'items' => [
                // Tài liệu được quản lý ngay trong từng thiết bị (trang thiết bị, tải lên, chi tiết tài liệu).
                // Tra cứu nhanh: ô tìm kiếm trên thanh trên cùng (Ctrl+K); xem gần đây: trang chủ.
                ['route' => 'devices.index', 'icon' => 'device', 'label' => 'Danh sách thiết bị', 'perm' => 'devices.view',
                    'active' => fn () => request()->routeIs('devices.index', 'devices.show', 'devices.qr', 'uploads.*', 'documents.*')],
                ['route' => 'trash.index', 'icon' => 'archive', 'label' => 'Tài liệu hết hiệu lực', 'perm' => 'documents.restore', 'badge' => $trashCount, 'badgeSoft' => true, 'active' => 'trash.*'],
            ],
        ],
        [
            'key'   => 'quan-tri',
            'label' => 'Quản trị hệ thống',
            'items' => [
                ['route' => 'users.index', 'icon' => 'users', 'label' => 'Người dùng', 'perm' => 'users.view', 'active' => 'users.*'],
                ['route' => 'roles.index', 'icon' => 'shield', 'label' => 'Vai trò & phân quyền', 'perm' => 'roles.view', 'active' => 'roles.*'],
                ['route' => 'audit.index', 'icon' => 'history', 'label' => 'Nhật ký hệ thống', 'perm' => 'audit.view', 'active' => 'audit.*'],
            ],
        ],
        [
            // Nhóm riêng, ngang cấp "Quản trị hệ thống": ai cũng xem được lịch sử của mình
            'key'   => 'lich-su-dang-nhap',
            'label' => 'Lịch sử đăng nhập',
            'items' => [
                ['route' => 'login-history.index', 'icon' => 'key', 'label' => 'Toàn hệ thống', 'perm' => 'login_history.view', 'active' => 'login-history.index'],
                ['route' => 'login-history.mine', 'icon' => 'user', 'label' => 'Của tôi', 'active' => 'login-history.mine'],
            ],
        ],
    ];
    // Dung lượng lưu trữ trong phạm vi bộ phận
    $storage = user_can('documents.view')
        ? \App\Support\DocumentScope::query(false)
            ->when(scope_department_ids() !== null, fn ($q) => $q->whereIn('devices.department_id', scope_department_ids()))
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(documents.file_size),0) as bytes')->first()
        : null;
@endphp

<aside class="sidebar">
    <a href="{{ route('home') }}" class="sidebar-brand">
        <span class="brand-tile"><img src="{{ asset('img/iconstella.svg') }}" alt="Stellapharm"></span>
        <div>
            <div class="brand-text">E<span>D</span>L</div>
            <div class="brand-sub">Thư viện tài liệu thiết bị</div>
        </div>
    </a>

    <nav class="sidebar-nav">
        @foreach ($menu as $section)
            @php
                $items = array_filter($section['items'], fn ($item) => ! isset($item['perm']) || user_can($item['perm']));
            @endphp
            @continue(! $items)

            <div class="nav-section" data-nav-section="{{ $section['key'] }}">
                @if ($section['label'])
                    <button type="button" class="nav-section-label" data-nav-toggle>
                        <span>{{ $section['label'] }}</span>
                        {!! icon('chevron-down', 'nav-section-chevron') !!}
                    </button>
                @endif
                <div class="nav-section-items">
                    @foreach ($items as $item)
                        @php
                            $href     = is_array($item['route']) ? route($item['route'][0], $item['route'][1]) : route($item['route']);
                            $isActive = is_callable($item['active']) ? ($item['active'])() : request()->routeIs($item['active']);
                        @endphp
                        <div class="nav-item">
                            <a href="{{ $href }}" class="nav-link {{ $isActive ? 'active' : '' }}">
                                <span class="nav-icon-tile">{!! icon($item['icon'], 'nav-icon') !!}</span>
                                <span class="nav-text">{{ $item['label'] }}</span>
                                @if (! empty($item['badge']))
                                    <span class="nav-badge {{ ! empty($item['badgeSoft']) ? 'soft' : '' }}">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                @endif
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    @if ($storage)
        <div class="sidebar-storage">
            <div>Lưu trữ trong phạm vi</div>
            <div style="margin-top:4px"><b>{{ number_format($storage->total) }}</b> tài liệu · <b>{{ \App\Support\FileType::humanSize($storage->bytes) }}</b></div>
        </div>
    @endif

    <div class="sidebar-footer">
        <div>Phiên bản {{ config('edl.version') }}</div>
        <div style="margin-top:3px">© {{ date('Y') }} Stellapharm</div>
    </div>
</aside>
