<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed                 → quyền, vai trò, bộ phận, dữ liệu gốc, tài khoản admin
     * php artisan db:seed --class=DemoSeeder → thêm thiết bị + tài liệu mẫu để dùng thử
     *
     * Tài khoản quản trị ban đầu: tên đăng nhập "admin", mật khẩu lấy từ biến môi trường
     * EDL_ADMIN_PASSWORD (mặc định ghi trong README). Hệ thống buộc đổi mật khẩu khi đăng nhập
     * lần đầu nếu EDL_ADMIN_FORCE_CHANGE=true.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(MasterDataSeeder::class);

        if (! DB::table('users')->where('userName', 'admin')->exists()) {
            $hash = Hash::make(env('EDL_ADMIN_PASSWORD') ?: 'Admin@123');
            $deptId = DB::table('departments')->orderBy('sort_order')->value('id');

            $id = DB::table('users')->insertGetId([
                'name'                 => 'Quản trị hệ thống',
                'userName'             => 'admin',
                'position'             => 'Quản trị hệ thống',
                'department_id'        => $deptId,
                'password'             => $hash,
                'is_active'            => true,
                'password_expires_at'  => today()->addDays(config('edl.security.password_validity_days')),
                'must_change_password' => (bool) env('EDL_ADMIN_FORCE_CHANGE', false),
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            DB::table('password_histories')->insert(['user_id' => $id, 'password_hash' => $hash, 'created_by' => 'seeder', 'created_at' => now()]);
            DB::table('user_role')->insert(['user_id' => $id, 'role_id' => DB::table('roles')->where('slug', 'admin')->value('id'), 'department_id' => null]);
        }
    }
}
