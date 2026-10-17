<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * §11.300(b) — mật khẩu hết hạn hoặc do quản trị đặt (tài khoản mới / vừa reset) thì
 * phải đổi trước khi dùng bất kỳ chức năng nào. Cờ đặt lúc đăng nhập (AuthController).
 */
class EnsurePasswordIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && session('edl.must_change_password')
            && ! $request->routeIs('password.change', 'password.update', 'logout')) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Vui lòng đổi mật khẩu trước khi tiếp tục.'], 423);
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
