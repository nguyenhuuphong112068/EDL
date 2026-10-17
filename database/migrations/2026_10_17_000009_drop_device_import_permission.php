<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Bỏ chức năng nhập thiết bị từ file → xoá quyền devices.import khỏi vai trò, quyền riêng và danh mục quyền. */
return new class extends Migration
{
    public function up(): void
    {
        $permId = DB::table('permissions')->where('slug', 'devices.import')->value('id');

        if ($permId) {
            DB::table('role_permission')->where('permission_id', $permId)->delete();
            DB::table('user_permission')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }

    public function down(): void
    {
        // Không khôi phục: chức năng nhập từ file đã bị gỡ khỏi phần mềm
    }
};
