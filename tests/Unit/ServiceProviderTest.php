<?php

declare(strict_types=1);

namespace Tests\Unit;

use ScorecardScanner\Providers\ScorecardScannerServiceProvider;
use ScorecardScanner\Services\ScorecardProcessingService;
use ScorecardScanner\Services\ImageProcessingService;
use ScorecardScanner\Services\OcrService;
use ScorecardScanner\Policies\ScorecardScanPolicy;
use ScorecardScanner\Models\ScorecardScan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_can_be_instantiated()
    {
        $provider = new ScorecardScannerServiceProvider($this->app);
        $this->assertInstanceOf(ScorecardScannerServiceProvider::class, $provider);
    }

    public function test_service_provider_registers_services()
    {
        $this->assertTrue($this->app->bound(ScorecardProcessingService::class));
        $this->assertTrue($this->app->bound(ImageProcessingService::class));
        $this->assertTrue($this->app->bound(OcrService::class));
    }

    public function test_service_provider_can_resolve_services()
    {
        $processingService = $this->app->make(ScorecardProcessingService::class);
        $this->assertInstanceOf(ScorecardProcessingService::class, $processingService);

        $imageService = $this->app->make(ImageProcessingService::class);
        $this->assertInstanceOf(ImageProcessingService::class, $imageService);

        $ocrService = $this->app->make(OcrService::class);
        $this->assertInstanceOf(OcrService::class, $ocrService);
    }

    public function test_service_provider_registers_policies()
    {
        $policy = Gate::getPolicyFor(ScorecardScan::class);
        $this->assertInstanceOf(ScorecardScanPolicy::class, $policy);
    }

    public function test_service_provider_registers_routes()
    {
        $routes = Route::getRoutes();
        
        // Check that scorecard-scans routes are registered
        $this->assertTrue($routes->hasNamedRoute('scorecard-scans.index'));
        $this->assertTrue($routes->hasNamedRoute('scorecard-scans.store'));
        $this->assertTrue($routes->hasNamedRoute('scorecard-scans.show'));
        $this->assertTrue($routes->hasNamedRoute('scorecard-scans.destroy'));
    }

    public function test_service_provider_routes_use_correct_controller()
    {
        $route = Route::getRoutes()->getByName('scorecard-scans.store');
        $action = $route->getAction();
        
        $this->assertStringContainsString('ScorecardScanController', $action['controller']);
    }

    public function test_service_provider_routes_have_correct_middleware()
    {
        $route = Route::getRoutes()->getByName('scorecard-scans.store');
        $middleware = $route->middleware();
        
        $this->assertContains('auth:sanctum', $middleware);
    }
}