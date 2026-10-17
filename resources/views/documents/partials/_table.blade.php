{{--
    Bảng tài liệu dùng chung: trang tra cứu + trang thiết bị.
    Biến: $documents, $snippets (tuỳ chọn), $showDevice (mặc định true), $term (từ khoá tô sáng)
--}}
@php
    use App\Support\Labels;
    $showDevice = $showDevice ?? true;
    $term       = $term ?? '';
    $snippets   = $snippets ?? [];
@endphp
<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th class="col-check"><input type="checkbox" data-check-all title="Chọn tất cả"></th>
                <th>Tài liệu</th>
                <th>Loại</th>
                @if ($showDevice)
                    <th>Thiết bị</th>
                @endif
                <th>Tình trạng</th>
                <th class="text-right">Dung lượng</th>
                <th class="col-actions"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($documents as $doc)
                <tr>
                    <td class="col-check"><input type="checkbox" data-check-item value="{{ $doc->id }}"></td>
                    <td>
                        <div class="file-cell">
                            {!! file_icon($doc->extension) !!}
                            <div>
                                <a href="{{ route('documents.show', $doc->id) }}" class="cell-title">{!! highlight($doc->title, $term) !!}</a>
                                <div class="cell-sub">
                                    <span class="mono">{!! highlight($doc->code, $term) !!}</span>
                                    · {{ Labels::date($doc->created_at) }}
                                </div>
                                @if (! empty($snippets[$doc->id]))
                                    <div class="snippet">{!! icon('search', '', ['style' => 'width:11px;height:11px;vertical-align:-1px']) !!} {!! highlight($snippets[$doc->id], $term) !!}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        @if ($doc->type_name)
                            <span class="type-pill"><i style="background:{{ $doc->type_color }}"></i>{{ $doc->type_name }}</span>
                        @else
                            <span class="text-muted text-sm">Chưa phân loại</span>
                        @endif
                    </td>
                    @if ($showDevice)
                        <td>
                            @if (works_on_department($doc->department_id) && user_can('devices.view'))
                                <a href="{{ route('devices.show', $doc->device_id) }}" class="fw-600 mono">{!! highlight($doc->device_code, $term) !!}</a>
                            @else
                                <span class="fw-600 mono">{{ $doc->device_code }}</span>
                            @endif
                            <div class="cell-sub truncate" style="max-width:200px" title="{{ $doc->device_name }}">{{ $doc->device_name }}</div>
                            @if (! works_on_department($doc->department_id))
                                <div class="cell-sub">{!! icon('building', '', ['style' => 'width:11px;height:11px;vertical-align:-1px']) !!} {{ $doc->department_code }}</div>
                            @endif
                        </td>
                    @endif
                    <td><span class="badge {{ Labels::documentStatusBadge($doc->status) }}">{{ Labels::documentStatus($doc->status) }}</span></td>
                    <td class="text-right nowrap text-sm text-muted">{{ \App\Support\FileType::humanSize($doc->file_size) }}</td>
                    <td class="col-actions">
                        <div class="btn-group">
                            <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-xs btn-secondary" title="Xem">{!! icon('eye') !!}</a>
                            @if (user_can('documents.download'))
                                <a href="{{ route('documents.download', $doc->id) }}" class="btn btn-xs btn-secondary" title="Tải xuống">{!! icon('download') !!}</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showDevice ? 7 : 6 }}">
                        <div class="empty-state">
                            {!! icon('search') !!}
                            <p>{{ $emptyText ?? 'Không tìm thấy tài liệu phù hợp.' }}</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
