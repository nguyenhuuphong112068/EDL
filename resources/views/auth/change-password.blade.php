@extends($forced ? 'layouts.auth' : 'layouts.app')
@section('title', 'Đổi mật khẩu')
@section('page-title', 'Đổi mật khẩu')

@section('content')
    @php
        $minLength = (int) config('edl.security.password_min_length');
        $daysLeft  = $expiresAt ? (int) floor(now()->diffInDays($expiresAt, false)) : null;
        $fields    = ['current_password' => 'Mật khẩu hiện tại', 'password' => 'Mật khẩu mới', 'password_confirmation' => 'Nhập lại mật khẩu mới'];
    @endphp

    <div class="{{ $forced ? '' : 'pw-page' }}">
        @if ($forced)
            <h1 class="auth-title">Đặt mật khẩu mới</h1>
            @if (session('error'))
                <div class="flash-alert warning">{!! icon('alert') !!}<div>{{ session('error') }}</div></div>
            @endif
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="pw-form {{ $forced ? '' : 'card pw-card' }}" id="pw-form" novalidate>
            @csrf

            @unless ($forced)
                <div class="pw-head">
                    <div class="pw-head-icon">{!! icon('key') !!}</div>
                    <div>
                        <h2>Đổi mật khẩu</h2>
                        <p>{{ auth()->user()->name }} · {{ auth()->user()->userName }}</p>
                        @if ($daysLeft !== null)
                            <span class="pw-expiry {{ $daysLeft < 0 ? 'bad' : ($daysLeft <= 14 ? 'warn' : 'ok') }}">
                                {!! icon('clock', '', ['style' => 'width:13px;height:13px']) !!}
                                @if ($daysLeft < 0)
                                    Mật khẩu đã hết hạn
                                @else
                                    Mật khẩu hiện tại còn hạn {{ $daysLeft }} ngày ({{ $expiresAt->format('d/m/Y') }})
                                @endif
                            </span>
                        @endif
                    </div>
                </div>
            @endunless

            @foreach ($fields as $field => $label)
                <div class="form-group">
                    <label class="form-label required" for="{{ $field }}">{{ $label }}</label>
                    <div class="input-icon">
                        {!! icon('lock', 'input-icon-lead') !!}
                        <input type="password" id="{{ $field }}" name="{{ $field }}" class="form-control @error($field) is-invalid @enderror"
                            autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" required {{ $loop->first ? 'autofocus' : '' }}>
                        <button type="button" class="input-icon-btn" data-pw-toggle="{{ $field }}" title="Hiện / ẩn mật khẩu">{!! icon('eye') !!}</button>
                    </div>
                    @error($field)<div class="form-error">{{ $message }}</div>@enderror
                    @if ($field === 'password')
                        <div class="pw-meter" id="pw-meter" data-level="0"><span></span><span></span><span></span><span></span></div>
                        <div class="pw-meter-label" id="pw-meter-label"></div>
                    @endif
                    @if ($field === 'password_confirmation')
                        <div class="form-error" id="pw-mismatch" hidden>Mật khẩu nhập lại không khớp.</div>
                    @endif
                </div>
            @endforeach

            <ul class="pw-rules" id="pw-rules">
                <li data-rule="length">Tối thiểu {{ $minLength }} ký tự</li>
                <li data-rule="upper">Có chữ hoa (A–Z)</li>
                <li data-rule="lower">Có chữ thường (a–z)</li>
                <li data-rule="digit">Có chữ số (0–9)</li>
                <li data-rule="special">Có ký tự đặc biệt (!@#…)</li>
                <li data-rule="differ">Khác mật khẩu hiện tại</li>
            </ul>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary {{ $forced ? 'auth-submit' : '' }}" id="pw-submit">{!! icon('check') !!} Lưu mật khẩu mới</button>
                @unless ($forced)
                    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="btn btn-secondary">Hủy</a>
                @endunless
            </div>
        </form>

        @if ($forced)
            <form method="POST" action="{{ route('logout') }}" style="margin-top:14px;text-align:center">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">{!! icon('logout') !!} Đăng xuất</button>
            </form>
        @endif
    </div>

    <script>
        (function () {
            var form = document.getElementById('pw-form');
            var cur = document.getElementById('current_password'), pw = document.getElementById('password'), confirm = document.getElementById('password_confirmation');
            var meter = document.getElementById('pw-meter'), meterLabel = document.getElementById('pw-meter-label');
            var mismatch = document.getElementById('pw-mismatch');
            var minLength = {{ $minLength }};
            var labels = ['', 'Yếu', 'Trung bình', 'Khá', 'Mạnh'];

            // Hiện / ẩn mật khẩu
            form.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = document.getElementById(btn.dataset.pwToggle);
                    input.type = input.type === 'password' ? 'text' : 'password';
                });
            });

            var check = function () {
                var v = pw.value;
                var rules = {
                    length: v.length >= minLength,
                    upper: /[A-Z]/.test(v),
                    lower: /[a-z]/.test(v),
                    digit: /\d/.test(v),
                    special: /[\W_]/.test(v),
                    differ: v !== '' && v !== cur.value
                };
                var passed = 0;
                Object.keys(rules).forEach(function (k) {
                    form.querySelector('[data-rule="' + k + '"]').classList.toggle('ok', rules[k]);
                    if (rules[k] && k !== 'differ') { passed++; }
                });
                var level = v === '' ? 0 : Math.max(1, Math.min(4, passed - 1 + (v.length >= minLength + 4 ? 1 : 0)));
                meter.dataset.level = level;
                meterLabel.textContent = v === '' ? '' : 'Độ mạnh: ' + labels[level];
                mismatch.hidden = confirm.value === '' || confirm.value === v;
                return passed === 5 && rules.differ && confirm.value === v;
            };
            [cur, pw, confirm].forEach(function (el) { el.addEventListener('input', check); });

            form.addEventListener('submit', function (e) {
                if (!cur.value || !check()) {
                    e.preventDefault();
                    (!cur.value ? cur : (confirm.value !== pw.value && pw.value ? confirm : pw)).focus();
                    if (window.EDL && EDL.toast) { EDL.toast('Mật khẩu mới chưa đạt đủ các điều kiện.', 'error'); }
                }
            });
            check();
        })();
    </script>
@endsection
