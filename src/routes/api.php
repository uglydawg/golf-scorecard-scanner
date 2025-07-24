<?php

declare(strict_types=1);

use ScorecardScanner\Http\Controllers\Api\ScorecardScanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('scorecard-scans', ScorecardScanController::class);
});