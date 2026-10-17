<?php

use App\Services\Documents\DocumentService;
use App\Services\Documents\DocumentStorage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Dọn file tạm của các lượt tải lên không được lưu
Artisan::command('edl:clean-temp', function (DocumentStorage $storage) {
    $this->info('Đã xoá ' . $storage->cleanExpiredTemp() . ' file tạm quá hạn.');
})->purpose('Dọn file tạm tải lên quá hạn');

// Đánh chỉ mục toàn văn lại (sau khi nâng cấp bộ trích xuất, hoặc tài liệu chưa có chỉ mục)
Artisan::command('edl:reindex {--all : Đánh lại cả tài liệu đã có chỉ mục}', function (DocumentService $service) {
    $ids = DB::table('documents')->whereNull('deleted_at')
        ->when(! $this->option('all'), fn ($q) => $q->where('is_indexed', false))
        ->pluck('id');

    $bar = $this->output->createProgressBar($ids->count());
    foreach ($ids as $id) {
        $service->index($id);
        $bar->advance();
    }
    $bar->finish();
    $this->newLine();
    $this->info('Đã đánh chỉ mục ' . $ids->count() . ' tài liệu.');
})->purpose('Đánh chỉ mục tìm kiếm toàn văn cho tài liệu');

Schedule::command('edl:clean-temp')->dailyAt('02:00');
