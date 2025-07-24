<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ScorecardScanner\Http\Controllers\Api\ScorecardScanController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('scorecard-scans', ScorecardScanController::class);
});
