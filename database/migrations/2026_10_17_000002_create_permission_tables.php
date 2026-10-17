<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bộ bảng phân quyền (theo WMS):
     *  - permission_groups : nhóm quyền theo menu (Thiết bị, Tài liệu, Dữ liệu gốc…)
     *  - permissions       : từng quyền chi tiết
     *  - roles             : vai trò (nhóm người dùng)
     *  - role_permission   : quyền của vai trò
     *  - user_role         : vai trò gán cho user THEO BỘ PHẬN (department_id NULL = mọi bộ phận)
     *  - user_permission   : quyền cấp riêng / chặn riêng cho 1 user, ghi đè kết quả từ vai trò
     *  - audit_logs        : nhật ký hệ thống, chỉ ghi thêm
     */
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_group_id')->constrained('permission_groups')->cascadeOnDelete();
            $table->string('slug', 80)->unique()->comment('vd: documents.upload');
            $table->string('display_name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 60)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('Vai trò hệ thống — không xoá được');
            $table->timestamps();
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->comment('NULL = áp dụng mọi bộ phận')
                ->constrained('departments')->cascadeOnDelete();
            $table->unique(['user_id', 'role_id', 'department_id']);
        });

        Schema::create('user_permission', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_denied')->default(false)->comment('true = chặn quyền dù vai trò có');
            $table->primary(['user_id', 'permission_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('userName', 60)->nullable()->index();
            $table->string('action', 60)->index();
            $table->string('module', 60)->nullable()->index();
            $table->string('record_id', 60)->nullable();
            $table->string('description', 500)->nullable();
            $table->longText('old_values')->nullable();
            $table->longText('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_permission');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permission_groups');
    }
};
