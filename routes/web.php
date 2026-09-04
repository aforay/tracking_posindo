<?php

use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/shipments', [DashboardController::class, 'index'])->name('shipments.index');

// Batch Process & Bot Tracking Endpoints
Route::post('/process', [DashboardController::class, 'import'])->name('tracking.process');
Route::post('/shipments/import', [DashboardController::class, 'import'])->name('dashboard.import');
Route::post('/settings/google-sheets', [DashboardController::class, 'updateGoogleSheetsSetting'])->name('settings.google_sheets');
Route::post('/shipments/sync-google-sheets', [DashboardController::class, 'syncGoogleSheets'])->name('shipments.sync_google_sheets');
Route::post('/shipments/sync-filter', [DashboardController::class, 'syncFilter'])->name('shipments.sync_filter');
Route::get('/sync/progress', [DashboardController::class, 'syncProgress'])->name('sync.progress');
Route::get('/sync/schedule-status', [DashboardController::class, 'syncScheduleStatus'])->name('sync.schedule_status');
Route::post('/sync/discover', [DashboardController::class, 'syncDiscover'])->name('sync.discover');
Route::post('/sync/sheet', [DashboardController::class, 'syncSingleSheet'])->name('sync.sheet');
Route::post('/shipments/push-updates', [DashboardController::class, 'pushUpdatesToSheets'])->name('shipments.push_updates');
Route::post('/bot/start-tracking', [TrackingController::class, 'startBotTracking'])->name('bot.start_tracking');
Route::get('/bot/progress', [TrackingController::class, 'progress'])->name('bot.progress');

// Export & Download Routes
Route::post('/export-colored-excel', [TrackingController::class, 'exportColoredExcel'])->name('tracking.export_colored');
Route::get('/export-aliqa', [TrackingController::class, 'exportAliqa'])->name('tracking.export_aliqa');
Route::get('/download/{filename}', [TrackingController::class, 'download'])->name('tracking.download');

// Status Update & Bulk Action Endpoints (Inertia & AJAX)
Route::post('/shipments/update-status', [DashboardController::class, 'updateStatusBulk'])->name('shipments.update_status');
Route::post('/shipments/bulk-action', [DashboardController::class, 'bulkAction'])->name('shipments.bulk_action');
Route::post('/shipments/{id}/color', [DashboardController::class, 'updateColor'])->name('shipments.color');
Route::post('/shipments/{id}/update-color', [DashboardController::class, 'updateColor'])->name('shipments.update_color');
Route::post('/shipments/{id}/track', [TrackingController::class, 'trackSingle'])->name('shipments.track_single');

use App\Http\Controllers\PostOfficeController;

// Post Office (KC Pos Indonesia) Contacts Management
Route::get('/post-offices', [PostOfficeController::class, 'index'])->name('post_offices.index');
Route::post('/post-offices', [PostOfficeController::class, 'store'])->name('post_offices.store');
Route::put('/post-offices/{id}', [PostOfficeController::class, 'update'])->name('post_offices.update');
Route::delete('/post-offices/{id}', [PostOfficeController::class, 'destroy'])->name('post_offices.destroy');
Route::post('/post-offices/import', [PostOfficeController::class, 'bulkImport'])->name('post_offices.import');

// NIPOS Simulation / Endpoint
Route::match(['get', 'post'], '/mock-nipos/lacak_item_banyakzaref.php', [TrackingController::class, 'mockNipos'])->name('mock.nipos');
