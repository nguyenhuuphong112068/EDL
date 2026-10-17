<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHẠM VI TÀI LIỆU — truy vấn gốc mà mọi màn hình tài liệu dùng chung (Query Builder).
 *
 * Mọi tài liệu đều công khai: user có quyền documents.view tra cứu / xem được tài liệu của mọi bộ phận.
 * Chỉ tài liệu thuộc bộ phận đang làm việc mới được sửa / thay file / chuyển hết hiệu lực (canManage).
 * Tài liệu hết hiệu lực (xoá mềm), hoặc thuộc thiết bị đã ngừng sử dụng, không hiện.
 */
class DocumentScope
{
    /** Cột hay dùng khi liệt kê — không lấy content_text (rất dài). */
    public const LIST_COLUMNS = [
        'documents.id', 'documents.code', 'documents.title', 'documents.device_id', 'documents.document_type_id',
        'documents.revision_count',
        'documents.status', 'documents.original_filename',
        'documents.extension', 'documents.file_size', 'documents.view_count', 'documents.download_count',
        'documents.uploaded_by', 'documents.created_at', 'documents.updated_at', 'documents.description',
        'devices.code as device_code', 'devices.name as device_name', 'devices.department_id',
        'document_types.name as type_name', 'document_types.code as type_code', 'document_types.color as type_color',
        'departments.code as department_code', 'departments.name as department_name',
        'users.name as uploader_name',
    ];

    /** Truy vấn tài liệu đã join thiết bị / loại / bộ phận / người tải lên, đã áp phạm vi. */
    public static function query(bool $withJoins = true): Builder
    {
        $query = DB::table('documents')
            ->join('devices', 'devices.id', '=', 'documents.device_id')
            ->whereNull('documents.deleted_at')
            ->whereNull('devices.deleted_at');

        if ($withJoins) {
            $query->leftJoin('document_types', 'document_types.id', '=', 'documents.document_type_id')
                ->leftJoin('departments', 'departments.id', '=', 'devices.department_id')
                ->leftJoin('users', 'users.id', '=', 'documents.uploaded_by');
        }

        return $query;
    }

    /** Một tài liệu user được xem — không thấy thì 404 (không lộ là tài liệu có tồn tại). */
    public static function findOrFail(int $id): object
    {
        $doc = self::query()->where('documents.id', $id)
            ->select(array_merge(self::LIST_COLUMNS, [
                'documents.file_path', 'documents.mime_type', 'documents.checksum', 'documents.is_indexed',
                'documents.language', 'documents.updated_by',
            ]))
            ->first();

        abort_if($doc === null, Response::HTTP_NOT_FOUND, 'Không tìm thấy tài liệu (có thể đã hết hiệu lực).');

        return $doc;
    }

    /** Tài liệu thuộc bộ phận đang làm việc — mới được sửa / thay file / chuyển hết hiệu lực. */
    public static function canManage(object $doc): bool
    {
        return works_on_department($doc->department_id);
    }

    public static function authorizeManage(object $doc, string $permission): void
    {
        abort_unless(user_can($permission) && self::canManage($doc), Response::HTTP_FORBIDDEN,
            'Bạn không có quyền thao tác trên tài liệu này (tài liệu thuộc bộ phận khác hoặc thiếu quyền).');
    }
}
