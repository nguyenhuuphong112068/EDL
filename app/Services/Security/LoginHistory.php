<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;

/**
 * Ghi lịch sử đăng nhập (Query Builder). Khác audit_logs ở chỗ mỗi phiên là MỘT dòng có cả
 * thời điểm vào / ra, nên xem được thời lượng phiên, phiên đang mở, nguồn truy cập.
 */
class LoginHistory
{
    public const SESSION_KEY = 'edl.login_history_id';

    public const STATUSES = [
        'success' => ['label' => 'Thành công', 'badge' => 'badge-success'],
        'failed'  => ['label' => 'Sai mật khẩu / tài khoản', 'badge' => 'badge-warning'],
        'locked'  => ['label' => 'Bị khoá', 'badge' => 'badge-danger'],
        'blocked' => ['label' => 'Bị chặn (vô hiệu hoá)', 'badge' => 'badge-secondary'],
    ];

    public const LOGOUT_TYPES = [
        'manual'  => 'Tự đăng xuất',
        'timeout' => 'Hết phiên (không thao tác)',
    ];

    /** Đăng nhập thành công — giữ id dòng trong session để cập nhật khi kết thúc phiên. */
    public static function success(int $userId, string $userName): void
    {
        $id = DB::table('login_histories')->insertGetId(self::base($userName, $userId) + [
            'status'       => 'success',
            'session_id'   => session()->getId(),
            'login_at'     => now(),
            'last_seen_at' => now(),
        ]);

        session([self::SESSION_KEY => $id]);
    }

    /** Lần đăng nhập không thành: failed | locked | blocked. */
    public static function failure(string $status, string $userName, ?int $userId, string $reason): void
    {
        DB::table('login_histories')->insert(self::base($userName, $userId) + [
            'status'   => $status,
            'reason'   => mb_substr($reason, 0, 255),
            'login_at' => now(),
        ]);
    }

    /** Kết thúc phiên hiện tại (gọi TRƯỚC khi huỷ session). */
    public static function end(string $type, ?\DateTimeInterface $at = null): void
    {
        if ($id = session(self::SESSION_KEY)) {
            DB::table('login_histories')->where('id', $id)->whereNull('logout_at')
                ->update(['logout_at' => $at ?? now(), 'logout_type' => $type]);
        }
    }

    /** Cập nhật lần thao tác gần nhất (middleware gọi tối đa 1 lần/phút). */
    public static function touch(): void
    {
        if ($id = session(self::SESSION_KEY)) {
            DB::table('login_histories')->where('id', $id)->update(['last_seen_at' => now()]);
        }
    }

    /**
     * Phiên được coi là còn mở: chưa đăng xuất và còn thao tác trong khoảng thời gian chờ.
     * Phiên đóng trình duyệt mà không đăng xuất sẽ tự hết hạn theo thời gian chờ.
     */
    public static function activeQuery()
    {
        return DB::table('login_histories')
            ->where('status', 'success')
            ->whereNull('logout_at')
            ->where('last_seen_at', '>=', now()->subMinutes(config('edl.security.idle_timeout_minutes')));
    }

    private static function base(string $userName, ?int $userId): array
    {
        $agent = (string) request()->userAgent();
        [$browser, $platform] = self::parseAgent($agent);

        return [
            'user_id'    => $userId,
            'userName'   => mb_substr($userName !== '' ? $userName : 'NA', 0, 60),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr($agent, 0, 500),
            'browser'    => $browser,
            'platform'   => $platform,
        ];
    }

    /** Nhận diện trình duyệt / hệ điều hành đơn giản từ User-Agent. */
    public static function parseAgent(string $ua): array
    {
        $browser = match (true) {
            (bool) preg_match('/Edg\/([\d]+)/', $ua, $m)            => 'Edge ' . $m[1],
            (bool) preg_match('/OPR\/([\d]+)/', $ua, $m)            => 'Opera ' . $m[1],
            (bool) preg_match('/Firefox\/([\d]+)/', $ua, $m)        => 'Firefox ' . $m[1],
            (bool) preg_match('/(?:Chrome|CriOS)\/([\d]+)/', $ua, $m) => 'Chrome ' . $m[1],
            (bool) preg_match('/Version\/([\d]+).*Safari/', $ua, $m) => 'Safari ' . $m[1],
            str_contains($ua, 'curl')                               => 'curl',
            $ua === ''                                              => null,
            default                                                 => 'Khác',
        };

        $platform = match (true) {
            str_contains($ua, 'Windows NT 10')              => 'Windows 10/11',
            str_contains($ua, 'Windows')                    => 'Windows',
            str_contains($ua, 'Android')                    => 'Android',
            (bool) preg_match('/iPhone|iPad/', $ua)          => 'iOS',
            str_contains($ua, 'Mac OS X')                   => 'macOS',
            str_contains($ua, 'Linux')                      => 'Linux',
            default                                         => null,
        };

        return [$browser, $platform];
    }
}
