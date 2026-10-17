<?php

namespace App\Http\Controllers\Devices;

use App\Http\Controllers\Controller;
use App\Services\Security\AuditLogger;
use App\Support\DocumentScope;
use App\Support\DocumentSearch;
use App\Support\Lookup;
use App\Support\QrCode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * THIẾT BỊ — đối tượng quản lý tài liệu. Trang chi tiết thiết bị là "hồ sơ" của nó:
 * tài liệu xếp theo folder loại tài liệu, tải lên nhanh bằng kéo thả, in nhãn QR dán
 * lên máy để quét mở hồ sơ ngay tại hiện trường.
 */
class DeviceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:devices.view', only: ['index', 'show', 'qr']),
            new Middleware('permission:devices.create', only: ['store']),
            new Middleware('permission:devices.update', only: ['update']),
            new Middleware('permission:devices.delete', only: ['destroy', 'restore']),
        ];
    }

    /**
     * Thiết bị trong phạm vi bộ phận. Thiết bị đã ngừng (deactivate = xoá mềm, deleted_at)
     * chỉ lấy khi $withInactive — để liệt kê / kích hoạt lại.
     */
    private function baseQuery(bool $withInactive = false)
    {
        $scope = scope_department_ids();

        return DB::table('devices')
            ->leftJoin('departments', 'departments.id', '=', 'devices.department_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'devices.created_by')
            ->when(! $withInactive, fn ($q) => $q->whereNull('devices.deleted_at'))
            ->when($scope !== null, fn ($q) => $q->whereIn('devices.department_id', $scope));
    }

    /**
     * DANH SÁCH THIẾT BỊ — đủ CRUD trên một trang: thêm / sửa / nhân bản qua modal,
     * ngừng sử dụng (xoá mềm) và kích hoạt lại ngay trên dòng.
     */
    public function index(Request $request)
    {
        // active: 1 = đang hoạt động (mặc định) | 0 = đã ngừng | all = tất cả
        $active = in_array($request->input('active'), ['0', 'all'], true) ? $request->input('active') : '1';

        $query = $this->baseQuery(true)->select(
            'devices.*', 'departments.code as department_code', 'departments.name as department_name',
            'creators.name as creator_name'
        )
            ->when($active === '1', fn ($q) => $q->whereNull('devices.deleted_at'))
            ->when($active === '0', fn ($q) => $q->whereNotNull('devices.deleted_at'));

        if ($request->filled('q')) {
            $like = '%' . addcslashes(trim($request->q), '%_') . '%';
            $query->where(fn ($q) => $q->where('devices.code', 'like', $like)->orWhere('devices.name', 'like', $like));
        }

        foreach (['status', 'department_id'] as $f) {
            if ($request->filled($f)) {
                $query->where("devices.{$f}", $request->input($f));
            }
        }

        // Số tài liệu của mỗi thiết bị (sub-select, không N+1)
        $query->selectSub(DB::table('documents')->selectRaw('COUNT(*)')->whereColumn('documents.device_id', 'devices.id')->whereNull('documents.deleted_at'), 'documents_count');

        $sort = $request->input('sort', 'code');
        match ($sort) {
            'name'      => $query->orderBy('devices.name'),
            'documents' => $query->orderByDesc('documents_count'),
            'newest'    => $query->orderByDesc('devices.created_at'),
            default     => $query->orderBy('devices.code'),
        };

        $devices = $query->paginate(25)->withQueryString();

        $scope = scope_department_ids();

        // Mở sẵn modal khi đi từ trang hồ sơ thiết bị: ?edit={id} hoặc ?copy={id}
        $openId   = (int) ($request->input('edit') ?: $request->input('copy'));
        $openMode = $request->filled('edit') ? 'edit' : ($request->filled('copy') ? 'copy' : null);
        $openDevice = $openId ? $this->baseQuery()->where('devices.id', $openId)->first(['devices.*']) : null;

        return view('devices.index', [
            'devices'       => $devices,
            'active'        => $active,
            'inactiveCount' => DB::table('devices')->whereNotNull('deleted_at')
                ->when($scope !== null, fn ($q) => $q->whereIn('department_id', $scope))->count(),
            'departments'   => Lookup::workableDepartments(),
            'openDevice'    => $openDevice && ($openMode === 'copy' || works_on_department($openDevice->department_id)) ? $openDevice : null,
            'openMode'      => $openMode,
        ]);
    }

    /** Thêm thiết bị (modal trên trang danh sách) — trả JSON {success, data, message}. */
    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $data += ['created_by' => auth()->id(), 'updated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()];

        $id = DB::table('devices')->insertGetId($data);
        AuditLogger::log('Thêm thiết bị', 'devices', $id, "{$data['code']} — {$data['name']}", null, $data);

        return $this->saved($request, $id, "Đã thêm thiết bị {$data['code']}.");
    }

    public function show(Request $request, int $device)
    {
        $dev = $this->findOrFail($device);

        // Folder = loại tài liệu (kèm số tài liệu)
        $docQuery = fn () => DocumentScope::query()->where('documents.device_id', $dev->id);

        $folders = $docQuery()->selectRaw('documents.document_type_id, COALESCE(document_types.name, ?) as name, document_types.color, COUNT(*) as total', ['Chưa phân loại'])
            ->groupBy('documents.document_type_id', 'document_types.name', 'document_types.color', 'document_types.sort_order')
            ->orderByRaw('document_types.sort_order IS NULL, document_types.sort_order')
            ->get();

        // Danh sách tài liệu của thiết bị: tìm cả nội dung file, lọc định dạng / tình trạng / bảo mật, sắp xếp
        $folder  = $request->input('folder');
        $view    = in_array($request->input('view'), ['grid', 'list'], true) ? $request->input('view') : 'list';
        $perPage = in_array((int) $request->input('per_page'), [20, 50, 100], true) ? (int) $request->input('per_page') : 20;
        $term    = (string) $request->input('q');

        $query = $docQuery()->select(DocumentScope::LIST_COLUMNS)
            ->when($folder === 'none', fn ($q) => $q->whereNull('documents.document_type_id'))
            ->when($folder && $folder !== 'none', fn ($q) => $q->where('documents.document_type_id', (int) $folder));
        DocumentSearch::applyKeyword($query, $term, $request->input('in', 'all'));
        DocumentSearch::applyFilters($query, new Request($request->only(['file_group', 'status', 'uploaded_by', 'date_from', 'date_to'])));
        if ($request->filled('sort')) {
            DocumentSearch::applySort($query, $request->input('sort'), null);
        } else {
            $query->orderByRaw('document_types.sort_order IS NULL, document_types.sort_order')->orderBy('documents.title');
        }
        $documents = $query->paginate($perPage)->withQueryString();

        // Đoạn trích nội dung khớp từ khoá
        $snippets = [];
        $ids = $documents->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($term !== '' && $request->input('in', 'all') !== 'title' && $ids) {
            foreach (DB::table('documents')->whereIn('id', $ids)->pluck('content_text', 'id') as $id => $content) {
                if ($s = DocumentSearch::snippet($content, $term)) {
                    $snippets[$id] = $s;
                }
            }
        }

        $stats = $docQuery()->selectRaw("COUNT(*) as total, COALESCE(SUM(documents.file_size),0) as bytes,
            COALESCE(SUM(documents.view_count),0) as views")->first();
        $stats->obsolete = DB::table('documents')->where('device_id', $dev->id)->whereNotNull('deleted_at')->count();

        return view('devices.show', [
            'device'        => $dev,
            'folders'       => $folders,
            'activeFolder'  => $folder,
            'documents'     => $documents,
            'snippets'      => $snippets,
            'view'          => $view,
            'stats'         => $stats,
            'qr'            => QrCode::svg(route('devices.show', $dev->id), 'M', 2),
            'canManage'     => works_on_department($dev->department_id),
        ]);
    }

    /** Sửa thiết bị (modal trên trang danh sách) — trả JSON {success, data, message}. */
    public function update(Request $request, int $device)
    {
        $dev = $this->findOrFail($device);
        $this->authorizeManage($dev);

        $before = (array) DB::table('devices')->where('id', $dev->id)->first();
        $data   = $this->validated($request, $dev->id) + ['updated_by' => auth()->id(), 'updated_at' => now()];

        DB::table('devices')->where('id', $dev->id)->update($data);
        AuditLogger::changes('Sửa thiết bị', 'devices', $dev->id, $before, $data, $data['code']);

        return $this->saved($request, $dev->id, "Đã cập nhật thiết bị {$data['code']}.");
    }

    /**
     * NGỪNG SỬ DỤNG (deactivate) — xoá mềm: đặt deleted_at, KHÔNG xoá dữ liệu / file.
     * Thiết bị và tài liệu của nó ẩn khỏi tra cứu cho tới khi được kích hoạt lại.
     */
    public function destroy(int $device)
    {
        $dev = $this->findOrFail($device);
        $this->authorizeManage($dev);

        $docCount = DB::table('documents')->where('device_id', $dev->id)->whereNull('deleted_at')->count();

        DB::table('devices')->where('id', $dev->id)->update(['deleted_at' => now(), 'updated_by' => auth()->id()]);
        AuditLogger::log('Ngừng sử dụng thiết bị', 'devices', $dev->id, "{$dev->code} — {$dev->name} ({$docCount} tài liệu được ẩn theo)");

        return redirect()->route('devices.index', request()->query())
            ->with('success', "Đã ngừng sử dụng thiết bị {$dev->code}" . ($docCount ? " — {$docCount} tài liệu được ẩn theo, sẽ hiện lại khi kích hoạt lại." : '.'));
    }

    /** Kích hoạt lại thiết bị đã ngừng — thiết bị và tài liệu hiện lại như cũ. */
    public function restore(int $device)
    {
        $dev = $this->findOrFail($device, true);
        $this->authorizeManage($dev);

        abort_if($dev->deleted_at === null, Response::HTTP_UNPROCESSABLE_ENTITY, 'Thiết bị đang hoạt động.');

        DB::table('devices')->where('id', $dev->id)->update(['deleted_at' => null, 'updated_by' => auth()->id(), 'updated_at' => now()]);
        AuditLogger::log('Kích hoạt lại thiết bị', 'devices', $dev->id, "{$dev->code} — {$dev->name}");

        return redirect()->route('devices.index', request()->query())->with('success', "Đã kích hoạt lại thiết bị {$dev->code}.");
    }

    /** Phản hồi sau khi thêm / sửa: JSON cho modal, chuyển trang nếu gửi form thường. */
    private function saved(Request $request, int $id, string $message)
    {
        if ($request->expectsJson()) {
            // Modal tải lại trang sau khi lưu — thông báo hiện ở đầu trang
            session()->flash('success', $message);

            return response()->json([
                'success' => true,
                'data'    => ['id' => $id, 'url' => route('devices.show', $id)],
                'message' => $message,
            ]);
        }

        return redirect()->route('devices.index')->with('success', $message);
    }

    /** In nhãn QR cho một hoặc nhiều thiết bị — quét bằng điện thoại để mở hồ sơ thiết bị. */
    public function qr(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));

        $devices = $this->baseQuery()
            ->whereIn('devices.id', $ids ?: [0])
            ->orderBy('devices.code')
            ->get(['devices.id', 'devices.code', 'devices.name', 'devices.status', 'departments.name as department_name']);

        abort_if($devices->isEmpty(), Response::HTTP_NOT_FOUND, 'Chưa chọn thiết bị để in nhãn.');

        $labels = $devices->map(fn ($d) => ['device' => $d, 'svg' => QrCode::svg(route('devices.show', $d->id), 'M', 1)]);

        return view('devices.qr', ['labels' => $labels]);
    }

    /* ==================== DÙNG CHUNG ==================== */

    private function findOrFail(int $id, bool $withInactive = false): object
    {
        $dev = $this->baseQuery($withInactive)->where('devices.id', $id)
            ->select('devices.*', 'departments.code as department_code', 'departments.name as department_name',
                'creators.name as creator_name')
            ->first();

        abort_if($dev === null, Response::HTTP_NOT_FOUND, 'Không tìm thấy thiết bị hoặc thiết bị thuộc bộ phận khác.');

        return $dev;
    }

    private function authorizeManage(object $dev): void
    {
        abort_unless(works_on_department($dev->department_id), Response::HTTP_FORBIDDEN, 'Thiết bị thuộc bộ phận khác.');
    }

    /** Thông tin thiết bị: Mã, Tên, Bộ phận lắp đặt, Tình trạng (người tạo / ngày tạo do hệ thống ghi). */
    private function validated(Request $request, ?int $id): array
    {
        $data = $request->validate([
            'code'          => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._\-\/]+$/', Rule::unique('devices', 'code')->ignore($id)],
            'name'          => 'required|string|max:255',
            'department_id' => 'required|integer|exists:departments,id',
            'status'        => ['required', Rule::in(array_keys(config('edl.device_statuses')))],
        ], ['code.regex' => 'Mã thiết bị chỉ gồm chữ, số và các ký tự . _ - /'], [
            'code' => 'mã thiết bị', 'name' => 'tên thiết bị', 'department_id' => 'bộ phận lắp đặt', 'status' => 'tình trạng',
        ]);

        abort_unless(works_on_department($data['department_id']), Response::HTTP_FORBIDDEN, 'Bạn không được thêm / chuyển thiết bị sang bộ phận này.');

        $data['code'] = mb_strtoupper($data['code']);

        return $data;
    }
}
