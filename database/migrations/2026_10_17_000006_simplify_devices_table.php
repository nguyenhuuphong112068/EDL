<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thu gọn thông tin thiết bị: chỉ còn Mã, Tên, Bộ phận lắp đặt, Tình trạng
     * (+ người tạo / ngày tạo do hệ thống ghi). Bỏ hai danh mục Dữ liệu gốc không còn dùng:
     * Loại thiết bị, Nhà sản xuất. Giữ nguyên dữ liệu thiết bị & tài liệu đang có.
     */
    private const DROPPED_COLUMNS = [
        'model', 'serial_number', 'asset_number', 'year_of_manufacture', 'commissioning_date',
        'responsible_person', 'specifications', 'description',
    ];

    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'device_type_id')) {
                $table->dropConstrainedForeignId('device_type_id');
            }
            if (Schema::hasColumn('devices', 'manufacturer_id')) {
                $table->dropConstrainedForeignId('manufacturer_id');
            }
        });

        Schema::table('devices', function (Blueprint $table) {
            $columns = array_values(array_filter(self::DROPPED_COLUMNS, fn ($c) => Schema::hasColumn('devices', $c)));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('device_types');
        Schema::dropIfExists('manufacturers');
    }

    public function down(): void
    {
        Schema::create('device_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('country', 100)->nullable();
            $table->string('contact')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('device_type_id')->nullable()->after('name')->constrained('device_types')->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->after('department_id')->constrained('manufacturers')->nullOnDelete();
            $table->string('model', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('asset_number', 100)->nullable();
            $table->unsignedSmallInteger('year_of_manufacture')->nullable();
            $table->date('commissioning_date')->nullable();
            $table->string('responsible_person')->nullable();
            $table->text('specifications')->nullable();
            $table->text('description')->nullable();
        });
    }
};
