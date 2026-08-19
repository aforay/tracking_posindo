<?php

use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackingController::class, 'index'])->name('tracking.index');
Route::post('/process', [TrackingController::class, 'process'])->name('tracking.process');
Route::get('/download/{filename}', [TrackingController::class, 'download'])->name('tracking.download');
Route::post('/export-colored-excel', [TrackingController::class, 'exportColoredExcel'])->name('tracking.export_colored');

// Inbound & Outbound Shipment Entry & Single Tracking Routes
Route::post('/shipments', [TrackingController::class, 'store'])->name('shipments.store');
Route::post('/shipments/{id}/track', [TrackingController::class, 'trackSingle'])->name('shipments.track');
Route::post('/shipments/{id}/color', [TrackingController::class, 'updateColor'])->name('shipments.color');

// NIPOS Simulation / Endpoint
Route::match(['get', 'post'], '/mock-nipos/lacak_item_banyakzaref.php', [TrackingController::class, 'mockNipos'])->name('mock.nipos');
