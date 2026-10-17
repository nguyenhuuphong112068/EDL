<?php

namespace App\Http\Controllers;

use App\Support\DocumentScope;
use Illuminate\Support\Facades\DB;

/**
 * Trang chủ — số thiết bị và số tài liệu của bộ phận đang làm việc,
 * cùng các tài liệu chính người dùng xem gần đây.
 */
class HomeController extends Controller
{
    private const RECENT_DAYS  = 30;
    private const RECENT_LIMIT = 8;

    public function index()
    {
        $scope   = scope_department_ids();
        $canDocs = user_can('documents.view');

        $data = [
            'canDocs'     => $canDocs,
            'recentDays'  => self::RECENT_DAYS,
            'deviceTotal' => DB::table('devices')->whereNull('deleted_at')
                ->when($scope !== null, fn ($q) => $q->whereIn('department_id', $scope))
                ->count(),
        ];

        if ($canDocs) {
            $data['docTotal'] = DocumentScope::query(false)
                ->when($scope !== null, fn ($q) => $q->whereIn('devices.department_id', $scope))
                ->count();

            // Tài liệu chính user này đã xem / tải trong N ngày gần đây (mỗi tài liệu một dòng, lần xem mới nhất trước)
            $recent = DocumentScope::query()->joinSub(
                DB::table('document_activities')->where('user_id', auth()->id())
                    ->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))
                    ->groupBy('document_id')->selectRaw('document_id, MAX(created_at) as last_seen'),
                'recent', 'recent.document_id', '=', 'documents.id'
            );

            $data['recentTotal'] = (clone $recent)->count();
            $data['recentDocs']  = $recent->orderByDesc('recent.last_seen')->limit(self::RECENT_LIMIT)->get([
                'documents.id', 'documents.title', 'documents.code', 'documents.extension',
                'devices.code as device_code', 'devices.name as device_name',
                'document_types.name as type_name', 'document_types.color as type_color', 'recent.last_seen',
            ]);
        }

        return view('home.index', $data);
    }
}
