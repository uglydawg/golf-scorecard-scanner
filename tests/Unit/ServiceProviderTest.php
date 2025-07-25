<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Policies\ScorecardScanPolicy;
use ScorecardScanner\Providers\ScorecardScannerServiceProvider;
use ScorecardScanner\Services\ImageProcessingService;
use ScorecardScanner\Services\OcrService;
use ScorecardScanner\Services\ScorecardProcessingService;

uses(Tests\TestCase::class);

it('can instantiate the service provider', function () {
    $provider = new ScorecardScannerServiceProvider($this->app);
    expect($provider)->toBeInstanceOf(ScorecardScannerServiceProvider::class);
});

it('registers core services in the container', function () {
    expect($this->app->bound(ScorecardProcessingService::class))->toBeTrue();
    expect($this->app->bound(ImageProcessingService::class))->toBeTrue();
    expect($this->app->bound(OcrService::class))->toBeTrue();
});

it('can resolve services from the container', function () {
    $processingService = $this->app->make(ScorecardProcessingService::class);
    expect($processingService)->toBeInstanceOf(ScorecardProcessingService::class);

    $imageService = $this->app->make(ImageProcessingService::class);
    expect($imageService)->toBeInstanceOf(ImageProcessingService::class);

    $ocrService = $this->app->make(OcrService::class);
    expect($ocrService)->toBeInstanceOf(OcrService::class);
});

it('registers authorization policies', function () {
    $policy = Gate::getPolicyFor(ScorecardScan::class);
    expect($policy)->toBeInstanceOf(ScorecardScanPolicy::class);
});

it('registers API routes', function () {
    $routes = Route::getRoutes();

    expect($routes->hasNamedRoute('scorecard-scans.index'))->toBeTrue();
    expect($routes->hasNamedRoute('scorecard-scans.store'))->toBeTrue();
    expect($routes->hasNamedRoute('scorecard-scans.show'))->toBeTrue();
    expect($routes->hasNamedRoute('scorecard-scans.destroy'))->toBeTrue();
});

it('configures routes with correct controller', function () {
    $route = Route::getRoutes()->getByName('scorecard-scans.store');
    $action = $route->getAction();

    expect($action['controller'])->toContain('ScorecardScanController');
});

it('applies authentication middleware to routes', function () {
    $route = Route::getRoutes()->getByName('scorecard-scans.store');
    $middleware = $route->middleware();

    expect($middleware)->toContain('auth:sanctum');
});
