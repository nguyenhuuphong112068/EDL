<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dữ liệu gốc — các danh mục khai báo một lần, dùng lặp lại khi nhập thiết bị / tài liệu. */
    public function up(): void
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

        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique()->comment('Tiền tố mã tài liệu, vd: HDSD');
            $table->string('name');
            $table->string('color', 9)->default('#3b82f6');
            $table->string('keywords', 500)->nullable()
                ->comment('Từ khoá nhận diện từ tên file, cách nhau dấu phẩy');
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
            $table->string('contact')->nullable()->comment('Đầu mối liên hệ / nhà phân phối');
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('device_types');
    }
};
