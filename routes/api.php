<?php

use App\Http\Controllers\Api\PrintController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Printer registry
    Route::get('/printers',               [PrintController::class, 'printers']);
    Route::get('/printers/{printer}/ping', [PrintController::class, 'ping']);

    // Print job queue
    Route::get('/print-jobs',                    [PrintController::class, 'jobs']);
    Route::post('/print-jobs/{job}/cancel',      [PrintController::class, 'cancelJob']);

    // Audit history per item
    Route::get('/print-history/{variantId}',     [PrintController::class, 'history']);
});
