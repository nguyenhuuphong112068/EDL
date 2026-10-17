<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Danh sách dữ liệu gốc cho ô chọn — chỉ mục đang dùng, cộng mục đang được chọn của form sửa. */
class Lookup
{
    private const SORTABLE = ['departments', 'document_types'];

    public static function options(string $table, int|string|null $keepId = null, array $columns = ['*']): Collection
    {
        return DB::table($table)
            ->where(fn ($q) => $q->where('is_active', true)->when($keepId, fn ($q) => $q->orWhere('id', $keepId)))
            ->when(in_array($table, self::SORTABLE, true), fn ($q) => $q->orderBy('sort_order'))
            ->orderBy('name')
            ->get($columns);
    }

    /** Bộ phận user được làm việc (ô chọn bộ phận của thiết bị). */
    public static function workableDepartments(int|string|null $keepId = null): Collection
    {
        $scope = scope_department_ids();

        return DB::table('departments')
            ->where(fn ($q) => $q->where('is_active', true)->when($keepId, fn ($q) => $q->orWhere('id', $keepId)))
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** Thiết bị trong phạm vi bộ phận đang làm việc. */
    public static function devices(): Collection
    {
        $scope = scope_department_ids();

        return DB::table('devices')
            ->whereNull('deleted_at')
            ->when($scope !== null, fn ($q) => $q->whereIn('department_id', $scope))
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'department_id']);
    }
}
