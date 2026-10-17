<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Services\Documents\DocumentService;
use App\Services\Documents\DocumentStorage;
use App\Services\Documents\SmartFill;
use App\Services\Security\AuditLogger;
use App\Support\DocumentScope;
use App\Support\FileType;
use App\Support\Lookup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * TẢI LÊN HÀNG LOẠT — hai bước để thao tác nhanh với nhiều file:
 *
 *  1. Kéo thả nhiều file → mỗi file được tải ngay lên vùng tạm (có thanh tiến trình), hệ thống
 *     trả về: mã băm SHA-256 (báo trùng), gợi ý thiết bị / loại tài liệu
 *     từ tên file (SmartFill).
 *  2. Người dùng chỉnh thông tin trên lưới (có ô "Áp dụng cho tất cả / các dòng đã chọn"),
 *     bấm "Lưu tất cả" → mọi dòng được kiểm tra và lưu; dòng lỗi báo riêng, dòng đúng vẫn lưu.
 *
 * API trả JSON chuẩn {success, data, message} (theo quy ước WMS).
 */
class UploadController extends Controller implements HasMiddleware
{
    public function __construct(
        private DocumentStorage $storage,
        private DocumentService $documents,
        private SmartFill $smartFill,
    ) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:documents.upload')];
    }

    public function create(Request $request)
    {
        $devices = Lookup::devices();

        return view('documents.upload', [
            'devices'       => $devices,
            'documentTypes' => Lookup::options('document_types'),
            'presetDevice'  => $devices->firstWhere('id', (int) $request->input('device_id')),
            'presetType'    => (int) $request->input('document_type_id') ?: null,
            'allowed'       => FileType::allowedExtensions(),
        ]);
    }

    /** Bước 1: nhận một file vào vùng tạm. */
    public function temp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file'      => ['required', 'file', 'max:' . config('edl.storage.max_file_kb')],
            'device_id' => 'nullable|integer',
        ], [
            'file.max'      => 'File vượt quá dung lượng cho phép (' . FileType::humanSize(config('edl.storage.max_file_kb') * 1024) . ').',
            'file.uploaded' => 'Tải file thất bại — có thể file vượt giới hạn upload của máy chủ.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());

        if (! FileType::isAllowed($ext)) {
            return response()->json(['success' => false, 'message' => "Định dạng .{$ext} không được phép lưu (chặn file thực thi / chưa khai báo)."], 422);
        }

        $meta = $this->storage->storeTemp($file, auth()->id());

        // Báo trùng: cùng nội dung (SHA-256) đã có trong thư viện
        $duplicate = DocumentScope::query()->where('documents.checksum', $meta['checksum'])
            ->first(['documents.id', 'documents.code', 'documents.title', 'devices.code as device_code']);

        $presetDevice = (int) $request->input('device_id') ?: null;

        return response()->json([
            'success' => true,
            'data'    => [
                'token'     => $meta['token'],
                'name'      => $meta['original_name'],
                'size'      => $meta['size'],
                'size_label' => FileType::humanSize($meta['size']),
                'extension' => $ext,
                'color'     => FileType::color($ext),
                'group'     => FileType::groupLabel(FileType::group($ext)),
                'duplicate' => $duplicate ? [
                    'code'  => $duplicate->code,
                    'title' => $duplicate->title,
                    'device' => $duplicate->device_code,
                    'url'   => route('documents.show', $duplicate->id),
                ] : null,
                'suggest'   => $this->smartFill->suggest($meta['original_name'], $presetDevice),
            ],
            'message' => 'Đã tải lên vùng tạm',
        ]);
    }

    public function discard(string $token)
    {
        $this->storage->discardTemp(auth()->id(), $token);

        return response()->json(['success' => true, 'data' => null, 'message' => 'Đã bỏ file']);
    }

    /** Bước 2: lưu thông tin của nhiều file cùng lúc. */
    public function store(Request $request)
    {
        $items = $request->input('items', []);

        if (! is_array($items) || ! $items) {
            return response()->json(['success' => false, 'message' => 'Không có file nào để lưu.'], 422);
        }

        if (count($items) > config('edl.storage.max_batch_files')) {
            return response()->json(['success' => false, 'message' => 'Mỗi lượt lưu tối đa ' . config('edl.storage.max_batch_files') . ' file.'], 422);
        }

        $rules = [
            'token'            => 'required|string|size:40|alpha_num',
            'title'            => 'required|string|max:500',
            'device_id'        => 'required|integer',
            'document_type_id' => 'nullable|integer|exists:document_types,id',
            'status'           => ['required', Rule::in(array_keys(config('edl.document_statuses')))],
            'description'      => 'nullable|string|max:5000',
        ];
        $names = [
            'title' => 'tiêu đề', 'device_id' => 'thiết bị', 'document_type_id' => 'loại tài liệu',
        ];

        $workable = Lookup::devices()->keyBy('id');

        $results = [];
        $saved   = 0;

        foreach ($items as $index => $item) {
            $key = $item['key'] ?? $index;

            $validator = Validator::make($item, $rules, ['device_id.required' => 'Chưa chọn thiết bị.'], $names);

            $validator->after(function ($v) use ($item, $workable) {
                if (! empty($item['device_id']) && ! $workable->has((int) $item['device_id'])) {
                    $v->errors()->add('device_id', 'Thiết bị không thuộc bộ phận bạn đang làm việc.');
                }
            });

            if ($validator->fails()) {
                $results[] = ['key' => $key, 'success' => false, 'message' => $validator->errors()->first(), 'field' => $validator->errors()->keys()[0] ?? null];
                continue;
            }

            $data = $validator->validated();
            $meta = $this->storage->tempMeta(auth()->id(), $data['token']);

            if (! $meta) {
                $results[] = ['key' => $key, 'success' => false, 'message' => 'File tạm không còn — vui lòng chọn lại file.'];
                continue;
            }

            try {
                $id   = $this->documents->createFromTemp(auth()->id(), $meta, $data);
                $code = DB::table('documents')->where('id', $id)->value('code');
                $saved++;

                AuditLogger::log('Tải lên tài liệu', 'documents', $id, "{$code} — {$data['title']} ({$meta['original_name']})");

                $results[] = ['key' => $key, 'success' => true, 'message' => "Đã lưu — mã {$code}", 'id' => $id, 'code' => $code, 'url' => route('documents.show', $id)];
            } catch (Throwable $e) {
                report($e);
                $results[] = ['key' => $key, 'success' => false, 'message' => 'Lưu thất bại: ' . $e->getMessage()];
            }
        }

        $failed = count($results) - $saved;

        return response()->json([
            'success' => $saved > 0,
            'data'    => ['results' => $results, 'saved' => $saved, 'failed' => $failed],
            'message' => "Đã lưu {$saved} tài liệu" . ($failed ? ", {$failed} dòng cần kiểm tra lại" : '') . '.',
        ], $saved > 0 || ! $failed ? 200 : 422);
    }
}
