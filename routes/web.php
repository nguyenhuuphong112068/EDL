<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\LoginHistoryController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Devices\DeviceController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\DocumentVersionController;
use App\Http\Controllers\Documents\SearchController;
use App\Http\Controllers\Documents\TrashController;
use App\Http\Controllers\Documents\UploadController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MasterData\DepartmentController;
use App\Http\Controllers\MasterData\DocumentTypeController;
use Illuminate\Support\Facades\Route;

// Đăng nhập — giới hạn tần suất để chống dò mật khẩu (ngoài khoá tài khoản theo số lần sai)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/password', [AuthController::class, 'changePassword'])->name('password.update');
    Route::post('/department/switch', [AuthController::class, 'switchDepartment'])->name('department.switch');

    Route::get('/', [HomeController::class, 'index'])->name('home');

    /*
     * THƯ VIỆN TÀI LIỆU
     * Khai các đường dẫn cố định (export, bulk, upload…) TRƯỚC {document} để không bị nuốt.
     */
    Route::get('search/quick', [SearchController::class, 'quick'])->middleware('throttle:120,1')->name('search.quick');
    Route::post('saved-searches', [SearchController::class, 'save'])->name('saved-searches.store');
    Route::delete('saved-searches/{savedSearch}', [SearchController::class, 'forget'])->whereNumber('savedSearch')->name('saved-searches.destroy');

    // Tải lên hàng loạt: chọn file → vùng tạm → khai thông tin → lưu tất cả
    Route::prefix('uploads')->name('uploads.')->group(function () {
        Route::get('/', [UploadController::class, 'create'])->name('create');
        Route::post('/temp', [UploadController::class, 'temp'])->name('temp');
        Route::delete('/temp/{token}', [UploadController::class, 'discard'])->where('token', '[A-Za-z0-9]{40}')->name('discard');
        Route::post('/', [UploadController::class, 'store'])->name('store');
    });

    Route::get('trash', [TrashController::class, 'index'])->name('trash.index');
    Route::post('trash/restore', [TrashController::class, 'restore'])->name('trash.restore');
    Route::delete('trash', [TrashController::class, 'destroy'])->name('trash.destroy');

    Route::get('documents/export', [DocumentController::class, 'export'])->name('documents.export');
    Route::post('documents/bulk', [DocumentController::class, 'bulk'])->name('documents.bulk');
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');

    Route::prefix('documents/{document}')->whereNumber('document')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'show'])->name('show');
        Route::get('/edit', [DocumentController::class, 'edit'])->name('edit');
        Route::put('/', [DocumentController::class, 'update'])->name('update');
        Route::delete('/', [DocumentController::class, 'destroy'])->name('destroy');
        Route::get('/preview', [DocumentController::class, 'preview'])->name('preview');
        Route::get('/download', [DocumentController::class, 'download'])->name('download');

        // Phiên bản file
        Route::post('/versions', [DocumentVersionController::class, 'store'])->name('versions.store');
        Route::get('/versions/{version}/download', [DocumentVersionController::class, 'download'])->whereNumber('version')->name('versions.download');
        Route::post('/versions/{version}/restore', [DocumentVersionController::class, 'restore'])->whereNumber('version')->name('versions.restore');
    });

    // THIẾT BỊ — khai đường dẫn cố định trước resource
    Route::get('devices/qr', [DeviceController::class, 'qr'])->name('devices.qr');
    Route::resource('devices', DeviceController::class)->except(['create', 'edit'])->whereNumber('device');
    Route::post('devices/{device}/restore', [DeviceController::class, 'restore'])->whereNumber('device')->name('devices.restore');

    // DỮ LIỆU GỐC
    Route::resource('departments', DepartmentController::class)->except('show')->parameters(['departments' => 'id'])->whereNumber('id');
    Route::resource('document-types', DocumentTypeController::class)->except('show')->parameters(['document-types' => 'id'])->whereNumber('id');

    // QUẢN TRỊ
    Route::prefix('admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy'])->whereNumber('user');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->whereNumber('user')->name('users.reset-password');
        Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->whereNumber('user')->name('users.unlock');
        Route::post('users/{user}/toggle', [UserController::class, 'toggle'])->whereNumber('user')->name('users.toggle');
        Route::get('users/{user}/permissions', [UserController::class, 'permissions'])->whereNumber('user')->name('users.permissions');
        Route::put('users/{user}/permissions', [UserController::class, 'updatePermissions'])->whereNumber('user')->name('users.permissions.update');

        Route::resource('roles', RoleController::class)->except('show')->whereNumber('role');
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('login-history', [LoginHistoryController::class, 'index'])->name('login-history.index');
    });

    // Lịch sử đăng nhập của chính mình — ai cũng xem được, không cần quyền
    Route::get('my/login-history', [LoginHistoryController::class, 'mine'])->name('login-history.mine');
});
