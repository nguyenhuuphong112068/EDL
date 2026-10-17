<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Services\Documents\DocumentService;
use App\Services\Documents\DocumentStorage;
use App\Services\Documents\FilePreview;
use App\Services\Security\AuditLogger;
use App\Support\DocumentScope;
use App\Support\DocumentSearch;
use App\Support\FileType;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * THƯ VIỆN TÀI LIỆU — tra cứu, xem, tải, sửa thông tin, chuyển hết hiệu lực,
 * thao tác hàng loạt, xuất danh sách.
 */
class DocumentController extends Controller implements HasMiddleware
{
    /** File Word được dựng lại đầy đủ (bảng, định dạng, ảnh, trang) ngay trong trình duyệt bằng docx-preview. */
    public const WORD_RENDERED = ['docx', 'docm'];

    public function __construct(
        private DocumentStorage $storage,
        private DocumentService $documents,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:documents.view', only: ['index', 'show', 'preview']),
            new Middleware('permission:documents.download', only: ['download']),
            new Middleware('permission:documents.update', only: ['edit', 'update']),
            new Middleware('permission:documents.delete', only: ['destroy']),
            new Middleware('permission:documents.export', only: ['export']),
            new Middleware('permission:documents.view', only: ['bulk']),
        ];
    }

    /* ==================== TRA CỨU ==================== */

    public function index(Request $request)
    {
        $view    = in_array($request->input('view'), ['grid', 'list'], true) ? $request->input('view') : 'list';
        $perPage = in_array((int) $request->input('per_page'), [20, 50, 100], true) ? (int) $request->input('per_page') : 20;

        $documents = DocumentSearch::build($request)->paginate($perPage)->withQueryString();

        $ids = $documents->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Đoạn trích nội dung khớp từ khoá — chỉ đọc content_text của các dòng trên trang
        $snippets = [];
        if ($request->filled('q') && $request->input('in', 'all') !== 'title' && $ids) {
            foreach (DB::table('documents')->whereIn('id', $ids)->pluck('content_text', 'id') as $id => $content) {
                if ($s = DocumentSearch::snippet($content, $request->q)) {
                    $snippets[$id] = $s;
                }
            }
        }

        $lookups = $this->filterLookups();

        // Đếm nhanh cho các tab
        $counts = [
            'all'  => DocumentScope::query(false)->count(),
            'mine' => DocumentScope::query(false)->where('documents.uploaded_by', auth()->id())->count(),
        ];

        return view('documents.index', [
            'documents'     => $documents,
            'snippets'      => $snippets,
            'lookups'       => $lookups,
            'chips'         => DocumentSearch::activeFilters($request, $lookups),
            'counts'        => $counts,
            'view'          => $view,
            'savedSearches' => DB::table('saved_searches')->where('user_id', auth()->id())->orderBy('name')->get(),
        ]);
    }

    /** Danh mục cho các ô lọc: [id => nhãn] */
    private function filterLookups(): array
    {
        return [
            'devices'       => Lookup::devices()->mapWithKeys(fn ($d) => [$d->id => "{$d->code} — {$d->name}"])->all(),
            'departments'   => DB::table('departments')->where('is_active', true)->orderBy('sort_order')->get()
                ->mapWithKeys(fn ($d) => [$d->id => "{$d->code} — {$d->name}"])->all(),
            'documentTypes' => DB::table('document_types')->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'uploaders'     => DB::table('users')->whereIn('id', DB::table('documents')->select('uploaded_by')->distinct())
                ->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /* ==================== CHI TIẾT ==================== */

    public function show(int $document, FilePreview $previewer)
    {
        $doc = DocumentScope::findOrFail($document);

        $device = DB::table('devices')->where('id', $doc->device_id)->first();

        $versions = DB::table('document_versions')
            ->leftJoin('users', 'users.id', '=', 'document_versions.uploaded_by')
            ->where('document_id', $doc->id)
            ->orderByDesc('revision')
            ->select('document_versions.*', 'users.name as uploader_name')
            ->get();

        $activities = DB::table('audit_logs')
            ->where('module', 'documents')->where('record_id', (string) $doc->id)
            ->orderByDesc('id')->limit(30)->get();

        // Tài liệu khác của cùng thiết bị — để chuyển nhanh
        $related = DocumentScope::query()->where('documents.device_id', $doc->device_id)->where('documents.id', '!=', $doc->id)
            ->orderBy('document_types.sort_order')->orderBy('documents.title')
            ->limit(12)->get(['documents.id', 'documents.title', 'documents.extension', 'documents.code', 'document_types.name as type_name']);

        // Bản trùng nội dung (cùng SHA-256) ở nơi khác
        $duplicates = $doc->checksum
            ? DocumentScope::query()->where('documents.checksum', $doc->checksum)->where('documents.id', '!=', $doc->id)
                ->get(['documents.id', 'documents.code', 'documents.title', 'devices.code as device_code'])
            : collect();

        $preview = $this->buildPreview($doc, $previewer);

        $this->recordActivity($doc->id, 'view');
        DB::table('documents')->where('id', $doc->id)->increment('view_count');

        return view('documents.show', [
            'doc'        => $doc,
            'device'     => $device,
            'versions'   => $versions,
            'activities' => $activities,
            'related'    => $related,
            'duplicates' => $duplicates,
            'preview'    => $preview,
            'canManage'  => DocumentScope::canManage($doc),
        ]);
    }

    private function buildPreview(object $doc, FilePreview $previewer): array
    {
        $mode = FileType::previewMode($doc->extension);

        if (! $this->storage->exists($doc->file_path)) {
            return ['mode' => 'missing'];
        }

        $path = $this->storage->absolutePath($doc->file_path);

        return match ($mode) {
            'docx'  => ['mode' => 'docx', 'paragraphs' => $previewer->documentParagraphs($path, $doc->extension)],
            'xlsx'  => ['mode' => 'xlsx', 'sheets' => $previewer->spreadsheet($path)],
            'text'  => ['mode' => 'text', 'text' => $previewer->textContent($path)],
            default => ['mode' => $mode],
        };
    }

    /**
     * Trả file cho khung xem trước: PDF, ảnh, video, âm thanh (trình duyệt tự hiển thị)
     * và Word .docx / .docm (đọc bằng docx-preview trong trình duyệt — không chạy macro).
     */
    public function preview(int $document)
    {
        $doc  = DocumentScope::findOrFail($document);
        $mime = FileType::inlineMime($doc->extension)
            ?? (in_array(strtolower($doc->extension), self::WORD_RENDERED, true) ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : null);

        abort_unless($mime && $this->storage->exists($doc->file_path), Response::HTTP_NOT_FOUND);

        $headers = [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . Str::ascii($doc->original_filename) . '"',
            'Cache-Control'       => 'private, max-age=600',
        ];

        // Ảnh / video / âm thanh: chặn mọi script nếu mở trực tiếp. PDF thì không — trình xem PDF
        // của trình duyệt không chạy được trong khung bị sandbox.
        if ($doc->extension !== 'pdf') {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self'; media-src 'self'; style-src 'unsafe-inline'; sandbox";
        }

        return response()->file($this->storage->absolutePath($doc->file_path), $headers);
    }

    public function download(int $document)
    {
        $doc = DocumentScope::findOrFail($document);

        abort_unless($this->storage->exists($doc->file_path), Response::HTTP_NOT_FOUND, 'File không còn trên máy chủ lưu trữ.');

        DB::table('documents')->where('id', $doc->id)->increment('download_count');
        $this->recordActivity($doc->id, 'download');
        AuditLogger::log('Tải xuống tài liệu', 'documents', $doc->id, "{$doc->code} — {$doc->original_filename}");

        return $this->storage->disk()->download($doc->file_path, $doc->original_filename);
    }

    /* ==================== SỬA THÔNG TIN ==================== */

    public function edit(int $document)
    {
        $doc = DocumentScope::findOrFail($document);
        DocumentScope::authorizeManage($doc, 'documents.update');

        return view('documents.edit', [
            'doc'           => $doc,
            'devices'       => Lookup::devices(),
            'documentTypes' => Lookup::options('document_types', $doc->document_type_id),
        ]);
    }

    public function update(Request $request, int $document)
    {
        $doc = DocumentScope::findOrFail($document);
        DocumentScope::authorizeManage($doc, 'documents.update');

        $data = $request->validate($this->rules(), [], $this->attributeNames());
        $this->assertDeviceInScope((int) $data['device_id']);

        $before = (array) DB::table('documents')->where('id', $doc->id)->first();
        $update = $data + ['updated_by' => auth()->id(), 'updated_at' => now()];

        DB::table('documents')->where('id', $doc->id)->update($update);

        AuditLogger::changes('Sửa thông tin tài liệu', 'documents', $doc->id, $before, $update, $doc->code);

        return redirect()->route('documents.show', $doc->id)->with('success', 'Đã cập nhật thông tin tài liệu.');
    }

    /** Chuyển hết hiệu lực (xoá mềm) — khôi phục được ở "Tài liệu hết hiệu lực". */
    public function destroy(int $document)
    {
        $doc = DocumentScope::findOrFail($document);
        DocumentScope::authorizeManage($doc, 'documents.delete');

        DB::table('documents')->where('id', $doc->id)->update(['deleted_at' => now(), 'deleted_by' => auth()->id()]);
        AuditLogger::log('Chuyển tài liệu hết hiệu lực', 'documents', $doc->id, "{$doc->code} — {$doc->title}");

        return redirect()->route('devices.show', $doc->device_id)->with('success', "Đã chuyển \"{$doc->title}\" sang hết hiệu lực.");
    }

    /* ==================== HÀNG LOẠT ==================== */

    /**
     * Thao tác trên nhiều tài liệu đã chọn:
     *  zip      — tải tất cả thành một file nén (giữ cấu trúc thư mục theo thiết bị)
     *  status   — đổi tình trạng
     *  trash    — chuyển hết hiệu lực
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'action' => 'required|in:zip,status,trash',
            'ids'    => 'required|array|min:1|max:500',
            'ids.*'  => 'integer',
        ], ['ids.required' => 'Chưa chọn tài liệu nào.']);

        $docs = DocumentScope::query()->whereIn('documents.id', $request->ids)
            ->select(array_merge(DocumentScope::LIST_COLUMNS, ['documents.file_path']))->get();

        abort_if($docs->isEmpty(), Response::HTTP_NOT_FOUND, 'Không tìm thấy tài liệu đã chọn.');

        return match ($request->action) {
            'zip'    => $this->bulkZip($docs),
            'status' => $this->bulkStatus($request, $docs),
            'trash'  => $this->bulkTrash($docs),
        };
    }

    private function bulkZip($docs)
    {
        abort_unless(user_can('documents.download'), Response::HTTP_FORBIDDEN);

        $zipPath = storage_path('app/private/' . config('edl.storage.tmp') . '/zip_' . Str::random(16) . '.zip');
        @mkdir(dirname($zipPath), 0775, true);

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $used = [];

        foreach ($docs as $doc) {
            if (! $this->storage->exists($doc->file_path)) {
                continue;
            }

            $folder = str_replace(['/', '\\'], '-', Str::ascii($doc->device_code)) . '/' . str_replace(['/', '\\'], '-', Str::ascii($doc->type_name ?? 'Khac'));
            $name   = $folder . '/' . $doc->code . ' - ' . Str::limit(Str::ascii($doc->title), 80, '') . '.' . $doc->extension;
            $name   = preg_replace('/[<>:"|?*]/', '_', $name);
            $name   = isset($used[$name]) ? str_replace(".{$doc->extension}", "_{$doc->id}.{$doc->extension}", $name) : $name;
            $used[$name] = true;

            $zip->addFile($this->storage->absolutePath($doc->file_path), $name);
            DB::table('documents')->where('id', $doc->id)->increment('download_count');
        }
        $zip->close();

        AuditLogger::log('Tải xuống hàng loạt (ZIP)', 'documents', null, $docs->count() . ' tài liệu: ' . $docs->pluck('code')->implode(', '));

        return response()->download($zipPath, 'EDL_tai_lieu_' . now()->format('Ymd_His') . '.zip')->deleteFileAfterSend();
    }

    private function bulkStatus(Request $request, $docs)
    {
        abort_unless(user_can('documents.update'), Response::HTTP_FORBIDDEN);
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(config('edl.document_statuses')))]])['status'];

        $ids = $docs->filter(fn ($d) => DocumentScope::canManage($d))->pluck('id');
        DB::table('documents')->whereIn('id', $ids)->update(['status' => $status, 'updated_by' => auth()->id(), 'updated_at' => now()]);

        AuditLogger::log('Đổi tình trạng hàng loạt', 'documents', null, $ids->count() . " tài liệu → {$status}");

        return back()->with('success', 'Đã cập nhật tình trạng cho ' . $ids->count() . ' tài liệu.');
    }

    private function bulkTrash($docs)
    {
        abort_unless(user_can('documents.delete'), Response::HTTP_FORBIDDEN);

        $allowed = $docs->filter(fn ($d) => DocumentScope::canManage($d));
        DB::table('documents')->whereIn('id', $allowed->pluck('id'))->update(['deleted_at' => now(), 'deleted_by' => auth()->id()]);

        AuditLogger::log('Chuyển hết hiệu lực hàng loạt', 'documents', null, $allowed->count() . ' tài liệu: ' . $allowed->pluck('code')->implode(', '));

        $skipped = $docs->count() - $allowed->count();

        return back()->with('success', 'Đã chuyển ' . $allowed->count() . ' tài liệu sang hết hiệu lực.'
            . ($skipped ? " Bỏ qua {$skipped} tài liệu thuộc bộ phận khác." : ''));
    }

    /* ==================== XUẤT EXCEL (CSV UTF-8) ==================== */

    public function export(Request $request)
    {
        $rows = DocumentSearch::build($request)->limit(20000)->get();

        AuditLogger::log('Xuất danh sách tài liệu', 'documents', null, $rows->count() . ' dòng');

        $filename = 'EDL_danh_sach_tai_lieu_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt
            fputcsv($out, ['Mã tài liệu', 'Tiêu đề', 'Loại tài liệu', 'Mã thiết bị', 'Tên thiết bị', 'Bộ phận',
                'Tình trạng', 'Tên file', 'Định dạng',
                'Dung lượng', 'Lượt xem', 'Lượt tải', 'Người tải lên', 'Ngày tải lên', 'Liên kết']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->code, $r->title, $r->type_name, $r->device_code, $r->device_name, $r->department_name,
                    config("edl.document_statuses.{$r->status}.label", $r->status),
                    $r->original_filename, strtoupper($r->extension), FileType::humanSize($r->file_size),
                    $r->view_count, $r->download_count, $r->uploader_name,
                    Carbon::parse($r->created_at)->format('d/m/Y H:i'),
                    route('documents.show', $r->id),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ==================== DÙNG CHUNG ==================== */

    public function rules(): array
    {
        return [
            'title'            => 'required|string|max:500',
            'device_id'        => 'required|integer|exists:devices,id',
            'document_type_id' => 'nullable|integer|exists:document_types,id',
            'status'           => ['required', Rule::in(array_keys(config('edl.document_statuses')))],
            'language'         => 'nullable|string|max:10',
            'description'      => 'nullable|string|max:5000',
        ];
    }

    public function attributeNames(): array
    {
        return [
            'title' => 'tiêu đề', 'device_id' => 'thiết bị', 'document_type_id' => 'loại tài liệu',
            'status' => 'tình trạng', 'description' => 'mô tả',
        ];
    }

    /** Thiết bị đích phải thuộc bộ phận đang làm việc. */
    public function assertDeviceInScope(int $deviceId): void
    {
        $dept = DB::table('devices')->whereNull('deleted_at')->where('id', $deviceId)->value('department_id');

        abort_unless($dept !== null && works_on_department($dept), Response::HTTP_FORBIDDEN,
            'Thiết bị không thuộc bộ phận bạn đang làm việc.');
    }

    private function recordActivity(int $documentId, string $action): void
    {
        DB::table('document_activities')->insert([
            'user_id'     => auth()->id(),
            'document_id' => $documentId,
            'action'      => $action,
            'created_at'  => now(),
        ]);
    }
}
