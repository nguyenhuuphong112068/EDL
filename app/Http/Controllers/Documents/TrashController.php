<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Services\Documents\DocumentService;
use App\Services\Security\AuditLogger;
use App\Support\FileType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Tài liệu hết hiệu lực (xoá mềm) của bộ phận đang làm việc: khôi phục hiệu lực hoặc xoá vĩnh viễn. */
class TrashController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:documents.restore', only: ['index', 'restore']),
            new Middleware('permission:documents.force_delete', only: ['destroy']),
        ];
    }

    private function query()
    {
        $scope = scope_department_ids();

        return DB::table('documents')
            ->join('devices', 'devices.id', '=', 'documents.device_id')
            ->leftJoin('document_types', 'document_types.id', '=', 'documents.document_type_id')
            ->leftJoin('users', 'users.id', '=', 'documents.deleted_by')
            ->whereNotNull('documents.deleted_at')
            ->when($scope !== null, fn ($q) => $q->whereIn('devices.department_id', $scope));
    }

    public function index(Request $request)
    {
        $documents = $this->query()
            ->when($request->filled('device_id'), fn ($q) => $q->where('documents.device_id', (int) $request->device_id))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('documents.title', 'like', '%' . $request->q . '%')
                ->orWhere('documents.code', 'like', '%' . $request->q . '%')))
            ->orderByDesc('documents.deleted_at')
            ->select('documents.id', 'documents.code', 'documents.title', 'documents.extension', 'documents.file_size',
                'documents.deleted_at', 'devices.code as device_code', 'devices.name as device_name',
                'document_types.name as type_name', 'users.name as deleted_by_name')
            ->paginate(25)->withQueryString();

        $totalSize = $this->query()->sum('documents.file_size');

        return view('documents.trash', ['documents' => $documents, 'totalSize' => FileType::humanSize($totalSize)]);
    }

    public function restore(Request $request)
    {
        $ids = $this->ids($request);

        DB::table('documents')->whereIn('id', $ids)->update(['deleted_at' => null, 'deleted_by' => null, 'updated_at' => now()]);
        AuditLogger::log('Khôi phục hiệu lực tài liệu', 'documents', count($ids) === 1 ? $ids[0] : null, count($ids) . ' tài liệu: #' . implode(', #', $ids));

        return back()->with('success', 'Đã khôi phục ' . count($ids) . ' tài liệu.');
    }

    public function destroy(Request $request, DocumentService $documents)
    {
        $ids   = $this->ids($request);
        $codes = DB::table('documents')->whereIn('id', $ids)->pluck('code')->implode(', ');

        foreach ($ids as $id) {
            $documents->forceDelete($id);
        }

        AuditLogger::log('Xoá vĩnh viễn tài liệu', 'documents', count($ids) === 1 ? $ids[0] : null, count($ids) . " tài liệu: {$codes}");

        return back()->with('success', 'Đã xoá vĩnh viễn ' . count($ids) . ' tài liệu và file đính kèm.');
    }

    /** id hợp lệ: đã hết hiệu lực và thuộc phạm vi bộ phận. */
    private function ids(Request $request): array
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer'], ['ids.required' => 'Chưa chọn tài liệu nào.']);

        $ids = $this->query()->whereIn('documents.id', $request->ids)->pluck('documents.id')->map(fn ($id) => (int) $id)->all();
        abort_if(! $ids, Response::HTTP_NOT_FOUND);

        return $ids;
    }
}
