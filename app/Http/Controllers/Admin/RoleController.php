<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\AccessControl;
use App\Services\Security\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Vai trò & ma trận quyền (nhóm quyền theo menu). Vai trò hệ thống không xoá được. */
class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.view', only: ['index']),
            new Middleware('permission:roles.manage', except: ['index']),
        ];
    }

    public function index()
    {
        $roles = DB::table('roles')
            ->select('roles.*')
            ->selectSub(DB::table('role_permission')->selectRaw('COUNT(*)')->whereColumn('role_permission.role_id', 'roles.id'), 'permissions_count')
            ->selectSub(DB::table('user_role')->selectRaw('COUNT(DISTINCT user_id)')->whereColumn('user_role.role_id', 'roles.id'), 'users_count')
            ->orderByDesc('is_system')->orderBy('name')
            ->get();

        return view('admin.roles.index', ['roles' => $roles, 'totalPermissions' => DB::table('permissions')->count()]);
    }

    public function create()
    {
        return view('admin.roles.form', ['role' => null, 'groups' => $this->groups(), 'selected' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $id = DB::transaction(function () use ($data, $request) {
            $id = DB::table('roles')->insertGetId($data + ['is_system' => false, 'created_at' => now(), 'updated_at' => now()]);
            $this->syncPermissions($id, $request->input('permissions', []));

            return $id;
        });

        AuditLogger::log('Thêm vai trò', 'roles', $id, $data['name'], null, ['permissions' => $this->slugs($id)]);

        return redirect()->route('roles.index')->with('success', "Đã thêm vai trò {$data['name']}.");
    }

    public function edit(int $role)
    {
        $record = $this->findOrFail($role);

        return view('admin.roles.form', [
            'role'     => $record,
            'groups'   => $this->groups(),
            'selected' => DB::table('role_permission')->where('role_id', $record->id)->pluck('permission_id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, int $role)
    {
        $record = $this->findOrFail($role);
        abort_if($record->slug === AccessControl::ADMIN_ROLE && ! user_is_admin(), Response::HTTP_FORBIDDEN);

        $data   = $this->validated($request, $record->id);
        $before = $this->slugs($record->id);

        DB::transaction(function () use ($record, $data, $request) {
            DB::table('roles')->where('id', $record->id)->update($data + ['updated_at' => now()]);
            // Vai trò admin luôn toàn quyền — không cần (và không cho) chỉnh ma trận
            if ($record->slug !== AccessControl::ADMIN_ROLE) {
                $this->syncPermissions($record->id, $request->input('permissions', []));
            }
        });

        AuditLogger::log('Sửa vai trò', 'roles', $record->id, $data['name'], ['permissions' => $before], ['permissions' => $this->slugs($record->id)]);

        return redirect()->route('roles.index')->with('success', "Đã cập nhật vai trò {$data['name']}.");
    }

    public function destroy(int $role)
    {
        $record = $this->findOrFail($role);

        if ($record->is_system) {
            return back()->with('error', 'Vai trò hệ thống không xoá được.');
        }
        if (DB::table('user_role')->where('role_id', $record->id)->exists()) {
            return back()->with('error', "Vai trò \"{$record->name}\" đang được gán cho người dùng — gỡ khỏi người dùng trước khi xoá.");
        }

        DB::table('roles')->where('id', $record->id)->delete();
        AuditLogger::log('Xoá vai trò', 'roles', $record->id, $record->name);

        return redirect()->route('roles.index')->with('success', "Đã xoá vai trò {$record->name}.");
    }

    private function findOrFail(int $id): object
    {
        $role = DB::table('roles')->where('id', $id)->first();
        abort_if($role === null, Response::HTTP_NOT_FOUND);

        return $role;
    }

    private function groups()
    {
        return DB::table('permission_groups')->orderBy('sort_order')->get()->map(function ($g) {
            $g->permissions = DB::table('permissions')->where('permission_group_id', $g->id)->orderBy('id')->get();

            return $g;
        });
    }

    private function validated(Request $request, ?int $id): array
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('roles', 'slug')->ignore($id)],
            'description' => 'nullable|string|max:255',
        ], [], ['name' => 'tên vai trò', 'slug' => 'mã vai trò']);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name'], '_');
        $data['description'] ??= null;

        if ($id) {
            // Không đổi mã của vai trò hệ thống (logic phân quyền dựa vào mã)
            $current = DB::table('roles')->where('id', $id)->first();
            if ($current->is_system) {
                $data['slug'] = $current->slug;
            }
        }

        return $data;
    }

    private function syncPermissions(int $roleId, array $permissionIds): void
    {
        $valid = DB::table('permissions')->whereIn('id', array_map('intval', $permissionIds))->pluck('id');

        DB::table('role_permission')->where('role_id', $roleId)->delete();
        if ($valid->isNotEmpty()) {
            DB::table('role_permission')->insert($valid->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])->all());
        }

        app(AccessControl::class)->flush();
    }

    private function slugs(int $roleId): array
    {
        return DB::table('permissions')->join('role_permission', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $roleId)->orderBy('slug')->pluck('slug')->all();
    }
}
