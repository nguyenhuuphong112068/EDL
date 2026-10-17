@extends('layouts.app')
@php use App\Support\Labels; use App\Support\FileType; @endphp
@section('title', $doc->title)
@section('page-title', 'Chi tiết tài liệu')

@section('content')
    <div class="page-header">
        <div style="min-width:0">
            <div class="breadcrumb">
                @if (user_can('devices.view'))
                    <a href="{{ route('devices.index') }}">Thiết bị</a>
                @else
                    <a href="{{ route('documents.index') }}">Tra cứu tài liệu</a>
                @endif
                {!! icon('chevron-right', '', ['style' => 'width:12px;height:12px']) !!}
                @if ($canManage && user_can('devices.view'))
                    <a href="{{ route('devices.show', $doc->device_id) }}">{{ $doc->device_code }}</a>
                @else
                    <span>{{ $doc->device_code }}</span>
                @endif
                {!! icon('chevron-right', '', ['style' => 'width:12px;height:12px']) !!} <span>{{ $doc->type_name ?? 'Chưa phân loại' }}</span>
            </div>
            <div class="d-flex align-center gap-12">
                {!! file_icon($doc->extension, 'lg') !!}
                <div style="min-width:0">
                    <h2 style="line-height:1.3">{{ $doc->title }}</h2>
                    <p><span class="mono fw-700">{{ $doc->code }}</span> · {{ $doc->original_filename }} · {{ FileType::humanSize($doc->file_size) }}</p>
                    <div class="d-flex gap-6 flex-wrap mt-8">
                        <span class="badge {{ Labels::documentStatusBadge($doc->status) }}">{{ Labels::documentStatus($doc->status) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-secondary" data-copy="{{ route('documents.show', $doc->id) }}">{!! icon('link') !!} Sao chép liên kết</button>
            @if (user_can('documents.download'))
                <a href="{{ route('documents.download', $doc->id) }}" class="btn btn-primary">{!! icon('download') !!} Tải xuống</a>
            @endif
        </div>
    </div>

    @if (! $canManage)
        <div class="flash-alert info">{!! icon('info') !!}<div>Tài liệu công khai của bộ phận <b>{{ $doc->department_name }}</b> — bạn chỉ có quyền xem / tải, không sửa được.</div></div>
    @endif
    @if ($duplicates->isNotEmpty())
        <div class="flash-alert warning">{!! icon('copy') !!}
            <div>File này có nội dung <b>trùng hoàn toàn</b> (cùng mã SHA-256) với:
                @foreach ($duplicates as $dup)
                    <a href="{{ route('documents.show', $dup->id) }}">{{ $dup->code }}</a> ({{ $dup->device_code }})@if (! $loop->last), @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-main-side">
        {{-- ========== XEM TRƯỚC ========== --}}
        <div class="card" style="min-width:0">
            <div class="card-header">
                <div class="card-title">{!! icon('eye') !!} Xem trước</div>
                <div class="d-flex align-center gap-8">
                    <span class="text-xs text-muted">{{ FileType::groupLabel(FileType::group($doc->extension)) }} · .{{ $doc->extension }}</span>
                    @if (in_array($preview['mode'], ['pdf', 'image', 'video', 'audio', 'text', 'docx', 'xlsx'], true))
                        <button type="button" class="btn btn-xs btn-secondary" data-fullscreen="#doc-preview" title="Xem toàn màn hình (phím F, Esc để thoát)">{!! icon('maximize') !!} <span>Toàn màn hình</span></button>
                    @endif
                </div>
            </div>
            <div class="card-body" style="padding:12px">
                <div class="preview-box" id="doc-preview" data-title="{{ $doc->title }}">
                    @switch($preview['mode'])
                        @case('pdf')
                            @include('documents.partials._pdf-viewer')
                        @break
                        @case('image')
                            <img src="{{ route('documents.preview', $doc->id) }}" alt="{{ $doc->title }}">
                        @break
                        @case('video')
                            <video src="{{ route('documents.preview', $doc->id) }}" controls preload="metadata"></video>
                        @break
                        @case('audio')
                            <audio src="{{ route('documents.preview', $doc->id) }}" controls preload="metadata"></audio>
                        @break
                        @case('text')
                            <pre class="preview-text">{{ $preview['text'] }}</pre>
                        @break
                        @case('docx')
                            @if (in_array(strtolower($doc->extension), \App\Http\Controllers\Documents\DocumentController::WORD_RENDERED, true))
                                @include('documents.partials._docx-viewer')
                                @break
                            @endif
                            <div class="preview-doc">
                                @forelse ($preview['paragraphs'] as $p)
                                    @if ($p['style'] === 'slide')
                                        <div class="form-section-title" style="margin-top:16px">{{ $p['text'] }}</div>
                                    @elseif ($p['style'] === 'h')
                                        <h3 style="margin:12px 0 8px">{{ $p['text'] }}</h3>
                                    @elseif ($p['style'] === 'b')
                                        <p><b>{{ $p['text'] }}</b></p>
                                    @else
                                        <p>{{ $p['text'] }}</p>
                                    @endif
                                @empty
                                    <p class="text-muted">Không đọc được nội dung để xem trước. Vui lòng tải file xuống.</p>
                                @endforelse
                            </div>
                        @break
                        @case('xlsx')
                            @if ($preview['sheets'])
                                <div class="sheet-tabs">
                                    @foreach ($preview['sheets'] as $i => $sheet)
                                        <button type="button" class="btn btn-xs {{ $i === 0 ? 'btn-primary' : 'btn-secondary' }}" data-sheet="{{ $i }}">{{ $sheet['name'] }}</button>
                                    @endforeach
                                </div>
                                @foreach ($preview['sheets'] as $i => $sheet)
                                    <div class="preview-sheet" data-sheet-panel="{{ $i }}" @if ($i > 0) hidden @endif>
                                        <table>
                                            @foreach ($sheet['rows'] as $r => $row)
                                                <tr>
                                                    <th style="width:36px;text-align:center;color:#94a3b8">{{ $r + 1 }}</th>
                                                    @foreach ($row as $cell)
                                                        <td>{{ $cell }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </table>
                                        @if ($sheet['truncated'])
                                            <div class="text-xs text-muted" style="padding:8px">Chỉ hiển thị 300 dòng đầu — tải file để xem đầy đủ.</div>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="preview-none">Không đọc được bảng tính để xem trước.</div>
                            @endif
                        @break
                        @case('missing')
                            <div class="preview-none">{!! icon('alert', '', ['style' => 'width:40px;height:40px;color:#dc2626']) !!}<p>File không còn trên máy chủ lưu trữ. Vui lòng liên hệ quản trị.</p></div>
                        @break
                        @default
                            <div class="preview-none">
                                {!! file_icon($doc->extension, 'lg') !!}
                                <p class="fw-600">Định dạng .{{ $doc->extension }} chưa xem trực tuyến được</p>
                                <p class="text-sm">Tải file xuống và mở bằng phần mềm tương ứng (AutoCAD, Office, trình giải nén…).</p>
                                @if (user_can('documents.download'))
                                    <a href="{{ route('documents.download', $doc->id) }}" class="btn btn-primary mt-16">{!! icon('download') !!} Tải xuống ({{ FileType::humanSize($doc->file_size) }})</a>
                                @endif
                            </div>
                    @endswitch
                </div>
                @if ($doc->is_indexed)
                    <div class="text-xs text-muted mt-8">{!! icon('check-circle', '', ['style' => 'width:12px;height:12px;vertical-align:-2px;color:#16a34a']) !!} Nội dung file đã được đánh chỉ mục — tìm được bằng từ khoá bên trong tài liệu.</div>
                @endif
            </div>
        </div>

        {{-- ========== THÔNG TIN ========== --}}
        <div style="display:flex;flex-direction:column;gap:16px;min-width:0">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">{!! icon('info') !!} Thông tin tài liệu</div>
                    @if ($canManage && user_can('documents.update'))
                        <a href="{{ route('documents.edit', $doc->id) }}" class="btn btn-xs btn-outline-primary">{!! icon('edit') !!} Sửa</a>
                    @endif
                </div>
                <div class="card-body">
                    <dl class="detail-list">
                        <dt>Thiết bị</dt>
                        <dd><span class="mono fw-700">{{ $device->code }}</span> — {{ $device->name }}
                            <div class="text-xs text-muted">{{ Labels::deviceStatus($device->status) }}</div>
                        </dd>
                        <dt>Bộ phận</dt><dd>{{ $doc->department_name }}</dd>
                        <dt>Loại tài liệu</dt><dd>@if ($doc->type_name)<span class="type-pill"><i style="background:{{ $doc->type_color }}"></i>{{ $doc->type_name }}</span>@else — @endif</dd>
                        <dt>Người tải lên</dt><dd>{{ $doc->uploader_name ?? '—' }}<div class="text-xs text-muted">{{ Labels::datetime($doc->created_at) }}</div></dd>
                        <dt>Cập nhật lần cuối</dt><dd>{{ Labels::datetime($doc->updated_at) }}@if ($doc->revision_count > 1)<div class="text-xs text-muted">Đã thay file {{ $doc->revision_count - 1 }} lần</div>@endif</dd>
                        <dt>Lượt xem / tải</dt><dd>{{ number_format($doc->view_count + 1) }} / {{ number_format($doc->download_count) }}</dd>
                        <dt>Mã kiểm tra</dt><dd class="mono text-xs" title="SHA-256" style="word-break:break-all">{{ $doc->checksum }}</dd>
                    </dl>
                    @if ($doc->description)
                        <hr class="divider">
                        <div class="text-sm" style="white-space:pre-line">{{ $doc->description }}</div>
                    @endif
                </div>
                @if ($canManage && (user_can('documents.update') || user_can('documents.delete')))
                    <div class="card-footer btn-group">
                        @if (user_can('documents.update'))
                            <button type="button" class="btn btn-sm btn-primary" data-modal-open="version-modal">{!! icon('upload') !!} Cập nhật file mới</button>
                        @endif
                        @if (user_can('documents.delete'))
                            <form method="POST" action="{{ route('documents.destroy', $doc->id) }}" data-confirm="Chuyển tài liệu này sang hết hiệu lực? Tài liệu sẽ ẩn khỏi tra cứu, khôi phục được ở mục Tài liệu hết hiệu lực.">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{!! icon('archive') !!} Hết hiệu lực</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Lịch sử cập nhật file --}}
            <div class="card">
                <div class="card-header"><div class="card-title">{!! icon('history') !!} Lịch sử cập nhật file</div><span class="badge badge-secondary">{{ $versions->count() }}</span></div>
                <div class="card-body" style="padding:6px 16px">
                    <div class="timeline">
                        @foreach ($versions as $v)
                            @php $isCurrent = $v->file_path === $doc->file_path && $loop->first; @endphp
                            <div class="timeline-item">
                                <div class="timeline-dot {{ $isCurrent ? 'current' : '' }}">{{ $v->revision }}</div>
                                <div style="flex:1;min-width:0">
                                    <div class="fw-600 truncate" title="{{ $v->original_filename }}">{{ $v->original_filename }}</div>
                                    <div class="text-xs text-muted">
                                        Lần {{ $v->revision }} · {{ FileType::humanSize($v->file_size) }} · {{ $v->uploader_name }} · {{ Labels::datetime($v->created_at) }}
                                    </div>
                                    @if ($v->change_note)
                                        <div class="text-xs" style="margin-top:3px">{{ $v->change_note }}</div>
                                    @endif
                                    <div class="btn-group" style="margin-top:6px">
                                        @if ($isCurrent)
                                            <span class="badge badge-primary">Bản hiện hành</span>
                                        @endif
                                        @if (user_can('documents.download'))
                                            <a href="{{ route('documents.versions.download', [$doc->id, $v->id]) }}" class="btn btn-xs btn-secondary">{!! icon('download') !!} Tải</a>
                                        @endif
                                        @if (! $isCurrent && $canManage && user_can('documents.update'))
                                            <form method="POST" action="{{ route('documents.versions.restore', [$doc->id, $v->id]) }}" data-confirm="Khôi phục file của lần {{ $v->revision }} làm bản hiện hành?">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-outline-primary">{!! icon('restore') !!} Khôi phục hiệu lực</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if ($related->isNotEmpty())
                <div class="card">
                    <div class="card-header"><div class="card-title">{!! icon('folder') !!} Tài liệu khác của thiết bị</div></div>
                    <div class="folder-list">
                        @foreach ($related as $r)
                            <a href="{{ route('documents.show', $r->id) }}" class="folder-item" style="gap:8px">
                                {!! file_icon($r->extension) !!}
                                <div style="min-width:0"><div class="truncate fw-600 text-sm">{{ $r->title }}</div><div class="text-xs text-muted">{{ $r->type_name }}</div></div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (user_can('audit.view') && $activities->isNotEmpty())
                <div class="card">
                    <div class="card-header"><div class="card-title">{!! icon('clock') !!} Nhật ký thao tác</div></div>
                    <div class="card-body" style="padding:6px 16px;max-height:320px;overflow:auto">
                        @foreach ($activities as $a)
                            <div class="timeline-item" style="padding:7px 0">
                                <div style="min-width:0">
                                    <div class="text-sm"><b>{{ $a->userName }}</b> — {{ $a->action }}</div>
                                    <div class="text-xs text-muted">{{ Labels::datetime($a->created_at) }} · {{ $a->ip_address }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Cập nhật file mới --}}
    @if ($canManage && user_can('documents.update'))
        <div class="modal" id="version-modal">
            <form method="POST" action="{{ route('documents.versions.store', $doc->id) }}" enctype="multipart/form-data" class="modal-dialog">
                @csrf
                <div class="modal-header"><div class="modal-title">Cập nhật file mới cho tài liệu</div><button type="button" class="modal-close" data-modal-close>&times;</button></div>
                <div class="modal-body">
                    <div class="flash-alert info" style="margin-bottom:14px">{!! icon('info') !!}<div>File hiện tại được giữ lại trong lịch sử cập nhật file và có thể khôi phục.</div></div>
                    <div class="form-group">
                        <label class="form-label required">File mới</label>
                        <input type="file" name="file" class="form-control" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Nội dung thay đổi</label>
                        <textarea name="change_note" class="form-textarea" rows="3" placeholder="Vd: Cập nhật theo đợt hiệu chuẩn tháng 10/2026"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
                    <button type="submit" class="btn btn-primary">{!! icon('upload') !!} Cập nhật</button>
                </div>
            </form>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-sheet]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('[data-sheet]').forEach(function (b) { b.className = 'btn btn-xs ' + (b === btn ? 'btn-primary' : 'btn-secondary'); });
                document.querySelectorAll('[data-sheet-panel]').forEach(function (p) { p.hidden = p.dataset.sheetPanel !== btn.dataset.sheet; });
            });
        });
    </script>
@endpush
