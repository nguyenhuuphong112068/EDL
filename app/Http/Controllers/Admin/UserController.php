<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\AccessControl;
use App\Services\Security\AuditLogger;
use App\Services\Security\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * NGƯỜI DÙNG — theo mô hình WMS:
 *  - mỗi user có một bộ phận chính, được gán nhiều vai trò, mỗi vai trò áp cho một bộ phận
 *    cụ thể hoặc cho mọi bộ phận;
 *  - quyền cấp riêng / chặn riêng từng user (3 trạng thái: theo vai trò / cấp / chặn);
 *  - quản trị đặt lại mật khẩu → user buộc đổi ở lần đăng nhập tới; mở khoá tài khoản bị khoá.
 * Chỉ người có vai trò admin mới gán được vai trò admin (chống leo thang quyền).
 */
class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.view', only: ['index']),
            new Middleware('permission:users.manage', only: ['create', 'store', 'edit', 'update', 'toggle', 'unlock', 'permissions', 'updatePermissions']),
            new Middleware('permission:users.reset_password', only: ['resetPassword']),
        ];
    }

    public function index(Request $request)
    {
        $users = DB::table('users')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->select('users.*', 'departments.code as department_code', 'departments.name as department_name')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('users.name', 'like', '%' . $request->q . '%')
                ->orWhere('users.userName', 'like', '%' . $request->q . '%')
                ->orWhere('users.email', 'like', '%' . $request->q . '%')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('users.department_id', $request->department_id))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('users.is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('users.is_active', false))
            ->when($request->input('status') === 'locked', fn ($q) => $q->where('users.locked_until', '>', now()))
            ->orderBy('users.name')
            ->paginate(25)->withQueryString();

        $roles = DB::table('user_role')
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->leftJoin('departments', 'departments.id', '=', 'user_role.department_id')
            ->whereIn('user_role.user_id', $users->pluck('id'))
            ->get(['user_role.user_id', 'roles.name', 'roles.slug', 'departments.code as department_code'])
            ->groupBy('user_id');

        return view('admin.users.index', [
            'users'       => $users,
            'roles'       => $roles,
            'departments' => DB::table('departments')->orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.users.form', $this->formData(null));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $id = DB::transaction(function () use ($data, $request) {
            $id = DB::table('users')->insertGetId([
                'name'                 => $data['name'],
                'userName'             => $data['userName'],
                'email'                => $data['email'] ?? null,
                'position'             => $data['position'] ?? null,
                'department_id'        => $data['department_id'] ?? null,
                'is_active'            => $request->boolean('is_active'),
                'password'             => Hash::make($data['password']),
                'password_expires_at'  => today()->addDays(config('edl.security.password_validity_days')),
                'must_change_password' => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            DB::table('password_histories')->insert([
                'user_id' => $id, 'password_hash' => DB::table('users')->where('id', $id)->value('password'),
                'created_by' => auth()->user()->userName, 'created_at' => now(),
            ]);

            $this->syncRoles($id, $request->input('assignments', []));

            return $id;
        });

        AuditLogger::log('Thêm người dùng', 'users', $id, "{$data['userName']} — {$data['name']}", null,
            collect($data)->except('password', 'password_confirmation')->all() + ['roles' => $this->roleSummary($id)]);

        return redirect()->route('users.index')->with('success', "Đã tạo tài khoản {$data['userName']}. Người dùng sẽ phải đổi mật khẩu ở lần đăng nhập đầu tiên.");
    }

    public function edit(int $user)
    {
        return view('admin.users.form', $this->formData($this->findOrFail($user)));
    }

    public function update(Request $request, int $user)
    {
        $record = $this->findOrFail($user);
        $data   = $this->validated($request, $record->id);

        if ($record->id === auth()->id() && ! $request->boolean('is_active')) {
            return back()->with('error', 'Không thể tự vô hiệu hoá tài khoản đang đăng nhập.');
        }

        $before      = (array) $record;
        $rolesBefore = $this->roleSummary($record->id);
        $update      = [
            'name'          => $data['name'],
            'userName'      => $data['userName'],
            'email'         => $data['email'] ?? null,
            'position'      => $data['position'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'is_active'     => $request->boolean('is_active'),
            'updated_at'    => now(),
        ];

        DB::transaction(function () use ($record, $update, $request) {
            DB::table('users')->where('id', $record->id)->update($update);
            $this->syncRoles($record->id, $request->input('assignments', []));
        });

        AuditLogger::changes('Sửa người dùng', 'users', $record->id, $before, $update, $record->userName);
        $rolesAfter = $this->roleSummary($record->id);
        if ($rolesBefore !== $rolesAfter) {
            AuditLogger::log('Đổi vai trò người dùng', 'users', $record->id, $record->userName, ['roles' => $rolesBefore], ['roles' => $rolesAfter]);
        }

        return redirect()->route('users.index')->with('success', "Đã cập nhật tài khoản {$update['userName']}.");
    }

    public function resetPassword(Request $request, int $user)
    {
        $record = $this->findOrFail($user);
        $request->validate(['new_password' => PasswordPolicy::rules()], PasswordPolicy::messages('new_password'), ['new_password' => 'mật khẩu mới']);

        PasswordPolicy::set($record->id, $request->new_password, true, auth()->user()->userName);
        AuditLogger::log('Đặt lại mật khẩu', 'users', $record->id, "Quản trị đặt lại mật khẩu cho {$record->userName} — buộc đổi lần đăng nhập tới");

        return back()->with('success', "Đã đặt lại mật khẩu cho {$record->userName}. Người dùng sẽ phải đổi mật khẩu khi đăng nhập.");
    }

    public function unlock(int $user)
    {
        $record = $this->findOrFail($user);
        DB::table('users')->where('id', $record->id)->update(['locked_until' => null, 'failed_login_attempts' => 0]);
        AuditLogger::log('Mở khoá tài khoản', 'users', $record->id, $record->userName);

        return back()->with('success', "Đã mở khoá tài khoản {$record->userName}.");
    }

    public function toggle(int $user)
    {
        $record = $this->findOrFail($user);
        abort_if($record->id === auth()->id(), Response::HTTP_FORBIDDEN, 'Không thể tự vô hiệu hoá tài khoản đang đăng nhập.');

        DB::table('users')->where('id', $record->id)->update(['is_active' => ! $record->is_active, 'updated_at' => now()]);
        AuditLogger::log($record->is_active ? 'Vô hiệu hoá tài khoản' : 'Kích hoạt tài khoản', 'users', $record->id, $record->userName);

        return back()->with('success', ($record->is_active ? 'Đã vô hiệu hoá ' : 'Đã kích hoạt ') . $record->userName);
    }

    /* ---------- Quyền cấp riêng (allow / deny / inherit) ---------- */

    public function permissions(int $user, AccessControl $access)
    {
        $record = $this->findOrFail($user);

        $fromRole = DB::table('role_permission')
            ->join('user_role', 'role_permission.role_id', '=', 'user_role.role_id')
            ->where('user_role.user_id', $record->id)
            ->pluck('role_permission.permission_id')->map(fn ($id) => (int) $id)->flip()->all();

        $overrides = DB::table('user_permission')->where('user_id', $record->id)->pluck('is_denied', 'permission_id')->all();

        $groups = DB::table('permission_groups')->orderBy('sort_order')->get()->map(function ($g) use ($fromRole, $overrides) {
            $g->permissions = DB::table('permissions')->where('permission_group_id', $g->id)->orderBy('id')->get()
                ->map(function ($p) use ($fromRole, $overrides) {
                    $p->from_role = isset($fromRole[$p->id]);
                    $p->state     = array_key_exists($p->id, $overrides) ? ($overrides[$p->id] ? 'deny' : 'allow') : 'inherit';

                    return $p;
                });

            return $g;
        });

        return view('admin.users.permissions', ['user' => $record, 'groups' => $groups, 'isAdmin' => $access->isAdmin($record->id)]);
    }

    public function updatePermissions(Request $request, int $user)
    {
        $record = $this->findOrFail($user);
        $states = (array) $request->input('perm', []);
        $valid  = DB::table('permissions')->pluck('slug', 'id');

        $before = DB::table('user_permission')->join('permissions', 'permissions.id', '=', 'user_permission.permission_id')
            ->where('user_id', $record->id)->pluck('user_permission.is_denied', 'permissions.slug')->all();

        DB::transaction(function () use ($record, $states, $valid) {
            DB::table('user_permission')->where('user_id', $record->id)->delete();
            $rows = [];
            foreach ($states as $permId => $state) {
                if (isset($valid[(int) $permId]) && in_array($state, ['allow', 'deny'], true)) {
                    $rows[] = ['user_id' => $record->id, 'permission_id' => (int) $permId, 'is_denied' => $state === 'deny'];
                }
            }
            if ($rows) {
                DB::table('user_permission')->insert($rows);
            }
        });

        $after = DB::table('user_permission')->join('permissions', 'permissions.id', '=', 'user_permission.permission_id')
            ->where('user_id', $record->id)->pluck('user_permission.is_denied', 'permissions.slug')->all();

        AuditLogger::log('Cấp quyền riêng', 'users', $record->id, $record->userName,
            array_map(fn ($d) => $d ? 'deny' : 'allow', $before), array_map(fn ($d) => $d ? 'deny' : 'allow', $after));

        return redirect()->route('users.permissions', $record->id)->with('success', "Đã lưu quyền riêng của {$record->userName}.");
    }

    /* ---------- Dùng chung ---------- */

    private function findOrFail(int $id): object
    {
        $user = DB::table('users')->where('id', $id)->first();
        abort_if($user === null, Response::HTTP_NOT_FOUND);

        return $user;
    }

    private function formData(?object $user): array
    {
        return [
            'user'        => $user,
            'departments' => DB::table('departments')->orderBy('sort_order')->orderBy('name')->get(),
            'roles'       => DB::table('roles')->orderBy('name')->get(),
            'assignments' => $user ? DB::table('user_role')->where('user_id', $user->id)->get() : collect(),
            'canAssignAdmin' => user_is_admin(),
        ];
    }

    private function validated(Request $request, ?int $id): array
    {
        $rules = [
            'name'                         => 'required|string|max:255',
            'userName'                     => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\-]+$/', Rule::unique('users', 'userName')->ignore($id)],
            'email'                        => 'nullable|email|max:255',
            'position'                     => 'nullable|string|max:255',
            'department_id'                => 'nullable|integer|exists:departments,id',
            'assignments'                  => 'nullable|array',
            'assignments.*.role_id'        => 'required|integer|exists:roles,id',
            'assignments.*.department_id'  => 'nullable|integer|exists:departments,id',
        ];

        if ($id === null) {
            $rules['password'] = array_merge(PasswordPolicy::rules(), ['confirmed']);
        }

        $data = $request->validate($rules, PasswordPolicy::messages() + [
            'userName.regex' => 'Tên đăng nhập chỉ gồm chữ không dấu, số và . _ -',
        ], ['name' => 'họ tên', 'userName' => 'tên đăng nhập', 'department_id' => 'bộ phận chính', 'password' => 'mật khẩu']);

        // Chống leo thang quyền: chỉ admin mới gán / giữ vai trò admin
        $adminRoleId = (int) DB::table('roles')->where('slug', AccessControl::ADMIN_ROLE)->value('id');
        $wantsAdmin  = collect($request->input('assignments', []))->contains(fn ($a) => (int) ($a['role_id'] ?? 0) === $adminRoleId);
        abort_if($wantsAdmin && ! user_is_admin(), Response::HTTP_FORBIDDEN, 'Chỉ quản trị hệ thống mới gán được vai trò Quản trị hệ thống.');

        return $data;
    }

    private function syncRoles(int $userId, array $assignments): void
    {
        DB::table('user_role')->where('user_id', $userId)->delete();

        $rows = collect($assignments)
            ->filter(fn ($a) => ! empty($a['role_id']))
            ->map(fn ($a) => ['user_id' => $userId, 'role_id' => (int) $a['role_id'], 'department_id' => ! empty($a['department_id']) ? (int) $a['department_id'] : null])
            ->unique(fn ($r) => $r['role_id'] . ':' . $r['department_id'])
            ->values()->all();

        if ($rows) {
            DB::table('user_role')->insert($rows);
        }

        app(AccessControl::class)->flush();
    }

    private function roleSummary(int $userId): array
    {
        return DB::table('user_role')
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->leftJoin('departments', 'departments.id', '=', 'user_role.department_id')
            ->where('user_role.user_id', $userId)
            ->orderBy('roles.slug')
            ->get(['roles.slug', 'departments.code'])
            ->map(fn ($r) => $r->slug . '@' . ($r->code ?? '*'))
            ->all();
    }
}
