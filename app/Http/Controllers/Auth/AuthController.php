<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\AccessControl;
use App\Services\Security\AuditLogger;
use App\Services\Security\LoginHistory;
use App\Services\Security\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Đăng nhập theo chính sách bảo mật của WMS:
 *  - khoá tạm tài khoản sau N lần nhập sai liên tiếp, tự mở sau M phút;
 *  - mật khẩu có hạn dùng, hết hạn / do quản trị đặt thì buộc đổi trước khi vào hệ thống;
 *  - không cho dùng lại N mật khẩu gần nhất;
 *  - mọi sự kiện đăng nhập (kể cả thất bại) đều ghi nhật ký.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'userName' => 'required|string|max:60',
            'password' => 'required|string',
        ], [], ['userName' => 'tên đăng nhập', 'password' => 'mật khẩu']);

        $userName = trim($request->userName);
        $user     = DB::table('users')->where('userName', $userName)->first();
        $max      = config('edl.security.max_failed_attempts');

        if (! $user) {
            AuditLogger::log('Đăng nhập thất bại', 'users', null, 'Tài khoản không tồn tại', null, null, $userName);
            LoginHistory::failure("failed", $userName, null, "Tài khoản không tồn tại");

            return $this->fail('Tài khoản hoặc mật khẩu không đúng.');
        }

        if (! $user->is_active) {
            AuditLogger::log('Đăng nhập bị chặn', 'users', $user->id, 'Tài khoản đã bị vô hiệu hoá', null, null, $user->userName, $user->id);
            LoginHistory::failure("blocked", $user->userName, $user->id, "Tài khoản đã bị vô hiệu hoá");

            return $this->fail('Tài khoản đã bị vô hiệu hoá, vui lòng liên hệ quản trị hệ thống.');
        }

        if ($user->locked_until && now()->lt(Carbon::parse($user->locked_until))) {
            $minutes = (int) ceil(now()->diffInMinutes(Carbon::parse($user->locked_until)));
            LoginHistory::failure("locked", $user->userName, $user->id, "Đăng nhập khi tài khoản đang bị khoá");

            return $this->fail("Tài khoản đang bị khoá do nhập sai quá nhiều lần. Vui lòng thử lại sau {$minutes} phút.");
        }

        if (! Hash::check($request->password, $user->password)) {
            $attempts = (int) $user->failed_login_attempts + 1;

            if ($attempts >= $max) {
                $until = now()->addMinutes(config('edl.security.lock_minutes'));
                DB::table('users')->where('id', $user->id)->update(['failed_login_attempts' => 0, 'locked_until' => $until]);
                AuditLogger::log('Khoá tài khoản', 'users', $user->id, "Nhập sai {$max} lần liên tiếp — khoá tới " . $until->format('d/m/Y H:i'), null, null, $user->userName, $user->id);
                LoginHistory::failure("locked", $user->userName, $user->id, "Sai mật khẩu lần {$attempts}/{$max} — khoá tài khoản " . config("edl.security.lock_minutes") . " phút");

                return $this->fail("Bạn đã nhập sai mật khẩu {$max} lần. Tài khoản bị khoá " . config('edl.security.lock_minutes') . ' phút.');
            }

            DB::table('users')->where('id', $user->id)->update(['failed_login_attempts' => $attempts]);
            AuditLogger::log('Đăng nhập thất bại', 'users', $user->id, "Sai mật khẩu (lần {$attempts}/{$max})", null, null, $user->userName, $user->id);
            LoginHistory::failure("failed", $user->userName, $user->id, "Sai mật khẩu (lần {$attempts}/{$max})");

            return $this->fail("Tài khoản hoặc mật khẩu không đúng. (sai {$attempts}/{$max} lần)");
        }

        // Đăng nhập — provider "database" của Laravel Auth (Query Builder, không Eloquent)
        Auth::loginUsingId($user->id, false);
        $request->session()->regenerate();

        DB::table('users')->where('id', $user->id)->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => now(),
            'last_login_ip'         => $request->ip(),
            'last_activity_at'      => now(),
        ]);

        session([
            'edl.last_activity_ts'     => now()->timestamp,
            'edl.must_change_password' => (bool) $user->must_change_password
                || ($user->password_expires_at && today()->gte(Carbon::parse($user->password_expires_at))),
        ]);
        app(AccessControl::class)->currentDepartmentId($user->id);

        LoginHistory::success($user->id, $user->userName);
        AuditLogger::log('Đăng nhập', 'users', $user->id, 'Đăng nhập thành công');

        if (session('edl.must_change_password')) {
            return redirect()->route('password.change')->with('error', $user->must_change_password
                ? 'Đây là lần đăng nhập đầu tiên (hoặc mật khẩu vừa được đặt lại). Vui lòng đổi mật khẩu mới.'
                : 'Mật khẩu đã hết hạn sử dụng. Vui lòng đổi mật khẩu mới để tiếp tục.');
        }

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        AuditLogger::log('Đăng xuất', 'users', Auth::id(), 'Đăng xuất');
        LoginHistory::end("manual");

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showChangePassword()
    {
        $expiresAt = DB::table('users')->where('id', Auth::id())->value('password_expires_at');

        return view('auth.change-password', [
            'forced'    => (bool) session('edl.must_change_password'),
            'expiresAt' => $expiresAt ? Carbon::parse($expiresAt) : null,
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => array_merge(PasswordPolicy::rules(), ['confirmed']),
        ], PasswordPolicy::messages() + ['password.confirmed' => 'Xác nhận mật khẩu không khớp.'],
            ['current_password' => 'mật khẩu hiện tại', 'password' => 'mật khẩu mới']);

        $user = DB::table('users')->where('id', Auth::id())->first();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        if (PasswordPolicy::recentlyUsed($user->id, $request->password)) {
            return back()->withErrors(['password' => 'Mật khẩu mới trùng với một trong ' . config('edl.security.password_history_depth') . ' mật khẩu gần đây. Vui lòng chọn mật khẩu khác.']);
        }

        PasswordPolicy::set($user->id, $request->password, false, $user->userName);
        session()->forget('edl.must_change_password');
        $request->session()->regenerate();

        AuditLogger::log('Đổi mật khẩu', 'users', $user->id, 'Đổi mật khẩu thành công');

        return redirect()->route('home')->with('success', 'Đã đổi mật khẩu. Mật khẩu có hiệu lực ' . config('edl.security.password_validity_days') . ' ngày.');
    }

    /** Chuyển bộ phận làm việc (ô chọn trên topbar). */
    public function switchDepartment(Request $request, AccessControl $access)
    {
        $id = (int) $request->input('department_id');

        if (! $access->switchDepartment(Auth::id(), $id)) {
            return back()->with('error', 'Bạn không được phân quyền làm việc ở bộ phận này.');
        }

        $name = $id === 0 ? 'Tất cả bộ phận' : DB::table('departments')->where('id', $id)->value('name');
        AuditLogger::log('Chuyển bộ phận', 'departments', $id, "Chuyển sang làm việc tại: {$name}");

        return back()->with('success', "Đang làm việc tại: {$name}");
    }

    private function fail(string $message)
    {
        return back()->withInput(request()->only('userName'))->with('error', $message);
    }
}
