<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Services\Security\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * DỮ LIỆU GỐC — khung chung cho mọi danh mục khai báo một lần, dùng lặp lại
 * (bộ phận, loại thiết bị, loại tài liệu, vị trí, nhà sản xuất, thẻ).
 *
 * Mỗi danh mục chỉ khai: bảng, tên route, các trường của form, cột hiển thị, luật kiểm tra
 * và bảng nào đang dùng nó (để đếm số lần sử dụng và chặn xoá mục đang dùng).
 * Giao diện dùng chung master-data/index + master-data/form nên mọi danh mục nhìn giống nhau.
 * Chỉ dùng Query Builder.
 */
abstract class MasterDataController extends Controller implements HasMiddleware
{
    protected string $table;
    protected string $routePrefix;
    protected string $title;
    protected string $singular;
    protected string $description = '';
    protected string $icon = 'database';

    /** Quyền xem / quản lý — khai static để dùng trong middleware(). */
    protected static string $viewPermission   = 'master_data.view';
    protected static string $managePermission = 'master_data.manage';

    /** Bảng đang tham chiếu tới danh mục: [bảng => cột khoá ngoại, …] */
    protected array $usages = [];

    /** Cột tìm kiếm */
    protected array $searchColumns = ['code', 'name'];

    protected string $orderBy = 'name';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:' . static::$viewPermission . '|' . static::$managePermission, only: ['index']),
            new Middleware('permission:' . static::$managePermission, except: ['index']),
        ];
    }

    /**
     * Trường của form: [tên cột => [label, type, required?, options?, hint?, placeholder?, span?]]
     * type: text | textarea | number | color | checkbox | select
     */
    abstract protected function fields(?object $record): array;

    /** Cột hiển thị ở danh sách: [key => [label, type]] — type: bold | text | mono | active | color | count | swatch */
    abstract protected function columns(): array;

    abstract protected function rules(?int $id): array;

    /** Truy vấn danh sách — lớp con có thể join thêm. */
    protected function listQuery()
    {
        return DB::table($this->table)->select("{$this->table}.*");
    }

    public function index(Request $request)
    {
        $query = $this->listQuery();

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($term) {
                foreach ($this->searchColumns as $col) {
                    $q->orWhere("{$this->table}.{$col}", 'like', $term);
                }
            });
        }

        if ($request->filled('status') && in_array('is_active', array_keys($this->fields(null)), true)) {
            $query->where("{$this->table}.is_active", $request->status === 'active');
        }

        // Đếm số lần đang được dùng bằng sub-select, tránh N+1
        foreach ($this->usages as $usageTable => $column) {
            $query->selectSub(
                DB::table($usageTable)->selectRaw('COUNT(*)')->whereColumn("{$usageTable}.{$column}", "{$this->table}.id")
                    ->when(in_array($usageTable, ['devices', 'documents'], true), fn ($q) => $q->whereNull("{$usageTable}.deleted_at")),
                "usage_{$usageTable}"
            );
        }

        $records = $query->orderBy($this->orderBy)->paginate(25)->withQueryString();

        return view('master-data.index', [
            'records'  => $records,
            'columns'  => $this->columns(),
            'usages'   => $this->usages,
            'meta'     => $this->meta(),
            'hasActive' => array_key_exists('is_active', $this->fields(null)),
        ]);
    }

    public function create()
    {
        return view('master-data.form', ['record' => null, 'fields' => $this->fields(null), 'meta' => $this->meta()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $data['created_at'] = $data['updated_at'] = now();

        $id = DB::table($this->table)->insertGetId($data);

        AuditLogger::log('Thêm ' . mb_strtolower($this->singular), $this->table, $id, $data['name'] ?? null, null, $data);

        return redirect()->route("{$this->routePrefix}.index")->with('success', "Đã thêm {$this->singular}: " . ($data['name'] ?? ''));
    }

    public function edit(int $id)
    {
        $record = $this->findOrFail($id);

        return view('master-data.form', ['record' => $record, 'fields' => $this->fields($record), 'meta' => $this->meta()]);
    }

    public function update(Request $request, int $id)
    {
        $before = (array) $this->findOrFail($id);
        $data   = $this->validated($request, $id);
        $data['updated_at'] = now();

        DB::table($this->table)->where('id', $id)->update($data);

        AuditLogger::changes('Sửa ' . mb_strtolower($this->singular), $this->table, $id, $before, $data, $data['name'] ?? null);

        return redirect()->route("{$this->routePrefix}.index")->with('success', "Đã cập nhật {$this->singular}: " . ($data['name'] ?? ''));
    }

    public function destroy(int $id)
    {
        $record = $this->findOrFail($id);

        foreach ($this->usages as $usageTable => $column) {
            if (DB::table($usageTable)->where($column, $id)->exists()) {
                return back()->with('error', "Không xoá được \"{$record->name}\" vì đang được sử dụng. Hãy bỏ chọn \"Đang dùng\" để ẩn khỏi danh sách chọn.");
            }
        }

        DB::table($this->table)->where('id', $id)->delete();
        AuditLogger::log('Xoá ' . mb_strtolower($this->singular), $this->table, $id, $record->name, (array) $record);

        return redirect()->route("{$this->routePrefix}.index")->with('success', "Đã xoá {$this->singular}: {$record->name}");
    }

    /* ---------------- Dùng chung ---------------- */

    protected function findOrFail(int $id): object
    {
        $record = DB::table($this->table)->where('id', $id)->first();
        abort_if($record === null, 404);

        return $record;
    }

    protected function validated(Request $request, ?int $id): array
    {
        $fields = $this->fields(null);
        $labels = array_map(fn ($f) => mb_strtolower($f['label']), $fields);
        $data   = $request->validate($this->rules($id), [], $labels);

        foreach ($fields as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $data[$name] = $request->boolean($name);
            } elseif (! array_key_exists($name, $data)) {
                $data[$name] = null;
            } elseif (is_string($data[$name])) {
                $data[$name] = trim($data[$name]) === '' ? null : trim($data[$name]);
            }

            if ($data[$name] === null && array_key_exists('default', $field)) {
                $data[$name] = $field['default'];
            }
        }

        if (isset($data['code'])) {
            $data['code'] = mb_strtoupper($data['code']);
        }

        return $data;
    }

    protected function unique(string $column, ?int $id)
    {
        return Rule::unique($this->table, $column)->ignore($id);
    }

    protected function meta(): array
    {
        return [
            'title'       => $this->title,
            'singular'    => $this->singular,
            'description' => $this->description,
            'route'       => $this->routePrefix,
            'icon'        => $this->icon,
            'canManage'   => user_can(static::$managePermission),
        ];
    }
}
