<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 1. Bỏ mức bảo mật: mọi tài liệu đều công khai cho mọi bộ phận tra cứu / xem
 *    → xoá cột documents.confidentiality và quyền documents.view_restricted.
 * 2. Gộp "Hết hiệu lực" với "đã xoá": tài liệu hết hiệu lực = xoá mềm (deleted_at), khôi phục được.
 *    Tài liệu đang ở tình trạng obsolete được chuyển sang hết hiệu lực (xoá mềm), tình trạng đặt lại active.
 * Giá trị cũ được sao lưu ra storage/app/private/backups/ trước khi đổi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasConf = Schema::hasColumn('documents', 'confidentiality');

        Storage::disk('local')->put('backups/documents_confidentiality_status_' . now()->format('Ymd_His') . '.json', json_encode([
            'documents' => DB::table('documents')->select(array_merge(['id', 'code', 'status', 'deleted_at'], $hasConf ? ['confidentiality'] : []))->get(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        DB::table('documents')->where('status', 'obsolete')->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'status' => 'active', 'updated_at' => now()]);
        DB::table('documents')->where('status', 'obsolete')->update(['status' => 'active']);

        if ($hasConf) {
            Schema::table('documents', fn (Blueprint $table) => $table->dropColumn('confidentiality'));
        }

        $permId = DB::table('permissions')->where('slug', 'documents.view_restricted')->value('id');
        if ($permId) {
            DB::table('role_permission')->where('permission_id', $permId)->delete();
            DB::table('user_permission')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }

        $labels = [
            'documents.delete'       => ['Chuyển tài liệu hết hiệu lực', 'Ẩn tài liệu khỏi tra cứu — khôi phục được'],
            'documents.restore'      => ['Xem & khôi phục tài liệu hết hiệu lực', 'Đưa tài liệu hết hiệu lực trở lại sử dụng'],
            'documents.force_delete' => ['Xoá vĩnh viễn', 'Xoá hẳn tài liệu hết hiệu lực và file khỏi máy chủ'],
        ];
        foreach ($labels as $slug => [$display, $description]) {
            DB::table('permissions')->where('slug', $slug)->update(['display_name' => $display, 'description' => $description, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('documents', 'confidentiality')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('confidentiality', 20)->default('public')->after('status');
            });
        }
    }
};
