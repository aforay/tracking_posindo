<?php

use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TrackingController::class, 'index'])->name('tracking.index');
Route::post('/process', [TrackingController::class, 'process'])->name('tracking.process');
Route::get('/download/{filename}', [TrackingController::class, 'download'])->name('tracking.download');

// NIPOS Simulation / Endpoint
Route::match(['get', 'post'], '/mock-nipos/lacak_item_banyakzaref.php', [TrackingController::class, 'mockNipos'])->name('mock.nipos');
