<?php

namespace App\Http\Middleware;

use App\Services\Security\AuditLogger;
use App\Services\Security\LoginHistory;
use Illuminate\Support\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * §11.10(d) — tự đăng xuất sau một khoảng không thao tác (theo WMS UpdateUserActivity).
 * Đồng thời cập nhật users.last_activity_at (tối đa 1 lần/phút để đỡ ghi DB).
 */
class SessionIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $timeout = config('edl.security.idle_timeout_minutes') * 60;
        $now     = now()->timestamp;
        $last    = (int) session('edl.last_activity_ts', $now);

        if ($now - $last > $timeout) {
            AuditLogger::log('Tự đăng xuất', 'users', Auth::id(),
                'Hết phiên do không hoạt động ' . config('edl.security.idle_timeout_minutes') . ' phút');
            // Phiên thực sự hết tại mốc thao tác cuối + thời gian chờ, không phải lúc phát hiện
            LoginHistory::end('timeout', Carbon::createFromTimestamp($last + $timeout));

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Phiên làm việc đã hết hạn do không hoạt động quá ' . config('edl.security.idle_timeout_minutes') . ' phút. Vui lòng đăng nhập lại.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 419);
            }

            return redirect()->route('login')->with('error', $message);
        }

        // Yêu cầu ngầm (gợi ý tìm kiếm…) không tính là thao tác của người dùng
        if (! $request->headers->has('X-EDL-Background')) {
            if ($now - (int) session('edl.activity_saved_ts', 0) > 60) {
                DB::table('users')->where('id', Auth::id())->update(['last_activity_at' => now()]);
                LoginHistory::touch();
                session(['edl.activity_saved_ts' => $now]);
            }

            session(['edl.last_activity_ts' => $now]);
        }

        return $next($request);
    }
}
