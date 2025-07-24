<?php

declare(strict_types=1);

use ScorecardScanner\Http\Controllers\Api\ScorecardScanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('scorecard-scans', ScorecardScanController::class);
});