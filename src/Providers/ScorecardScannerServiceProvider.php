<?php

declare(strict_types=1);

namespace ScorecardScanner\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use ScorecardScanner\Console\Commands\PublishMigrationsCommand;
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Policies\ScorecardScanPolicy;
use ScorecardScanner\Services\ImageProcessingService;
use ScorecardScanner\Services\OcrService;
use ScorecardScanner\Services\ScorecardProcessingService;

class ScorecardScannerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/scorecard-scanner.php',
            'scorecard-scanner'
        );

        $this->publishes([
            __DIR__.'/../../config/scorecard-scanner.php' => config_path('scorecard-scanner.php'),
        ], 'scorecard-scanner-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'scorecard-scanner-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishMigrationsCommand::class,
            ]);
        }

        $this->registerRoutes();
        $this->registerPolicies();
    }

    /**
     * Register the package services.
     */
    protected function registerServices(): void
    {
        $this->app->singleton(OcrService::class, function ($app) {
            return new OcrService;
        });

        $this->app->singleton(ImageProcessingService::class, function ($app) {
            return new ImageProcessingService;
        });

        $this->app->singleton(ScorecardProcessingService::class, function ($app) {
            return new ScorecardProcessingService(
                $app->make(ImageProcessingService::class),
                $app->make(OcrService::class)
            );
        });
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    /**
     * Register the package policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(ScorecardScan::class, ScorecardScanPolicy::class);
    }
}
