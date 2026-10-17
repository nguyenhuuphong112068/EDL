<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * LỊCH SỬ ĐĂNG NHẬP
 *  - Quản trị: toàn bộ lượt đăng nhập (thành công / thất bại / bị khoá), phiên đang mở,
 *    thống kê trong ngày, lọc và xuất Excel.
 *  - Mỗi người dùng: xem lịch sử đăng nhập của chính mình để tự phát hiện truy cập lạ.
 */
class LoginHistoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:login_history.view', except: ['mine'])];
    }

    private function query(Request $request)
    {
        return DB::table('login_histories')
            ->leftJoin('users', 'users.id', '=', 'login_histories.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->select('login_histories.*', 'users.name as full_name', 'departments.code as department_code')
            ->whereDate('login_histories.login_at', '>=', $request->input('from', now()->subDays(7)->toDateString()))
            ->whereDate('login_histories.login_at', '<=', $request->input('to', now()->toDateString()))
            ->when($request->filled('user'), fn ($q) => $q->where('login_histories.userName', $request->input('user')))
            ->when($request->filled('status'), fn ($q) => $q->where('login_histories.status', $request->status))
            ->when($request->filled('ip'), fn ($q) => $q->where('login_histories.ip_address', 'like', $request->input('ip') . '%'))
            ->when($request->filled('department_id'), fn ($q) => $q->where('users.department_id', $request->department_id))
            ->orderByDesc('login_histories.id');
    }

    public function index(Request $request)
    {
        if ($request->input('export') === 'csv') {
            return $this->export($request);
        }

        $today = DB::table('login_histories')->whereDate('login_at', today())
            ->selectRaw("SUM(status = 'success') as success, SUM(status = 'failed') as failed, SUM(status IN ('locked','blocked')) as locked,
                COUNT(DISTINCT CASE WHEN status = 'success' THEN user_id END) as users, COUNT(DISTINCT ip_address) as ips")
            ->first();

        $online = LoginHistory::activeQuery()
            ->leftJoin('users', 'users.id', '=', 'login_histories.user_id')
            ->orderByDesc('login_histories.last_seen_at')
            ->get(['login_histories.*', 'users.name as full_name']);

        // Tài khoản bị nhập sai nhiều trong 24h — dấu hiệu dò mật khẩu
        $suspicious = DB::table('login_histories')
            ->whereIn('status', ['failed', 'locked'])
            ->where('login_at', '>=', now()->subDay())
            ->groupBy('userName')
            ->havingRaw('COUNT(*) >= 3')
            ->selectRaw('userName, COUNT(*) as attempts, COUNT(DISTINCT ip_address) as ips, MAX(login_at) as last_at')
            ->orderByDesc('attempts')
            ->limit(10)
            ->get();

        return view('admin.login-history.index', [
            'logs'        => $this->query($request)->paginate(50)->withQueryString(),
            'today'       => $today,
            'online'      => $online,
            'suspicious'  => $suspicious,
            'from'        => $request->input('from', now()->subDays(7)->toDateString()),
            'to'          => $request->input('to', now()->toDateString()),
            'users'       => DB::table('login_histories')->distinct()->orderBy('userName')->pluck('userName'),
            'departments' => DB::table('departments')->orderBy('sort_order')->get(),
        ]);
    }

    public function mine()
    {
        $logs = DB::table('login_histories')
            ->where(fn ($q) => $q->where('user_id', auth()->id())->orWhere('userName', auth()->user()->userName))
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.login-history.mine', [
            'logs'      => $logs,
            'currentId' => session(LoginHistory::SESSION_KEY),
            'failed30'  => DB::table('login_histories')->where('userName', auth()->user()->userName)
                ->whereIn('status', ['failed', 'locked'])->where('login_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    private function export(Request $request)
    {
        $rows = $this->query($request)->limit(50000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Thời điểm đăng nhập', 'Tài khoản', 'Họ tên', 'Bộ phận', 'Kết quả', 'Lý do', 'IP', 'Trình duyệt', 'Hệ điều hành',
                'Thao tác cuối', 'Kết thúc phiên', 'Cách kết thúc', 'Thời lượng (phút)']);
            foreach ($rows as $r) {
                $end = $r->logout_at ?? $r->last_seen_at;
                fputcsv($out, [
                    Carbon::parse($r->login_at)->format('d/m/Y H:i:s'), $r->userName, $r->full_name, $r->department_code,
                    LoginHistory::STATUSES[$r->status]['label'] ?? $r->status, $r->reason, $r->ip_address, $r->browser, $r->platform,
                    $r->last_seen_at ? Carbon::parse($r->last_seen_at)->format('d/m/Y H:i:s') : '',
                    $r->logout_at ? Carbon::parse($r->logout_at)->format('d/m/Y H:i:s') : '',
                    LoginHistory::LOGOUT_TYPES[$r->logout_type] ?? '',
                    $r->status === 'success' && $end ? (int) Carbon::parse($r->login_at)->diffInMinutes(Carbon::parse($end)) : '',
                ]);
            }
            fclose($out);
        }, 'EDL_lich_su_dang_nhap_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
