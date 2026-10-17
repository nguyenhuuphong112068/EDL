<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Nhật ký hệ thống (Audit Trail) — chỉ xem / xuất, không sửa không xoá. */
class AuditController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:audit.view')];
    }

    private function query(Request $request)
    {
        $from = $request->input('from', now()->subDays(7)->toDateString());
        $to   = $request->input('to', now()->toDateString());

        return DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')
            ->select('audit_logs.*', 'users.name as user_full_name')
            ->whereDate('audit_logs.created_at', '>=', $from)
            ->whereDate('audit_logs.created_at', '<=', $to)
            ->when($request->filled('user'), fn ($q) => $q->where('audit_logs.userName', $request->user))
            ->when($request->filled('action'), fn ($q) => $q->where('audit_logs.action', $request->action))
            ->when($request->filled('module'), fn ($q) => $q->where('audit_logs.module', $request->module))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('audit_logs.description', 'like', '%' . $request->q . '%')
                ->orWhere('audit_logs.record_id', $request->q)
                ->orWhere('audit_logs.new_values', 'like', '%' . $request->q . '%')))
            ->orderByDesc('audit_logs.id');
    }

    public function index(Request $request)
    {
        if ($request->input('export') === 'csv') {
            return $this->export($request);
        }

        return view('admin.audit.index', [
            'logs'    => $this->query($request)->paginate(50)->withQueryString(),
            'from'    => $request->input('from', now()->subDays(7)->toDateString()),
            'to'      => $request->input('to', now()->toDateString()),
            'actions' => DB::table('audit_logs')->distinct()->orderBy('action')->pluck('action'),
            'modules' => DB::table('audit_logs')->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
            'users'   => DB::table('audit_logs')->whereNotNull('userName')->distinct()->orderBy('userName')->pluck('userName'),
        ]);
    }

    private function export(Request $request)
    {
        $rows = $this->query($request)->limit(50000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Thời gian', 'Tài khoản', 'Họ tên', 'Hành động', 'Đối tượng', 'Mã bản ghi', 'Mô tả', 'Giá trị cũ', 'Giá trị mới', 'IP']);
            foreach ($rows as $r) {
                fputcsv($out, [Carbon::parse($r->created_at)->format('d/m/Y H:i:s'), $r->userName, $r->user_full_name, $r->action,
                    $r->module, $r->record_id, $r->description, $r->old_values, $r->new_values, $r->ip_address]);
            }
            fclose($out);
        }, 'EDL_nhat_ky_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
