<?php

use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\WorkerApiController;
use App\Http\Controllers\Site\WizardController;
use App\Http\Middleware\WorkerToken;
use Illuminate\Support\Facades\Route;

// Bez relací a CSRF (prefix /api).
Route::get('/slug-check', [WizardController::class, 'slugCheck'])->middleware('throttle:60,1');
Route::post('/quote', [WizardController::class, 'quote'])->middleware('throttle:120,1');
Route::post('/stripe/webhook', StripeWebhookController::class);

Route::middleware(WorkerToken::class)->prefix('worker')->group(function () {
    Route::get('/jobs', [WorkerApiController::class, 'jobs']);
    Route::post('/jobs/{job}/done', [WorkerApiController::class, 'done']);
    Route::post('/jobs/{job}/failed', [WorkerApiController::class, 'failed']);
});
