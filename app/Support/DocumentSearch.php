<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TÌM KIẾM TÀI LIỆU — áp bộ lọc từ request lên truy vấn gốc DocumentScope.
 * Dùng chung cho trang tra cứu, xuất Excel, thao tác hàng loạt, tìm nhanh.
 *
 * Từ khoá nhiều từ: MỖI từ phải khớp ít nhất một trường (tiêu đề, mã, mô tả, tên file,
 * mã/tên thiết bị, và — nếu chọn — nội dung file).
 */
class DocumentSearch
{
    /** Các tham số lọc được lưu vào "Bộ lọc đã lưu" */
    public const FILTER_KEYS = [
        'q', 'in', 'scope', 'device_id', 'department_id',
        'document_type_id', 'file_group', 'status', 'uploaded_by',
        'date_from', 'date_to', 'sort', 'view',
    ];

    public const SORTS = [
        'newest'  => 'Mới tải lên',
        'updated' => 'Mới cập nhật',
        'title'   => 'Tên A → Z',
        'code'    => 'Mã tài liệu',
        'device'  => 'Mã thiết bị',
        'views'   => 'Xem nhiều nhất',
        'size'    => 'Dung lượng lớn',
    ];

    public static function build(Request $request): Builder
    {
        $query = DocumentScope::query()->select(DocumentScope::LIST_COLUMNS);

        self::applyScope($query, $request->input('scope'));
        self::applyKeyword($query, (string) $request->input('q'), $request->input('in', 'all'));
        self::applyFilters($query, $request);
        self::applySort($query, $request->input('sort'), $request->input('scope'));

        return $query;
    }

    public static function applyScope(Builder $query, ?string $scope): void
    {
        $userId = auth()->id();

        match ($scope) {
            'recent'    => $query->joinSub(
                DB::table('document_activities')->where('user_id', $userId)
                    ->groupBy('document_id')->selectRaw('document_id, MAX(created_at) as last_seen'),
                'recent', 'recent.document_id', '=', 'documents.id'
            ),
            'mine'      => $query->where('documents.uploaded_by', $userId),
            default     => null,
        };
    }

    public static function applyKeyword(Builder $query, string $term, ?string $in = 'all'): void
    {
        $words = self::words($term);

        foreach ($words as $word) {
            $like = '%' . $word . '%';

            $query->where(function (Builder $q) use ($like, $in) {
                if ($in !== 'content') {
                    $q->orWhere('documents.title', 'like', $like)
                        ->orWhere('documents.code', 'like', $like)
                        ->orWhere('documents.description', 'like', $like)
                        ->orWhere('documents.original_filename', 'like', $like)
                        ->orWhere('devices.code', 'like', $like)
                        ->orWhere('devices.name', 'like', $like);
                }

                if ($in === 'all' || $in === 'content') {
                    $q->orWhere('documents.content_text', 'like', $like);
                }
            });
        }
    }

    public static function applyFilters(Builder $query, Request $request): void
    {
        $map = [
            'device_id'        => 'documents.device_id',
            'department_id'    => 'devices.department_id',
            'document_type_id' => 'documents.document_type_id',
            'status'           => 'documents.status',
            'uploaded_by'      => 'documents.uploaded_by',
        ];

        foreach ($map as $param => $column) {
            $value = $request->input($param);
            if (is_array($value)) {
                $value = array_filter($value, fn ($v) => $v !== null && $v !== '');
                if ($value) {
                    $query->whereIn($column, $value);
                }
            } elseif ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }

        if ($request->filled('file_group')) {
            $query->whereIn('documents.extension', FileType::extensionsOfGroup($request->file_group) ?: ['-']);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('documents.created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('documents.created_at', '<=', $request->date_to);
        }
    }

    public static function applySort(Builder $query, ?string $sort, ?string $scope): void
    {
        if ($scope === 'recent' && ! $sort) {
            $query->orderByDesc('recent.last_seen');

            return;
        }

        match ($sort) {
            'updated' => $query->orderByDesc('documents.updated_at'),
            'title'   => $query->orderBy('documents.title'),
            'code'    => $query->orderBy('documents.code'),
            'device'  => $query->orderBy('devices.code')->orderBy('documents.title'),
            'views'   => $query->orderByDesc('documents.view_count'),
            'size'    => $query->orderByDesc('documents.file_size'),
            default   => $query->orderByDesc('documents.created_at')->orderByDesc('documents.id'),
        };
    }

    /** @return array<int, string> */
    public static function words(string $term): array
    {
        $term = trim(mb_substr($term, 0, 200));
        if ($term === '') {
            return [];
        }

        // "cụm từ trong ngoặc kép" tìm nguyên cụm
        preg_match_all('/"([^"]+)"|(\S+)/u', $term, $m, PREG_SET_ORDER);

        return collect($m)->map(fn ($x) => $x[1] !== '' ? $x[1] : $x[2])
            ->map(fn ($w) => addcslashes($w, '%_'))
            ->filter(fn ($w) => mb_strlen($w) >= 1)
            ->take(8)
            ->values()
            ->all();
    }

    /** Đoạn trích quanh từ khoá đầu tiên tìm thấy trong nội dung file. */
    public static function snippet(?string $content, string $term, int $radius = 90): ?string
    {
        if (! $content || trim($term) === '') {
            return null;
        }

        foreach (self::words($term) as $word) {
            $word = stripslashes($word);
            $pos  = mb_stripos($content, $word);

            if ($pos === false) {
                $pos = mb_stripos(Str::ascii($content), Str::ascii($word));
            }

            if ($pos !== false) {
                $start = max(0, $pos - $radius);
                $text  = mb_substr($content, $start, $radius * 2 + mb_strlen($word));
                $text  = preg_replace('/\s+/u', ' ', $text);

                return ($start > 0 ? '… ' : '') . trim($text) . ' …';
            }
        }

        return null;
    }

    /** Danh sách bộ lọc đang áp dụng — để hiện thành "chip" có nút bỏ. */
    public static function activeFilters(Request $request, array $lookups): array
    {
        $chips  = [];
        $labels = [
            'device_id'        => ['Thiết bị', $lookups['devices'] ?? []],
            'department_id'    => ['Bộ phận', $lookups['departments'] ?? []],
            'document_type_id' => ['Loại tài liệu', $lookups['documentTypes'] ?? []],
            'uploaded_by'      => ['Người tải lên', $lookups['uploaders'] ?? []],
        ];

        foreach ($labels as $key => [$label, $options]) {
            if ($request->filled($key)) {
                $chips[$key] = $label . ': ' . ($options[$request->input($key)] ?? $request->input($key));
            }
        }

        $static = [
            'file_group'      => ['Định dạng', collect(config('edl.file_groups'))->map(fn ($g) => $g['label'])->all()],
            'status'          => ['Tình trạng', collect(config('edl.document_statuses'))->map(fn ($s) => $s['label'])->all()],
            'in'              => ['Tìm trong', ['title' => 'Thông tin tài liệu', 'content' => 'Nội dung file']],
        ];

        foreach ($static as $key => [$label, $options]) {
            if ($request->filled($key) && ! ($key === 'in' && $request->in === 'all')) {
                $chips[$key] = $label . ': ' . ($options[$request->input($key)] ?? $request->input($key));
            }
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $chips['date_from'] = 'Ngày tải lên: ' . ($request->date_from ?: '…') . ' → ' . ($request->date_to ?: '…');
        }

        return $chips;
    }
}
