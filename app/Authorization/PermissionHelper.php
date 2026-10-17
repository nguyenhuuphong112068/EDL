<?php

/*
 * Hàm phân quyền dùng chung cho view / controller — cùng tên gọi với WMS
 * (user_can, user_can_any, user_has_permission…). Logic nằm ở AccessControl.
 */

use App\Services\Security\AccessControl;

if (! function_exists('access_control')) {
    function access_control(): AccessControl
    {
        return app(AccessControl::class);
    }
}

if (! function_exists('user_has_permission')) {
    /**
     * $typeReturn = 'boolean'  -> true / false
     * $typeReturn = 'disabled' -> '' nếu có quyền, 'disabled' nếu không (dùng cho thuộc tính input)
     */
    function user_has_permission($userId, string $slug, string $typeReturn = 'boolean')
    {
        $result = $userId ? access_control()->can((int) $userId, $slug) : false;

        if ($typeReturn === 'disabled') {
            return $result ? '' : 'disabled';
        }

        return $result;
    }
}

if (! function_exists('user_can')) {
    /** Bản rút gọn của user_has_permission cho user đang đăng nhập. */
    function user_can(string $slug, string $typeReturn = 'boolean')
    {
        return user_has_permission(auth()->id(), $slug, $typeReturn);
    }
}

if (! function_exists('user_can_any')) {
    /** Có ít nhất một trong các quyền — dùng cho mục menu / nút mở màn hình gộp. */
    function user_can_any(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if (user_can($slug)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('user_is_admin')) {
    function user_is_admin(): bool
    {
        return auth()->check() && access_control()->isAdmin((int) auth()->id());
    }
}

if (! function_exists('current_department_id')) {
    /** Bộ phận đang làm việc của phiên; 0 = "Tất cả bộ phận". */
    function current_department_id(): int
    {
        return auth()->check() ? access_control()->currentDepartmentId((int) auth()->id()) : -1;
    }
}

if (! function_exists('scope_department_ids')) {
    /** id bộ phận dùng để lọc dữ liệu theo phiên — null nghĩa là không giới hạn ("Tất cả"). */
    function scope_department_ids(): ?array
    {
        $current = current_department_id();

        return $current === 0 ? null : [$current];
    }
}

if (! function_exists('works_on_department')) {
    /** Bộ phận $departmentId nằm trong phạm vi làm việc hiện tại (được sửa dữ liệu của bộ phận đó). */
    function works_on_department($departmentId): bool
    {
        $scope = scope_department_ids();

        return $scope === null || in_array((int) $departmentId, $scope, true);
    }
}

if (! function_exists('can_access_department')) {
    function can_access_department($departmentId): bool
    {
        return auth()->check() && access_control()->canAccessDepartment((int) auth()->id(), (int) $departmentId);
    }
}
