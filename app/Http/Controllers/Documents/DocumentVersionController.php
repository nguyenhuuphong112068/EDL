<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Services\Documents\DocumentService;
use App\Services\Documents\DocumentStorage;
use App\Services\Security\AuditLogger;
use App\Support\DocumentScope;
use App\Support\FileType;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Lịch sử phiên bản file: thay file mới, tải bản cũ, khôi phục bản cũ. */
class DocumentVersionController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentService $documents, private DocumentStorage $storage) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:documents.update', only: ['store', 'restore']),
            new Middleware('permission:documents.download', only: ['download']),
        ];
    }

    public function store(Request $request, int $document)
    {
        $doc = DocumentScope::findOrFail($document);
        DocumentScope::authorizeManage($doc, 'documents.update');

        $request->validate([
            'file'        => ['required', 'file', 'max:' . config('edl.storage.max_file_kb')],
            'change_note' => 'nullable|string|max:1000',
        ], [], ['file' => 'file', 'change_note' => 'nội dung thay đổi']);

        $file = $request->file('file');
        if (! FileType::isAllowed($file->getClientOriginalExtension())) {
            return back()->with('error', 'Định dạng .' . $file->getClientOriginalExtension() . ' không được phép lưu.');
        }

        $this->documents->replaceFile($doc, $file, $request->change_note, auth()->id());

        return redirect()->route('documents.show', $doc->id)->with('success', 'Đã cập nhật file mới. Bản cũ vẫn được giữ trong lịch sử cập nhật file.');
    }

    public function download(int $document, int $version)
    {
        $doc = DocumentScope::findOrFail($document);
        $ver = $this->findVersion($doc->id, $version);

        abort_unless($this->storage->exists($ver->file_path), Response::HTTP_NOT_FOUND, 'File không còn trên máy chủ lưu trữ.');
        AuditLogger::log('Tải xuống phiên bản cũ', 'documents', $doc->id, "{$doc->code} — lần {$ver->revision}: {$ver->original_filename}");

        return $this->storage->disk()->download($ver->file_path, $ver->original_filename);
    }

    public function restore(int $document, int $version)
    {
        $doc = DocumentScope::findOrFail($document);
        DocumentScope::authorizeManage($doc, 'documents.update');
        $ver = $this->findVersion($doc->id, $version);

        abort_unless($this->storage->exists($ver->file_path), Response::HTTP_NOT_FOUND, 'File của phiên bản này không còn trên máy chủ.');

        $this->documents->restoreVersion($doc, $ver, auth()->id());

        return redirect()->route('documents.show', $doc->id)->with('success', "Đã khôi phục file của lần {$ver->revision} làm bản hiện hành.");
    }

    private function findVersion(int $documentId, int $versionId): object
    {
        $ver = DB::table('document_versions')->where('document_id', $documentId)->where('id', $versionId)->first();
        abort_if($ver === null, Response::HTTP_NOT_FOUND);

        return $ver;
    }
}
