@extends('layouts.app')
@section('title', 'Sửa tài liệu')
@section('page-title', 'Sửa thông tin tài liệu')

@section('content')
    @php $hideErrorSummary = true; @endphp
    <div style="max-width:980px">
        <div class="page-header">
            <div>
                <div class="breadcrumb"><a href="{{ route('documents.show', $doc->id) }}">{{ $doc->code }}</a></div>
                <h2>Sửa thông tin tài liệu</h2>
                <p>Muốn thay file, dùng nút "Cập nhật file mới" ở trang chi tiết — bản cũ được giữ trong lịch sử cập nhật file.</p>
            </div>
            <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-secondary">← Quay lại</a>
        </div>

        <form method="POST" action="{{ route('documents.update', $doc->id) }}">
            @csrf @method('PUT')
            <div class="card mb-16">
                <div class="card-body">
                    <div class="form-section-title">Thông tin chung</div>
                    <div class="form-group">
                        <label class="form-label required">Tiêu đề tài liệu</label>
                        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $doc->title) }}" required maxlength="500">
                        @error('title')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-row cols-2">
                        <div class="form-group">
                            <label class="form-label required">Thiết bị</label>
                            <select name="device_id" class="form-select @error('device_id') is-invalid @enderror" required>
                                @foreach ($devices as $d)
                                    <option value="{{ $d->id }}" @selected(old('device_id', $doc->device_id) == $d->id)>{{ $d->code }} — {{ $d->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-hint">Đổi thiết bị = chuyển tài liệu sang hồ sơ thiết bị khác.</div>
                            @error('device_id')<div class="form-error">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Loại tài liệu</label>
                            <select name="document_type_id" class="form-select">
                                <option value="">— Chưa phân loại —</option>
                                @foreach ($documentTypes as $t)
                                    <option value="{{ $t->id }}" @selected(old('document_type_id', $doc->document_type_id) == $t->id)>{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row cols-2">
                        <div class="form-group">
                            <label class="form-label required">Tình trạng</label>
                            <select name="status" class="form-select">
                                @foreach (config('edl.document_statuses') as $key => $s)
                                    <option value="{{ $key }}" @selected(old('status', $doc->status) === $key)>{{ $s['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Mô tả / ghi chú</label>
                        <textarea name="description" class="form-textarea" rows="4">{{ old('description', $doc->description) }}</textarea>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{!! icon('check') !!} Lưu thay đổi</button>
                <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-secondary">Hủy</a>
            </div>
        </form>
    </div>
@endsection
