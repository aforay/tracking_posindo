<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for External Integrations & Webhooks
|--------------------------------------------------------------------------
*/

Route::post('/sheets/inbound-webhook', [DashboardController::class, 'inboundWebhook'])->name('api.sheets.inbound_webhook');
