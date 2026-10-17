<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Services\Security\AuditLogger;
use App\Support\DocumentScope;
use App\Support\DocumentSearch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/** Tìm nhanh (ô tìm kiếm trên topbar, Ctrl+K) + bộ lọc đã lưu. */
class SearchController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:documents.view')];
    }

    public function quick(Request $request)
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['success' => true, 'data' => ['documents' => [], 'devices' => []], 'message' => '']);
        }

        // Ưu tiên khớp ở tiêu đề / mã, sau đó mới tới khớp trong nội dung file
        $titleQuery = DocumentScope::query();
        DocumentSearch::applyKeyword($titleQuery, $term, 'title');
        $docs = $titleQuery->orderByDesc('documents.view_count')->limit(7)
            ->get(['documents.id', 'documents.code', 'documents.title', 'documents.extension', 'devices.code as device_code', 'document_types.name as type_name']);

        $contentHits = collect();
        if ($docs->count() < 7) {
            $contentQuery = DocumentScope::query()->whereNotIn('documents.id', $docs->pluck('id')->all() ?: [0]);
            DocumentSearch::applyKeyword($contentQuery, $term, 'content');
            $contentHits = $contentQuery->limit(7 - $docs->count())
                ->get(['documents.id', 'documents.code', 'documents.title', 'documents.extension', 'devices.code as device_code', 'document_types.name as type_name']);
        }

        $scope   = scope_department_ids();
        $like    = '%' . addcslashes($term, '%_') . '%';
        $devices = user_can('devices.view')
            ? DB::table('devices')
                ->leftJoin('departments', 'departments.id', '=', 'devices.department_id')
                ->whereNull('devices.deleted_at')
                ->when($scope !== null, fn ($q) => $q->whereIn('devices.department_id', $scope))
                ->where(fn ($q) => $q->where('devices.code', 'like', $like)->orWhere('devices.name', 'like', $like))
                ->limit(5)
                ->get(['devices.id', 'devices.code', 'devices.name', 'devices.status', 'departments.name as department_name'])
            : collect();

        $mapDoc = fn ($d, $inContent) => [
            'url'        => route('documents.show', $d->id),
            'title'      => $d->title,
            'code'       => $d->code,
            'device'     => $d->device_code,
            'type'       => $d->type_name,
            'icon'       => file_icon($d->extension),
            'in_content' => $inContent,
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'documents' => $docs->map(fn ($d) => $mapDoc($d, false))->merge($contentHits->map(fn ($d) => $mapDoc($d, true)))->values(),
                'devices'   => $devices->map(fn ($d) => [
                    'url'  => route('devices.show', $d->id),
                    'code' => $d->code,
                    'name' => $d->name,
                    'meta' => collect([$d->department_name, \App\Support\Labels::deviceStatus($d->status)])->filter()->implode(' · '),
                ]),
            ],
            'message' => '',
        ]);
    }

    public function save(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100']);

        $query = collect($request->only(DocumentSearch::FILTER_KEYS))->filter(fn ($v) => $v !== null && $v !== '')->all();

        DB::table('saved_searches')->insert([
            'user_id'    => auth()->id(),
            'name'       => $request->name,
            'query'      => json_encode($query, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLogger::log('Lưu bộ lọc tìm kiếm', 'saved_searches', null, $request->name);

        return redirect()->route('documents.index', $query)->with('success', "Đã lưu bộ lọc \"{$request->name}\".");
    }

    public function forget(int $savedSearch)
    {
        DB::table('saved_searches')->where('id', $savedSearch)->where('user_id', auth()->id())->delete();

        return back()->with('success', 'Đã xoá bộ lọc đã lưu.');
    }
}
