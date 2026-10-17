<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;

/**
 * PHÂN QUYỀN THEO BỘ PHẬN — chuyển từ app/Authorization/PermissionHelper.php của WMS.
 *
 *  - Vai trò gán cho user qua user_role kèm department_id: NULL = áp mọi bộ phận,
 *    có giá trị = chỉ có hiệu lực khi user đang làm việc ở bộ phận đó.
 *  - Quyền cấp riêng (user_permission) ghi đè kết quả từ vai trò: is_denied = 1 là chặn,
 *    = 0 là cấp thêm. Áp cho mọi bộ phận.
 *  - Vai trò "admin" (gán áp mọi bộ phận) có toàn bộ quyền ở mọi bộ phận.
 *  - Bộ phận đang làm việc lưu ở session (ô "Bộ phận" trên topbar). 0 = "Tất cả bộ phận",
 *    chỉ dành cho user được vào mọi bộ phận.
 *
 * Chỉ dùng Query Builder. Kết quả cache trong một request (đăng ký singleton).
 */
class AccessControl
{
    public const SESSION_KEY = 'edl.department_id';
    public const ADMIN_ROLE  = 'admin';

    /** @var array<string, array<string, true>> */
    private array $permissionCache = [];

    /** @var array<int, array<int, int>|string> */
    private array $departmentCache = [];

    /** @var array<int, bool> */
    private array $adminCache = [];

    public function isAdmin(int $userId): bool
    {
        return $this->adminCache[$userId] ??= DB::table('user_role')
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->where('user_role.user_id', $userId)
            ->whereNull('user_role.department_id')
            ->where('roles.slug', self::ADMIN_ROLE)
            ->exists();
    }

    public function can(int $userId, string $slug): bool
    {
        return isset($this->permissions($userId)[$slug]);
    }

    /** @return array<string, true> slug quyền user có tại bộ phận đang chọn (hoặc $departmentId) */
    public function permissions(int $userId, ?int $departmentId = null): array
    {
        $departmentId ??= $this->currentDepartmentId($userId);
        $key = $userId . ':' . $departmentId;

        if (isset($this->permissionCache[$key])) {
            return $this->permissionCache[$key];
        }

        if ($this->isAdmin($userId)) {
            return $this->permissionCache[$key] = array_fill_keys(DB::table('permissions')->pluck('slug')->all(), true);
        }

        $names = DB::table('permissions')
            ->join('role_permission', 'permissions.id', '=', 'role_permission.permission_id')
            ->join('user_role', 'role_permission.role_id', '=', 'user_role.role_id')
            ->where('user_role.user_id', $userId)
            ->where(function ($q) use ($departmentId) {
                $q->whereNull('user_role.department_id');
                if ($departmentId > 0) {
                    $q->orWhere('user_role.department_id', $departmentId);
                }
            })
            ->pluck('permissions.slug')
            ->mapWithKeys(fn ($slug) => [$slug => true])
            ->all();

        $overrides = DB::table('permissions')
            ->join('user_permission', 'permissions.id', '=', 'user_permission.permission_id')
            ->where('user_permission.user_id', $userId)
            ->pluck('user_permission.is_denied', 'permissions.slug');

        foreach ($overrides as $slug => $isDenied) {
            if ($isDenied) {
                unset($names[$slug]);
            } else {
                $names[$slug] = true;
            }
        }

        return $this->permissionCache[$key] = $names;
    }

    /**
     * Bộ phận user được vào làm việc.
     *  - '*' nếu có vai trò áp mọi bộ phận (kể cả admin);
     *  - ngược lại: bộ phận chính + các bộ phận được gán vai trò cụ thể.
     *
     * @return array<int, int>|string
     */
    public function allowedDepartmentIds(int $userId): array|string
    {
        if (isset($this->departmentCache[$userId])) {
            return $this->departmentCache[$userId];
        }

        $hasGlobalRole = DB::table('user_role')
            ->where('user_id', $userId)
            ->whereNull('department_id')
            ->exists();

        if ($hasGlobalRole) {
            return $this->departmentCache[$userId] = '*';
        }

        $ids = DB::table('user_role')
            ->where('user_id', $userId)
            ->whereNotNull('department_id')
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $primary = DB::table('users')->where('id', $userId)->value('department_id');
        if ($primary) {
            $ids[] = (int) $primary;
        }

        return $this->departmentCache[$userId] = array_values(array_unique($ids));
    }

    public function canAccessDepartment(int $userId, int $departmentId): bool
    {
        $allowed = $this->allowedDepartmentIds($userId);

        if ($departmentId === 0) {
            return $allowed === '*';
        }

        return $allowed === '*' || in_array($departmentId, $allowed, true);
    }

    /** Danh sách bộ phận cho ô chọn trên topbar. */
    public function selectableDepartments(int $userId)
    {
        $allowed = $this->allowedDepartmentIds($userId);

        return DB::table('departments')
            ->where('is_active', true)
            ->when($allowed !== '*', fn ($q) => $q->whereIn('id', $allowed ?: [0]))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Bộ phận đang làm việc. Lần đầu (hoặc giá trị cũ không còn hợp lệ) lấy bộ phận chính,
     * không có thì bộ phận đầu tiên được phép; user vào mọi bộ phận mà không có bộ phận chính
     * thì "Tất cả" (0). Không được vào bộ phận nào: -1 (mọi truy vấn lọc theo bộ phận ra rỗng).
     */
    public function currentDepartmentId(int $userId): int
    {
        $session = session(self::SESSION_KEY);

        if ($session !== null && $this->canAccessDepartment($userId, (int) $session)) {
            return (int) $session;
        }

        $primary  = (int) DB::table('users')->where('id', $userId)->value('department_id');
        $fallback = $primary && $this->canAccessDepartment($userId, $primary) ? $primary : null;

        if ($fallback === null) {
            $allowed  = $this->allowedDepartmentIds($userId);
            $fallback = $allowed === '*' ? 0 : ($allowed[0] ?? -1);
        }

        session([self::SESSION_KEY => $fallback]);

        return $fallback;
    }

    public function switchDepartment(int $userId, int $departmentId): bool
    {
        if (! $this->canAccessDepartment($userId, $departmentId)) {
            return false;
        }

        session([self::SESSION_KEY => $departmentId]);
        $this->permissionCache = [];

        return true;
    }

    public function flush(): void
    {
        $this->permissionCache = [];
        $this->departmentCache = [];
        $this->adminCache      = [];
    }
}
