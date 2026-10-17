<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Tài liệu không còn: phiên bản (version), ngày ban hành (issue_date), ngày hiệu lực (effective_date).
 * Lịch sử thay file (document_versions.revision) vẫn giữ — chỉ bỏ cột số hiệu phiên bản.
 * Giá trị cũ được sao lưu ra storage/app/private/backups/ trước khi xoá cột.
 */
return new class extends Migration
{
    public function up(): void
    {
        $docCols = array_values(array_filter(['version', 'issue_date', 'effective_date'], fn ($c) => Schema::hasColumn('documents', $c)));
        $verCols = Schema::hasColumn('document_versions', 'version') ? ['version'] : [];

        if ($docCols || $verCols) {
            $backup = [
                'documents'         => $docCols ? DB::table('documents')->select(array_merge(['id', 'code'], $docCols))->get() : [],
                'document_versions' => $verCols ? DB::table('document_versions')->whereNotNull('version')->select(['id', 'document_id', 'revision', 'version'])->get() : [],
            ];
            Storage::disk('local')->put('backups/documents_version_dates_' . now()->format('Ymd_His') . '.json',
                json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ($docCols) {
            Schema::table('documents', fn (Blueprint $table) => $table->dropColumn($docCols));
        }
        if ($verCols) {
            Schema::table('document_versions', fn (Blueprint $table) => $table->dropColumn('version'));
        }
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('version', 30)->nullable()->after('description')->comment('Số hiệu phiên bản / lần ban hành');
            $table->date('issue_date')->nullable()->after('revision_count')->comment('Ngày ban hành');
            $table->date('effective_date')->nullable()->after('issue_date')->comment('Ngày hiệu lực');
        });
        Schema::table('document_versions', function (Blueprint $table) {
            $table->string('version', 30)->nullable()->after('revision');
        });
    }
};
