<?php

namespace App\Http\Controllers\MasterData;

/** Bộ phận quản lý — thiết bị và quyền của người dùng đều chia theo bộ phận. */
class DepartmentController extends MasterDataController
{
    protected string $table       = 'departments';
    protected string $routePrefix = 'departments';
    protected string $title       = 'Bộ phận quản lý';
    protected string $singular    = 'bộ phận';
    protected string $description = 'Mỗi thiết bị thuộc một bộ phận quản lý; người dùng được phân quyền làm việc theo bộ phận.';
    protected string $icon        = 'building';
    protected string $orderBy     = 'sort_order';

    protected static string $viewPermission   = 'departments.manage';
    protected static string $managePermission = 'departments.manage';

    protected array $usages = ['devices' => 'department_id', 'users' => 'department_id'];

    protected function fields(?object $record): array
    {
        return [
            'code'         => ['label' => 'Mã bộ phận', 'type' => 'text', 'required' => true, 'placeholder' => 'Vd: CD', 'span' => 1],
            'name'         => ['label' => 'Tên bộ phận', 'type' => 'text', 'required' => true, 'placeholder' => 'Vd: Phòng Cơ điện', 'span' => 2],
            'manager_name' => ['label' => 'Trưởng bộ phận', 'type' => 'text', 'span' => 2],
            'sort_order'   => ['label' => 'Thứ tự hiển thị', 'type' => 'number', 'default' => 0, 'span' => 1],
            'description'  => ['label' => 'Mô tả', 'type' => 'textarea', 'span' => 3],
            'is_active'    => ['label' => 'Đang hoạt động', 'type' => 'checkbox', 'hint' => 'Bỏ chọn để ẩn bộ phận khỏi các ô chọn.'],
        ];
    }

    protected function columns(): array
    {
        return [
            'code'         => ['label' => 'Mã', 'type' => 'mono'],
            'name'         => ['label' => 'Tên bộ phận', 'type' => 'bold'],
            'manager_name' => ['label' => 'Trưởng bộ phận', 'type' => 'text'],
            'usage_devices' => ['label' => 'Thiết bị', 'type' => 'count'],
            'usage_users'  => ['label' => 'Người dùng', 'type' => 'count'],
            'is_active'    => ['label' => 'Trạng thái', 'type' => 'active'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code'         => ['required', 'string', 'max:30', 'alpha_dash', $this->unique('code', $id)],
            'name'         => ['required', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'description'  => ['nullable', 'string', 'max:500'],
        ];
    }
}
