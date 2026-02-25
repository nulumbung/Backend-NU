<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LiveStreamController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\AdvertisementController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\AgendaController;
use App\Http\Controllers\Api\BanomController;
use App\Http\Controllers\Api\MultimediaController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\BackupController;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']); // Public subscribe
Route::get('/settings/public', [SettingController::class, 'publicSettings']); // Public settings
Route::get('ads/active', [AdvertisementController::class, 'active']);
Route::post('ads/{advertisement}/impression', [AdvertisementController::class, 'impression']);
Route::post('ads/{advertisement}/click', [AdvertisementController::class, 'click']);
Route::get('live-streams', [LiveStreamController::class, 'index']);
Route::get('live-streams/active', [LiveStreamController::class, 'getActive']);
Route::get('posts', [PostController::class, 'index']);
Route::get('posts/latest', [PostController::class, 'latest']);
Route::get('posts/category/{slug}', [PostController::class, 'byCategory']);
Route::get('posts/{id}', [PostController::class, 'show']);
Route::get('categories', [CategoryController::class, 'index']); // Public categories
Route::get('categories/{id}', [CategoryController::class, 'show']);
Route::get('banoms', [BanomController::class, 'index']); // Public banoms
Route::get('banoms/{id}', [BanomController::class, 'show']); // Public banom detail
Route::get('multimedia', [MultimediaController::class, 'index']); // Public multimedia
Route::get('multimedia/{id}', [MultimediaController::class, 'show']);
Route::get('agendas', [AgendaController::class, 'index']); // Public agendas
Route::get('agendas/{id}', [AgendaController::class, 'show']); // Public agenda detail
Route::get('histories', [HistoryController::class, 'index']); // Public histories
Route::get('histories/{history}', [HistoryController::class, 'show']); // Public history detail
Route::get('comments/{type}/{target}', [CommentController::class, 'index']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('comments/{type}/{target}', [CommentController::class, 'store']);
});

// Admin Panel Protected Routes
Route::middleware(['auth:sanctum', 'role:superadmin,admin,editor,redaksi'])->group(function () {
    // Admin Dashboard Stats
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Resource Routes (Admin Panel)
    Route::apiResource('posts', PostController::class)->except(['index', 'show']); // Index & show are public
    Route::get('/admin/posts', [PostController::class, 'adminIndex']); // Separate admin listing
    
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
    Route::apiResource('agendas', AgendaController::class)->except(['index', 'show']);
    Route::apiResource('banoms', BanomController::class)->except(['index', 'show']);
    Route::apiResource('multimedia', MultimediaController::class)->except(['index', 'show']); // Index & show are public
    Route::apiResource('histories', HistoryController::class)->except(['index', 'show']);
    Route::get('agendas/organizers', [AgendaController::class, 'organizers']);
    Route::get('agendas/export', [AgendaController::class, 'exportData']);
    Route::apiResource('ads', AdvertisementController::class);
    
    Route::apiResource('newsletters', NewsletterController::class)->except(['store']); // Store is handled by public subscribe or explicit admin add

    Route::get('settings', [SettingController::class, 'index']);
    Route::put('settings', [SettingController::class, 'updateBatch']);

    Route::post('live-streams/{live_stream}/refresh', [LiveStreamController::class, 'refresh']);
    Route::apiResource('live-streams', LiveStreamController::class)->except(['index']);

    // File Upload
    Route::post('/upload', [FileUploadController::class, 'upload']);
});

Route::middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
    Route::apiResource('users', UserController::class);

    // Role & Permission Management
    Route::get('permissions', [RoleController::class, 'permissions']);
    Route::apiResource('roles', RoleController::class);

    // Backup & Restore
    Route::get('backup/download', [BackupController::class, 'download']);
    Route::post('backup/restore', [BackupController::class, 'restore']);
    Route::post('backup/preview', [BackupController::class, 'preview']);
});
