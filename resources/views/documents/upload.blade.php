@extends('layouts.app')
@section('title', 'Tải lên tài liệu')
@section('page-title', 'Thiết bị — Tải lên tài liệu')

@section('content')
    <div class="page-header">
        <div>
            @if ($presetDevice)
                <h2>Tải tài liệu cho <span class="mono">{{ $presetDevice->code }}</span> — {{ $presetDevice->name }}</h2>
                <p>Mọi file chọn ở đây đều được lưu vào thiết bị này.</p>
            @else
                <h2>Tải tài liệu hàng loạt</h2>
                <p>Thiết bị được nhận diện theo mã thiết bị trong tên file — kiểm tra lại trên lưới trước khi lưu.</p>
            @endif
        </div>
        @if ($presetDevice)
            <a href="{{ route('devices.show', $presetDevice->id) }}" class="btn btn-secondary">← Về thiết bị {{ $presetDevice->code }}</a>
        @endif
    </div>

    @if ($devices->isEmpty())
        <div class="flash-alert warning">{!! icon('alert') !!}<div>Bộ phận đang làm việc chưa có thiết bị nào. Hãy <a href="{{ route('devices.index') }}">thêm thiết bị</a> ở trang Danh sách thiết bị trước khi tải tài liệu.</div></div>
    @endif

    <div class="card mb-16">
        <div class="card-body">
            <label class="dropzone" id="dropzone">
                <input type="file" id="file-input" multiple hidden>
                <span class="dropzone-icon">{!! icon('cloud-up') !!}</span>
                <h3>Kéo thả file vào đây</h3>
                <p>hoặc</p>
                <span class="btn btn-primary dropzone-btn">{!! icon('folder') !!} Chọn file từ máy tính</span>
                <p class="dropzone-limit">
                    Tối đa <b>{{ config('edl.storage.max_batch_files') }} file</b> mỗi lượt · mỗi file ≤ <b>{{ \App\Support\FileType::humanSize(config('edl.storage.max_file_kb') * 1024) }}</b>
                </p>
                <div class="dropzone-formats">
                    @foreach (['PDF', 'Word', 'Excel', 'PowerPoint', 'Ảnh', 'CAD (DWG, DXF, STEP)', 'Video', 'Âm thanh', 'File nén', 'Văn bản', 'PLC'] as $f)
                        <span>{{ $f }}</span>
                    @endforeach
                </div>
            </label>
        </div>
    </div>

    <div class="card" id="upload-card" hidden>
        <div class="card-header">
            <div class="card-title">{!! icon('list') !!} Danh sách file</div>
            <div class="upload-summary" id="upload-summary"></div>
        </div>

        <div class="table-wrap">
            <table class="upload-table {{ $presetDevice ? 'fixed-device' : '' }}">
                <thead>
                    <tr>
                        <th>File</th>
                        <th style="min-width:260px">Tiêu đề tài liệu *</th>
                        @unless ($presetDevice)
                            <th style="min-width:220px">Thiết bị *</th>
                        @endunless
                        <th style="min-width:190px">Loại tài liệu</th>
                        <th style="width:130px">Tình trạng</th>
                        <th style="min-width:200px">Mô tả</th>
                        <th style="width:56px" class="text-center">Bỏ</th>
                    </tr>
                </thead>
                <tbody id="upload-rows"></tbody>
            </table>
        </div>

        <div class="sticky-actions">
            <div class="text-sm text-muted" id="save-hint">Dòng viền vàng: file trùng nội dung với tài liệu đã có. Dòng đỏ: cần sửa hoặc bấm ✕ để bỏ.</div>
            <div class="btn-group">
                <button type="button" class="btn btn-secondary" id="add-more">{!! icon('plus') !!} Thêm file</button>
                <button type="button" class="btn btn-success btn-lg" id="save-all">{!! icon('check') !!} Lưu tất cả</button>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    @php
        $uploadConfig = [
            'tempUrl'     => route('uploads.temp'),
            'discardUrl'  => url('uploads/temp'),
            'saveUrl'     => route('uploads.store'),
            'maxBytes'    => (int) config('edl.storage.max_file_kb') * 1024,
            'maxFiles'    => (int) config('edl.storage.max_batch_files'),
            'allowed'     => $allowed,
            'fixedDevice' => $presetDevice ? (int) $presetDevice->id : null,
            'presetType'  => $presetType,
            'devices'     => $devices->map(fn ($d) => ['id' => (int) $d->id, 'label' => $d->code . ' — ' . $d->name])->values(),
            'types'       => $documentTypes->map(fn ($t) => ['id' => (int) $t->id, 'label' => $t->name])->values(),
            'statuses'    => collect(config('edl.document_statuses'))->map(fn ($s) => $s['label']),
        ];
    @endphp
    <script>
        window.EDL_UPLOAD = @json($uploadConfig);
    </script>
    <script src="{{ asset('js/bulk-upload.js') }}?v={{ config('edl.version') }}"></script>
@endpush
