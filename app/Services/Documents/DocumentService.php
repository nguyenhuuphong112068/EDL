<?php

namespace App\Services\Documents;

use App\Services\Security\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nghiệp vụ tài liệu (Query Builder): tạo từ file tạm, thay file (lưu phiên bản cũ),
 * khôi phục phiên bản, sinh mã tài liệu, đánh chỉ mục toàn văn.
 */
class DocumentService
{
    public function __construct(
        private DocumentStorage $storage,
        private TextExtractor $extractor,
    ) {}

    /**
     * Mã tài liệu: {MÃ LOẠI}-{MÃ THIẾT BỊ}-{STT 3 số}, vd HDSD-MAY-001-002.
     * Đếm cả tài liệu hết hiệu lực để không cấp lại mã đã dùng.
     */
    public function generateCode(object $device, ?object $type): string
    {
        $prefix = Str::upper(($type->code ?? 'TL') . '-' . $device->code) . '-';
        $prefix = preg_replace('/[^A-Z0-9\-_.]/', '', Str::ascii($prefix)) ?: 'TL-';

        $last = DB::table('documents')->where('code', 'like', $prefix . '%')
            ->pluck('code')
            ->map(fn ($c) => (int) substr($c, strlen($prefix)))
            ->max() ?? 0;

        do {
            $last++;
            $code = $prefix . str_pad((string) $last, 3, '0', STR_PAD_LEFT);
        } while (DB::table('documents')->where('code', $code)->exists());

        return $code;
    }

    /**
     * Tạo tài liệu từ một file tạm đã tải lên.
     * $data đã được kiểm tra hợp lệ ở controller.
     */
    public function createFromTemp(int $userId, array $meta, array $data): int
    {
        $device = DB::table('devices')->where('id', $data['device_id'])->first();
        $type   = $data['document_type_id'] ? DB::table('document_types')->where('id', $data['document_type_id'])->first() : null;

        $path = $this->storage->promoteTemp($userId, $meta, $device->id, $type->code ?? null);

        try {
            return DB::transaction(function () use ($userId, $meta, $data, $device, $type, $path) {
                $id = DB::table('documents')->insertGetId([
                    'code'              => $this->generateCode($device, $type),
                    'title'             => $data['title'],
                    'device_id'         => $device->id,
                    'document_type_id'  => $type->id ?? null,
                    'description'       => $data['description'] ?? null,
                    'revision_count'    => 1,
                    'status'            => $data['status'] ?? 'active',
                    'language'          => $data['language'] ?? null,
                    'file_path'         => $path,
                    'original_filename' => $meta['original_name'],
                    'extension'         => $meta['extension'],
                    'mime_type'         => $meta['mime_type'],
                    'file_size'         => $meta['size'],
                    'checksum'          => $meta['checksum'],
                    'uploaded_by'       => $userId,
                    'updated_by'        => $userId,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                DB::table('document_versions')->insert([
                    'document_id'       => $id,
                    'revision'          => 1,
                    'file_path'         => $path,
                    'original_filename' => $meta['original_name'],
                    'extension'         => $meta['extension'],
                    'mime_type'         => $meta['mime_type'],
                    'file_size'         => $meta['size'],
                    'checksum'          => $meta['checksum'],
                    'change_note'       => 'Tải lên lần đầu',
                    'uploaded_by'       => $userId,
                    'created_at'        => now(),
                ]);

                $this->index($id);

                return $id;
            });
        } catch (Throwable $e) {
            // Lưu DB lỗi thì bỏ file đã chuyển, không để file mồ côi
            $this->storage->delete($path);
            throw $e;
        }
    }

    /**
     * Thay file của tài liệu — file mới thành bản hiện hành, file cũ vẫn giữ trong lịch sử
     * thay file (document_versions) để xem lại / khôi phục.
     */
    public function replaceFile(object $doc, UploadedFile $file, ?string $note, int $userId): void
    {
        $typeCode = $doc->type_code ?? null;
        $path     = $this->storage->storeUploaded($file, $doc->device_id, $typeCode);
        $revision = (int) DB::table('document_versions')->where('document_id', $doc->id)->max('revision') + 1;
        $ext      = strtolower($file->getClientOriginalExtension());
        $checksum = hash_file('sha256', $this->storage->absolutePath($path));

        DB::transaction(function () use ($doc, $file, $note, $userId, $path, $revision, $ext, $checksum) {
            DB::table('document_versions')->insert([
                'document_id'       => $doc->id,
                'revision'          => $revision,
                'file_path'         => $path,
                'original_filename' => $this->storage->cleanName($file->getClientOriginalName()),
                'extension'         => $ext,
                'mime_type'         => $file->getMimeType(),
                'file_size'         => $file->getSize(),
                'checksum'          => $checksum,
                'change_note'       => $note,
                'uploaded_by'       => $userId,
                'created_at'        => now(),
            ]);

            DB::table('documents')->where('id', $doc->id)->update([
                'revision_count'    => $revision,
                'file_path'         => $path,
                'original_filename' => $this->storage->cleanName($file->getClientOriginalName()),
                'extension'         => $ext,
                'mime_type'         => $file->getMimeType(),
                'file_size'         => $file->getSize(),
                'checksum'          => $checksum,
                'updated_by'        => $userId,
                'updated_at'        => now(),
            ]);
        });

        $this->index($doc->id);

        AuditLogger::log('Thay file tài liệu', 'documents', $doc->id,
            "{$doc->code}: lần thay file thứ {$revision}" . ($note ? " — {$note}" : ''),
            ['file' => $doc->original_filename],
            ['file' => $file->getClientOriginalName()]);
    }

    /** Đưa một phiên bản cũ trở lại làm bản hiện hành (ghi thành một lần thay file mới). */
    public function restoreVersion(object $doc, object $version, int $userId): void
    {
        $revision = (int) DB::table('document_versions')->where('document_id', $doc->id)->max('revision') + 1;

        DB::transaction(function () use ($doc, $version, $userId, $revision) {
            DB::table('document_versions')->insert([
                'document_id'       => $doc->id,
                'revision'          => $revision,
                'file_path'         => $version->file_path,
                'original_filename' => $version->original_filename,
                'extension'         => $version->extension,
                'mime_type'         => $version->mime_type,
                'file_size'         => $version->file_size,
                'checksum'          => $version->checksum,
                'change_note'       => "Khôi phục từ lần {$version->revision}",
                'uploaded_by'       => $userId,
                'created_at'        => now(),
            ]);

            DB::table('documents')->where('id', $doc->id)->update([
                'revision_count'    => $revision,
                'file_path'         => $version->file_path,
                'original_filename' => $version->original_filename,
                'extension'         => $version->extension,
                'mime_type'         => $version->mime_type,
                'file_size'         => $version->file_size,
                'checksum'          => $version->checksum,
                'updated_by'        => $userId,
                'updated_at'        => now(),
            ]);
        });

        $this->index($doc->id);

        AuditLogger::log('Khôi phục phiên bản', 'documents', $doc->id, "{$doc->code}: khôi phục file của lần {$version->revision}");
    }

    /** Đánh chỉ mục toàn văn cho file hiện hành. */
    public function index(int $documentId): void
    {
        $doc = DB::table('documents')->where('id', $documentId)->first(['file_path', 'extension']);

        if (! $doc || ! $this->storage->exists($doc->file_path)) {
            return;
        }

        $text = $this->extractor->extract($this->storage->absolutePath($doc->file_path), $doc->extension);

        DB::table('documents')->where('id', $documentId)->update([
            'content_text' => $text,
            'is_indexed'   => $text !== null,
        ]);
    }

    /** Xoá vĩnh viễn: bản ghi + mọi file của mọi phiên bản. */
    public function forceDelete(int $documentId): void
    {
        $paths = DB::table('document_versions')->where('document_id', $documentId)->pluck('file_path')
            ->push(DB::table('documents')->where('id', $documentId)->value('file_path'))
            ->filter()->unique();

        DB::table('documents')->where('id', $documentId)->delete();

        foreach ($paths as $path) {
            // File có thể được bản ghi khác dùng chung (khôi phục phiên bản) — chỉ xoá khi không còn ai dùng
            if (! DB::table('document_versions')->where('file_path', $path)->exists()
                && ! DB::table('documents')->where('file_path', $path)->exists()) {
                $this->storage->delete($path);
            }
        }
    }
}
