<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Đăng nhập') — EDL</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('edl.version') }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ config('edl.version') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/iconstella.svg') }}">
</head>

<body class="a3-body">
    <div class="a3">
        {{-- Nền 3D: sàn lưới phối cảnh + quầng sáng + các tấm file nổi --}}
        <div class="a3-floor" aria-hidden="true"></div>
        <div class="a3-glow g1" aria-hidden="true"></div>
        <div class="a3-glow g2" aria-hidden="true"></div>
        <div class="a3-tiles" aria-hidden="true">
            @foreach ([['PDF', '#ef4444', '7%', '16%', '0s'], ['DOCX', '#3b82f6', '20%', '70%', '-2s'], ['XLSX', '#22c55e', '86%', '14%', '-4s'], ['DWG', '#06b6d4', '90%', '64%', '-1s'], ['STEP', '#a855f7', '54%', '86%', '-3s'], ['MP4', '#ec4899', '44%', '7%', '-5s']] as [$ext, $color, $left, $top, $delay])
                <span class="a3-tile" style="--c: {{ $color }}; left: {{ $left }}; top: {{ $top }}; animation-delay: {{ $delay }}"><b>{{ $ext }}</b></span>
            @endforeach
        </div>

        <div class="a3-stage">
            {{-- Bên trái: khối lập phương 3D + tên hệ thống --}}
            <section class="a3-intro" aria-hidden="true">
                <div class="a3-scene">
                    <div class="a3-ring"><span></span><span></span><span></span></div>
                    <div class="a3-cube">
                        <div class="a3-face f1"><img src="{{ asset('img/iconstella.svg') }}" alt=""></div>
                        <div class="a3-face f2"><img src="{{ asset('img/iconstella.svg') }}" alt=""></div>
                        <div class="a3-face f3">{!! icon('file') !!}</div>
                        <div class="a3-face f4">{!! icon('device') !!}</div>
                        <div class="a3-face f5">{!! icon('cube') !!}</div>
                        <div class="a3-face f6"></div>
                    </div>
                    <div class="a3-cube-shadow"></div>
                </div>
                <div class="a3-wordmark">
                    <div class="a3-wm-main"><span>E</span><span class="d">D</span><span>L</span></div>
                    <div class="a3-wm-en">Equipment Document Library</div>
                    <div class="a3-wm-vi">Thư viện tài liệu thiết bị</div>
                </div>
            </section>

            {{-- Bên phải: thẻ đăng nhập kính mờ, nghiêng nhẹ theo chuột --}}
            <main class="a3-card" data-tilt>
                <div class="a3-card-light"></div>
                <div class="a3-brand">
                    <span class="a3-logo-wrap"><img src="{{ asset('img/stella-pharm.jpg') }}" alt="Stellapharm" class="a3-logo"></span>
                </div>
                @yield('content')
            </main>
        </div>

        <div class="a3-foot">© {{ date('Y') }} Stellapharm · Phiên bản {{ config('edl.version') }}</div>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.dataset.togglePassword);
                input.type = input.type === 'password' ? 'text' : 'password';
            });
        });

        // Thẻ đăng nhập nghiêng 3D theo chuột (tắt khi người dùng chọn giảm chuyển động)
        (function () {
            var card = document.querySelector('[data-tilt]');
            if (!card || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) { return; }
            document.addEventListener('mousemove', function (e) {
                var x = e.clientX / window.innerWidth - 0.5, y = e.clientY / window.innerHeight - 0.5;
                card.style.setProperty('--rx', (-y * 6).toFixed(2) + 'deg');
                card.style.setProperty('--ry', (x * 8).toFixed(2) + 'deg');
                var r = card.getBoundingClientRect();
                card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
                card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
            });
        })();
    </script>
</body>

</html>
