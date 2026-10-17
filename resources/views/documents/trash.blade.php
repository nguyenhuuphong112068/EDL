@extends('layouts.app')
@php use App\Support\Labels; @endphp
@section('title', 'Tài liệu hết hiệu lực')
@section('page-title', 'Thiết bị — Tài liệu hết hiệu lực')

@section('content')
    <div class="page-header">
        <div>
            <h2>Tài liệu hết hiệu lực</h2>
            <p>Tổng dung lượng: <b>{{ $totalSize }}</b>.</p>
        </div>
    </div>

    <div class="card">
        <form method="GET" class="filter-bar">
            @if (request('device_id'))<input type="hidden" name="device_id" value="{{ request('device_id') }}">@endif
            <div class="filter-group grow">
                <label>Tìm kiếm</label>
                <input type="search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Tên, mã tài liệu…">
            </div>
            <div class="filter-group"><label>&nbsp;</label><button class="btn btn-primary">{!! icon('search') !!} Tìm</button></div>
        </form>

        <form method="POST" id="trash-form">
            @csrf
            <div class="bulk-bar">
                <span class="bulk-count">Đã chọn 0</span>
                <button type="submit" formaction="{{ route('trash.restore') }}" class="btn btn-sm btn-primary">{!! icon('restore') !!} Khôi phục hiệu lực</button>
                @if (user_can('documents.force_delete'))
                    <button type="submit" formaction="{{ route('trash.destroy') }}" name="_method" value="DELETE" class="btn btn-sm btn-danger"
                        data-confirm="XOÁ VĨNH VIỄN các tài liệu đã chọn và toàn bộ file / phiên bản? Không thể hoàn tác.">{!! icon('trash') !!} Xoá vĩnh viễn</button>
                @endif
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th class="col-check"><input type="checkbox" data-check-all></th>
                            <th>Tài liệu</th>
                            <th>Thiết bị</th>
                            <th>Loại</th>
                            <th>Chuyển bởi</th>
                            <th>Hết hiệu lực từ</th>
                            <th class="text-right">Dung lượng</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $doc)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $doc->id }}" data-check-item></td>
                                <td><div class="file-cell">{!! file_icon($doc->extension) !!}<div><div class="fw-600">{{ $doc->title }}</div><div class="cell-sub mono">{{ $doc->code }}</div></div></div></td>
                                <td><span class="mono fw-600">{{ $doc->device_code }}</span><div class="cell-sub">{{ $doc->device_name }}</div></td>
                                <td>{{ $doc->type_name ?? '—' }}</td>
                                <td>{{ $doc->deleted_by_name ?? '—' }}</td>
                                <td class="nowrap">{{ Labels::datetime($doc->deleted_at) }}<div class="cell-sub">{{ Labels::ago($doc->deleted_at) }}</div></td>
                                <td class="text-right text-sm text-muted">{{ \App\Support\FileType::humanSize($doc->file_size) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state">{!! icon('archive') !!}<p>Không có tài liệu hết hiệu lực.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        {{ $documents->links() }}
    </div>
@endsection
