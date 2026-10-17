<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Danh mục quyền theo nhóm menu + các vai trò mặc định.
 * Chạy lại được nhiều lần (updateOrInsert) — thêm quyền mới thì bổ sung vào đây rồi chạy lại.
 */
class PermissionSeeder extends Seeder
{
    public const GROUPS = [
        'Thư viện tài liệu' => [
            'documents.view'            => ['Xem / tra cứu tài liệu', 'Xem danh sách, chi tiết, xem trước file'],
            'documents.download'        => ['Tải xuống tài liệu', 'Tải file gốc, tải nhiều file dạng ZIP'],
            'documents.upload'          => ['Tải lên tài liệu', 'Tải lên đơn lẻ / hàng loạt'],
            'documents.update'          => ['Sửa tài liệu', 'Sửa thông tin, thay file (phiên bản mới), khôi phục phiên bản'],
            'documents.delete'          => ['Chuyển tài liệu hết hiệu lực', 'Ẩn tài liệu khỏi tra cứu — khôi phục được'],
            'documents.restore'         => ['Xem & khôi phục tài liệu hết hiệu lực', 'Đưa tài liệu hết hiệu lực trở lại sử dụng'],
            'documents.force_delete'    => ['Xoá vĩnh viễn', 'Xoá hẳn tài liệu hết hiệu lực và file khỏi máy chủ'],
            'documents.export'          => ['Xuất danh sách tài liệu', 'Xuất kết quả tìm kiếm ra Excel (CSV)'],
        ],
        'Thiết bị' => [
            'devices.view'   => ['Xem thiết bị', 'Xem danh sách, hồ sơ thiết bị, in nhãn QR'],
            'devices.create' => ['Thêm thiết bị', null],
            'devices.update' => ['Sửa thiết bị', null],
            'devices.delete' => ['Ngừng sử dụng / kích hoạt lại thiết bị', 'Xoá mềm: thiết bị và tài liệu được ẩn, kích hoạt lại được'],
        ],
        'Dữ liệu gốc' => [
            'master_data.view'   => ['Xem dữ liệu gốc', 'Loại tài liệu'],
            'master_data.manage' => ['Quản lý dữ liệu gốc', 'Thêm / sửa / xoá loại tài liệu'],
            'departments.manage' => ['Quản lý bộ phận', 'Thêm / sửa bộ phận quản lý'],
        ],
        'Quản trị hệ thống' => [
            'users.view'           => ['Xem người dùng', null],
            'users.manage'         => ['Quản lý người dùng', 'Thêm / sửa, gán vai trò theo bộ phận, cấp quyền riêng, mở khoá'],
            'users.reset_password' => ['Đặt lại mật khẩu người dùng', null],
            'roles.view'           => ['Xem vai trò', null],
            'roles.manage'         => ['Quản lý vai trò & phân quyền', null],
            'login_history.view'   => ['Xem lịch sử đăng nhập', 'Lượt đăng nhập của mọi tài khoản, phiên đang mở, nhập sai bất thường'],
            'audit.view'           => ['Xem nhật ký hệ thống', 'Audit trail: đăng nhập, xem, tải, sửa, xoá…'],
        ],
    ];

    public const ROLES = [
        'admin' => ['Quản trị hệ thống', 'Toàn quyền ở mọi bộ phận', true, '*'],
        'manager' => ['Trưởng bộ phận', 'Quản lý toàn bộ tài liệu, thiết bị và dữ liệu gốc của bộ phận', true, [
            'documents.view', 'documents.download', 'documents.upload', 'documents.update', 'documents.delete', 'documents.restore',
            'documents.force_delete', 'documents.export',
            'devices.view', 'devices.create', 'devices.update', 'devices.delete',
            'master_data.view', 'master_data.manage', 'users.view', 'audit.view',
        ]],
        'editor' => ['Nhân viên quản lý tài liệu', 'Tải lên, cập nhật tài liệu và thiết bị của bộ phận', true, [
            'documents.view', 'documents.download', 'documents.upload', 'documents.update', 'documents.delete', 'documents.restore',
            'documents.export', 'devices.view', 'devices.create', 'devices.update', 'master_data.view',
        ]],
        'viewer' => ['Người xem', 'Chỉ tra cứu, xem và tải tài liệu', true, [
            'documents.view', 'documents.download', 'devices.view',
        ]],
    ];

    public function run(): void
    {
        $order = 0;
        foreach (self::GROUPS as $groupName => $permissions) {
            DB::table('permission_groups')->updateOrInsert(['name' => $groupName], ['sort_order' => ++$order, 'updated_at' => now(), 'created_at' => now()]);
            $groupId = DB::table('permission_groups')->where('name', $groupName)->value('id');

            foreach ($permissions as $slug => [$display, $description]) {
                DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                    'permission_group_id' => $groupId,
                    'display_name'        => $display,
                    'description'         => $description,
                    'updated_at'          => now(),
                    'created_at'          => now(),
                ]);
            }
        }

        foreach (self::ROLES as $slug => [$name, $description, $system, $permissions]) {
            $exists = DB::table('roles')->where('slug', $slug)->exists();
            DB::table('roles')->updateOrInsert(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'is_system' => $system, 'updated_at' => now(), 'created_at' => now(),
            ]);

            // Không ghi đè ma trận quyền mà quản trị đã chỉnh trên giao diện
            if ($exists || $permissions === '*') {
                continue;
            }

            $roleId = DB::table('roles')->where('slug', $slug)->value('id');
            $ids    = DB::table('permissions')->whereIn('slug', $permissions)->pluck('id');
            DB::table('role_permission')->insertOrIgnore($ids->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])->all());
        }
    }
}
