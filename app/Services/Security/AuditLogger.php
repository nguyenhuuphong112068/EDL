<?php

namespace App\Services\Security;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Ghi nhật ký hệ thống (Audit Trail) — theo WMS AuditTrialController::log.
 * Bảng audit_logs chỉ ghi thêm: không có màn hình / route nào sửa hay xoá.
 * Không ghi mật khẩu, nội dung toàn văn.
 */
class AuditLogger
{
    private const HIDDEN = ['password', 'remember_token', 'content_text', 'updated_at', 'created_at'];

    public static function log(
        string $action,
        ?string $module = null,
        int|string|null $recordId = null,
        ?string $description = null,
        array|string|null $old = null,
        array|string|null $new = null,
        ?string $actor = null,
        ?int $actorId = null,
    ): void {
        $user = auth()->user();

        DB::table('audit_logs')->insert([
            'user_id'     => $actorId ?? $user?->id,
            'userName'    => $actor ?? $user?->userName ?? 'NA',
            'action'      => $action,
            'module'      => $module,
            'record_id'   => $recordId !== null ? (string) $recordId : null,
            'description' => $description !== null ? mb_substr($description, 0, 500) : null,
            'old_values'  => self::encode($old),
            'new_values'  => self::encode($new),
            'ip_address'  => request()->ip(),
            'user_agent'  => mb_substr((string) request()->userAgent(), 0, 255),
            'created_at'  => now(),
        ]);
    }

    /**
     * Ghi thay đổi của một bản ghi: chỉ các cột thực sự đổi giá trị.
     * $before / $after là mảng cột => giá trị (lấy bằng (array) DB::table(...)->first()).
     */
    public static function changes(string $action, string $table, int|string $id, array $before, array $after, ?string $description = null): void
    {
        $before = Arr::except($before, self::HIDDEN);
        $after  = Arr::except($after, self::HIDDEN);

        $changed = [];
        foreach ($after as $key => $value) {
            if (array_key_exists($key, $before) && (string) $before[$key] !== (string) $value) {
                $changed[] = $key;
            }
        }

        if (! $changed) {
            return;
        }

        self::log($action, $table, $id, $description, Arr::only($before, $changed), Arr::only($after, $changed));
    }

    private static function encode(array|string|null $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }
}
