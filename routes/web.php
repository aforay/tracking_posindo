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
Route::post('/bot/start-tracking', [TrackingController::class, 'startBotTracking'])->name('bot.start_tracking');
Route::get('/bot/progress', [TrackingController::class, 'progress'])->name('bot.progress');

// Export & Download Routes
Route::post('/export-colored-excel', [TrackingController::class, 'exportColoredExcel'])->name('tracking.export_colored');
Route::get('/export-aliqa', [TrackingController::class, 'exportAliqa'])->name('tracking.export_aliqa');
Route::get('/download/{filename}', [TrackingController::class, 'download'])->name('tracking.download');

// Status Update & Bulk Action Endpoints (Inertia & AJAX)
Route::post('/shipments', [TrackingController::class, 'store'])->name('shipments.store');
Route::post('/shipments/update-status', [TrackingController::class, 'updateStatusBulk'])->name('shipments.update_status');
Route::post('/shipments/bulk-action', [TrackingController::class, 'bulkAction'])->name('shipments.bulk_action');
Route::post('/shipments/{id}/track', [TrackingController::class, 'trackSingle'])->name('shipments.track');
Route::post('/shipments/{id}/color', [TrackingController::class, 'updateColor'])->name('shipments.color');
Route::post('/shipments/{id}/update-color', [TrackingController::class, 'updateColor'])->name('shipments.update_color');

// NIPOS Simulation / Endpoint
Route::match(['get', 'post'], '/mock-nipos/lacak_item_banyakzaref.php', [TrackingController::class, 'mockNipos'])->name('mock.nipos');
