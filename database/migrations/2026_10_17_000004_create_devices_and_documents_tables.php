<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thiết bị (đối tượng quản lý) và tài liệu của thiết bị.
     *
     * Mỗi thiết bị là một "folder" tài liệu — giống REMS mỗi nguyên liệu của NSX là một folder.
     * Mỗi tài liệu giữ một file hiện hành; các lần thay file trước đó nằm ở document_versions.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Mã thiết bị');
            $table->string('name');
            $table->foreignId('device_type_id')->nullable()->constrained('device_types')->nullOnDelete();
            $table->foreignId('department_id')->constrained('departments')->comment('Bộ phận quản lý');
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('asset_number', 100)->nullable()->comment('Mã tài sản kế toán');
            $table->unsignedSmallInteger('year_of_manufacture')->nullable();
            $table->date('commissioning_date')->nullable()->comment('Ngày đưa vào sử dụng');
            $table->string('status', 20)->default('active');
            $table->string('responsible_person')->nullable()->comment('Người phụ trách');
            $table->text('specifications')->nullable()->comment('Thông số kỹ thuật');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique()->comment('Mã tài liệu, sinh tự động');
            $table->string('title', 500);
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('version', 30)->nullable()->comment('Số hiệu phiên bản / lần ban hành');
            $table->unsignedInteger('revision_count')->default(1)->comment('Số lần thay file');
            $table->date('issue_date')->nullable()->comment('Ngày ban hành');
            $table->date('effective_date')->nullable()->comment('Ngày hiệu lực');
            $table->string('status', 20)->default('active');
            $table->string('confidentiality', 20)->default('internal');
            $table->string('language', 10)->nullable();

            // File hiện hành
            $table->string('file_path', 500);
            $table->string('original_filename', 500);
            $table->string('extension', 20)->index();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('checksum', 64)->nullable()->index()->comment('SHA-256 — phát hiện trùng file');

            // Nội dung trích xuất để tìm kiếm toàn văn
            $table->longText('content_text')->nullable();
            $table->boolean('is_indexed')->default(false);

            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('download_count')->default(0);

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['device_id', 'document_type_id']);
            $table->index('title');
        });

        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->comment('Thứ tự lần thay file');
            $table->string('version', 30)->nullable();
            $table->string('file_path', 500);
            $table->string('original_filename', 500);
            $table->string('extension', 20);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('checksum', 64)->nullable();
            $table->string('change_note', 1000)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        // Lượt xem / tải của từng người — dùng cho "Xem gần đây" và thống kê
        Schema::create('document_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20)->comment('view | download');
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('query');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('document_activities');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('devices');
    }
};
