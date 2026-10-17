<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Chính sách mật khẩu §11.300(b) — dùng chung cho tự đổi mật khẩu và quản trị đặt lại. */
class PasswordPolicy
{
    /** Luật kiểm tra độ phức tạp: chữ hoa, chữ thường, số, ký tự đặc biệt. */
    public static function rules(): array
    {
        return [
            'required', 'string', 'min:' . config('edl.security.password_min_length'), 'max:255',
            'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/',
        ];
    }

    public static function messages(string $field = 'password'): array
    {
        return [
            "{$field}.min"   => 'Mật khẩu phải có ít nhất ' . config('edl.security.password_min_length') . ' ký tự.',
            "{$field}.regex" => 'Mật khẩu phải gồm chữ hoa, chữ thường, chữ số và ký tự đặc biệt.',
        ];
    }

    /** Mật khẩu mới có trùng N mật khẩu gần nhất (kể cả hiện tại) không. */
    public static function recentlyUsed(int $userId, string $plain): bool
    {
        $current = DB::table('users')->where('id', $userId)->value('password');

        if ($current && Hash::check($plain, $current)) {
            return true;
        }

        return DB::table('password_histories')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(config('edl.security.password_history_depth'))
            ->pluck('password_hash')
            ->contains(fn ($hash) => Hash::check($plain, $hash));
    }

    /**
     * Đặt mật khẩu mới + ghi lịch sử.
     * $byAdmin = true: quản trị đặt lại → buộc user đổi ở lần đăng nhập tới.
     */
    public static function set(int $userId, string $plain, bool $byAdmin, ?string $actor = null): void
    {
        $hash = Hash::make($plain);

        DB::table('users')->where('id', $userId)->update([
            'password'              => $hash,
            'password_expires_at'   => today()->addDays(config('edl.security.password_validity_days')),
            'must_change_password'  => $byAdmin,
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'updated_at'            => now(),
        ]);

        DB::table('password_histories')->insert([
            'user_id'       => $userId,
            'password_hash' => $hash,
            'created_by'    => $actor ?? 'self',
            'created_at'    => now(),
        ]);
    }
}
