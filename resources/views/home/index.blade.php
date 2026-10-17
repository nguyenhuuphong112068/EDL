@extends('layouts.app')
@section('title', 'Trang chủ')
@section('page-title', 'Trang chủ')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/home.css') }}?v={{ config('edl.version') }}">
@endpush

@section('content')
    @php
        $deptName = current_department_id() === 0
            ? null
            : \Illuminate\Support\Facades\DB::table('departments')->where('id', current_department_id())->value('name');

        $weekdays = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];

        $ofDept = $deptName ? 'của ' . $deptName : 'của tất cả bộ phận';

        // [màu, icon, số, nhãn, dòng phụ]
        $cards = [['green', 'device', $deviceTotal, 'Thiết bị', 'Thiết bị đang sử dụng ' . $ofDept]];
        if ($canDocs) {
            $cards[] = ['blue', 'file', $docTotal, 'Tài liệu', 'Tài liệu đang hiệu lực ' . $ofDept];
            $cards[] = ['purple', 'clock', $recentTotal, 'Xem gần đây', 'Tài liệu bạn đã xem trong ' . $recentDays . ' ngày qua'];
        }
    @endphp

    <div class="h3">
        {{-- ================= HERO 3D ================= --}}
        <section class="h3-hero">
            <div class="h3-floor" aria-hidden="true"></div>
            <div class="h3-glow" aria-hidden="true"></div>

            <div class="h3-hero-text">
                <div class="h3-eyebrow"><img src="{{ asset('img/iconstella.svg') }}" alt=""> Stellapharm · EDL</div>
                <h1>Thư viện tài liệu thiết bị</h1>
                <p>Xin chào <b>{{ auth()->user()->name }}</b> — số liệu {{ $deptName ? 'của bộ phận ' . $deptName : 'của toàn bộ các bộ phận' }}.</p>
                <div class="h3-date">{!! icon('calendar') !!} {{ $weekdays[now()->dayOfWeek] }}, {{ now()->format('d/m/Y') }}</div>
            </div>

            <div class="h3-scene" aria-hidden="true">
                <div class="h3-orbit"><span></span><span></span><span></span></div>
                <div class="h3-cube">
                    <div class="h3-face f-front"><img src="{{ asset('img/iconstella.svg') }}" alt=""></div>
                    <div class="h3-face f-back"><img src="{{ asset('img/iconstella.svg') }}" alt=""></div>
                    <div class="h3-face f-right">{!! icon('file') !!}</div>
                    <div class="h3-face f-left">{!! icon('device') !!}</div>
                    <div class="h3-face f-top">{!! icon('cube') !!}</div>
                    <div class="h3-face f-bottom"></div>
                </div>
                <div class="h3-shadow"></div>
            </div>
        </section>

        {{-- ================= 3 CON SỐ ================= --}}
        <div class="h3-cards">
            @foreach ($cards as [$color, $ic, $value, $label, $sub])
                <div class="h3-card {{ $color }}" data-tilt>
                    <div class="h3-card-light"></div>
                    <div class="h3-iso">
                        <div class="h3-iso-cube">
                            <span class="i-top"></span>
                            <span class="i-left">{!! icon($ic) !!}</span>
                            <span class="i-right"></span>
                        </div>
                    </div>
                    <div class="h3-card-body">
                        <div class="h3-value" data-count="{{ (int) $value }}">{{ number_format($value) }}</div>
                        <div class="h3-label">{{ $label }}</div>
                        <div class="h3-sub">{{ $sub }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ================= TÀI LIỆU XEM GẦN ĐÂY (của riêng người dùng) ================= --}}
        @if ($canDocs)
            <section class="h3-recent card">
                <div class="card-header">
                    <div class="card-title">{!! icon('clock') !!} Tài liệu bạn xem gần đây</div>
                    @if ($recentTotal > $recentDocs->count())
                        <a href="{{ route('documents.index', ['scope' => 'recent']) }}" class="btn btn-xs btn-secondary">Xem tất cả {{ $recentTotal }}</a>
                    @endif
                </div>
                @if ($recentDocs->isEmpty())
                    <div class="empty-state" style="padding:28px">{!! icon('clock') !!}<p>Bạn chưa xem tài liệu nào trong {{ $recentDays }} ngày qua.</p></div>
                @else
                    <div class="h3-recent-grid">
                        @foreach ($recentDocs as $doc)
                            <a href="{{ route('documents.show', $doc->id) }}" class="h3-recent-item">
                                {!! file_icon($doc->extension) !!}
                                <div class="h3-recent-main">
                                    <div class="h3-recent-title" title="{{ $doc->title }}">{{ $doc->title }}</div>
                                    <div class="h3-recent-meta">
                                        <span class="mono">{{ $doc->device_code }}</span>
                                        @if ($doc->type_name)
                                            · <span class="type-pill"><i style="background:{{ $doc->type_color ?? '#94a3b8' }}"></i>{{ $doc->type_name }}</span>
                                        @endif
                                    </div>
                                </div>
                                <span class="h3-recent-time" title="{{ \App\Support\Labels::datetime($doc->last_seen) }}">{{ \App\Support\Labels::ago($doc->last_seen) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Đếm số từ 0 lên giá trị thật
            document.querySelectorAll('.h3-value[data-count]').forEach(function (el) {
                var target = parseInt(el.dataset.count, 10) || 0;
                if (still || target === 0) { return; }
                var start = null, dur = 1100;
                var step = function (t) {
                    if (!start) { start = t; }
                    var p = Math.min(1, (t - start) / dur);
                    el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString('vi-VN');
                    if (p < 1) { requestAnimationFrame(step); }
                };
                el.textContent = '0';
                requestAnimationFrame(step);
            });

            if (still) { return; }

            // Card nghiêng 3D theo vị trí chuột + vệt sáng chạy theo
            document.querySelectorAll('[data-tilt]').forEach(function (card) {
                card.addEventListener('mousemove', function (e) {
                    var r = card.getBoundingClientRect();
                    var x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
                    card.style.setProperty('--rx', ((0.5 - y) * 14).toFixed(2) + 'deg');
                    card.style.setProperty('--ry', ((x - 0.5) * 18).toFixed(2) + 'deg');
                    card.style.setProperty('--mx', (x * 100).toFixed(1) + '%');
                    card.style.setProperty('--my', (y * 100).toFixed(1) + '%');
                    card.classList.add('is-tilting');
                });
                card.addEventListener('mouseleave', function () {
                    card.style.setProperty('--rx', '0deg');
                    card.style.setProperty('--ry', '0deg');
                    card.classList.remove('is-tilting');
                });
            });
        })();
    </script>
@endpush
