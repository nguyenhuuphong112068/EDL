<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bộ phận quản lý + tài khoản người dùng.
     *
     * Bảo mật tài khoản theo WMS: khoá tạm khi nhập sai nhiều lần, mật khẩu có hạn dùng,
     * buộc đổi mật khẩu ở lần đăng nhập đầu, lịch sử mật khẩu để chống dùng lại.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('Mã bộ phận, vd: CD, QA');
            $table->string('name')->comment('Tên bộ phận');
            $table->string('manager_name')->nullable()->comment('Trưởng bộ phận');
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Họ tên');
            $table->string('userName', 60)->unique()->comment('Tên đăng nhập');
            $table->string('email')->nullable();
            $table->string('position')->nullable()->comment('Chức vụ');
            $table->foreignId('department_id')->nullable()->comment('Bộ phận chính')
                ->constrained('departments')->nullOnDelete();
            $table->string('password');
            $table->boolean('is_active')->default(true);

            // Bảo mật đăng nhập
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable()->comment('Khoá tạm tới thời điểm này');
            $table->date('password_expires_at')->nullable()->comment('Ngày mật khẩu hết hạn');
            $table->boolean('must_change_password')->default(true)->comment('Buộc đổi mật khẩu lần đăng nhập tới');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        // Bảng chỉ ghi thêm — dùng để chặn đặt lại N mật khẩu gần nhất
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password_hash');
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('users');
        Schema::dropIfExists('departments');
    }
};
