@extends('layouts.app')
@section('title', 'In nhãn QR thiết bị')
@section('page-title', 'In nhãn QR thiết bị')

@section('content')
    <div class="page-header no-print">
        <div>
            <h2>Nhãn QR thiết bị ({{ $labels->count() }})</h2>
            <p>Dán nhãn lên thiết bị — quét bằng camera điện thoại để mở hồ sơ tài liệu (cần đăng nhập & đúng quyền).</p>
        </div>
        <button type="button" class="btn btn-primary" onclick="window.print()">{!! icon('printer') !!} In nhãn</button>
    </div>

    <div class="qr-sheet">
        @foreach ($labels as $label)
            @php $d = $label['device']; @endphp
            <div class="qr-label">
                <div class="qr">{!! $label['svg'] !!}</div>
                <div style="min-width:0">
                    <div class="code mono">{{ $d->code }}</div>
                    <div class="name">{{ $d->name }}</div>
                    <div class="meta">
                        {{ $d->department_name }}
                    </div>
                    <div class="meta" style="margin-top:6px;font-weight:700;color:#2563eb;display:flex;align-items:center;gap:5px">
                        <img src="{{ asset('img/iconstella.svg') }}" alt="Stellapharm" style="width:16px;height:auto">
                        Stellapharm · EDL
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
