<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NiposSettingController;
use App\Http\Controllers\PostOfficeController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

// --- 1. Public & Authentication Routes ---
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

// NIPOS Simulation / Endpoint (Public Mock API for local testing)
Route::match(['get', 'post'], '/mock-nipos/lacak_item_banyakzaref.php', [TrackingController::class, 'mockNipos'])->name('mock.nipos');

// --- 2. Operational Routes (Admin & CS) ---
Route::middleware(['role:admin,cs'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/shipments', [DashboardController::class, 'index'])->name('shipments.index');

    // Status Update & Bulk Action Endpoints (Inertia & AJAX)
    Route::post('/shipments/update-status', [DashboardController::class, 'updateStatusBulk'])->name('shipments.update_status');
    Route::post('/shipments/bulk-action', [DashboardController::class, 'bulkAction'])->name('shipments.bulk_action');
    Route::post('/shipments/{id}/color', [DashboardController::class, 'updateColor'])->name('shipments.color');
    Route::post('/shipments/{id}/update-color', [DashboardController::class, 'updateColor'])->name('shipments.update_color');

    // Tracking Bot (CS & Admin can run tracking bot and view progress)
    Route::post('/shipments/{id}/track', [TrackingController::class, 'trackSingle'])->name('shipments.track_single');
    Route::post('/bot/start-tracking', [TrackingController::class, 'startBotTracking'])->name('bot.start_tracking');
    Route::get('/bot/progress', [TrackingController::class, 'progress'])->name('bot.progress');

    // Post Office (KC Pos Indonesia) Contacts Directory (Read-only for CS)
    Route::get('/post-offices', [PostOfficeController::class, 'index'])->name('post_offices.index');

    // Sync status & schedule monitors
    Route::get('/sync/progress', [DashboardController::class, 'syncProgress'])->name('sync.progress');
    Route::get('/sync/schedule-status', [DashboardController::class, 'syncScheduleStatus'])->name('sync.schedule_status');
});

// --- 3. Admin-Only Restricted Routes (Dangerous & Sensitive Operations) ---
Route::middleware(['role:admin'])->group(function () {
    // Import & Upload Operations
    Route::post('/process', [DashboardController::class, 'import'])->name('tracking.process');
    Route::post('/shipments/import', [DashboardController::class, 'import'])->name('dashboard.import');

    // Google Sheets & Two-Way Sync Configuration
    Route::post('/settings/google-sheets', [DashboardController::class, 'updateGoogleSheetsSetting'])->name('settings.google_sheets');
    Route::post('/shipments/sync-google-sheets', [DashboardController::class, 'syncGoogleSheets'])->name('shipments.sync_google_sheets');
    Route::post('/shipments/sync-filter', [DashboardController::class, 'syncFilter'])->name('shipments.sync_filter');
    Route::post('/sync/discover', [DashboardController::class, 'syncDiscover'])->name('sync.discover');
    Route::post('/sync/sheet', [DashboardController::class, 'syncSingleSheet'])->name('sync.sheet');
    Route::post('/shipments/push-updates', [DashboardController::class, 'pushUpdatesToSheets'])->name('shipments.push_updates');

    // NIPOS Session Cookie Settings & Test Connection
    Route::get('/settings/nipos-cookie', [NiposSettingController::class, 'index'])->name('settings.nipos_cookie.index');
    Route::get('/settings/nipos-cookie/status', [NiposSettingController::class, 'status'])->name('settings.nipos_cookie.status');
    Route::post('/settings/nipos-cookie', [NiposSettingController::class, 'store'])->name('settings.nipos_cookie.store');
    Route::post('/settings/nipos-cookie/test', [NiposSettingController::class, 'testConnection'])->name('settings.nipos_cookie.test');

    // Sensitive Data Export & Downloads
    Route::post('/export-colored-excel', [TrackingController::class, 'exportColoredExcel'])->name('tracking.export_colored');
    Route::get('/export-aliqa', [TrackingController::class, 'exportAliqa'])->name('tracking.export_aliqa');
    Route::get('/download/{filename}', [TrackingController::class, 'download'])->name('tracking.download');

    // Master Post Offices CRUD & Bulk Import
    Route::post('/post-offices', [PostOfficeController::class, 'store'])->name('post_offices.store');
    Route::put('/post-offices/{id}', [PostOfficeController::class, 'update'])->name('post_offices.update');
    Route::delete('/post-offices/{id}', [PostOfficeController::class, 'destroy'])->name('post_offices.destroy');
    Route::post('/post-offices/import', [PostOfficeController::class, 'bulkImport'])->name('post_offices.import');
});