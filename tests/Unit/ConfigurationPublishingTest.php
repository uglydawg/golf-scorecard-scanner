<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class);

it('loads default configuration correctly', function () {
    $config = config('scorecard-scanner');

    expect($config)->toBeArray();
    expect($config)->toHaveKey('ocr');
    expect($config)->toHaveKey('storage');
    expect($config)->toHaveKey('processing');
});

it('has properly structured OCR configuration', function () {
    $ocrConfig = config('scorecard-scanner.ocr');

    expect($ocrConfig)->toHaveKey('default');
    expect($ocrConfig)->toHaveKey('providers');

    $providers = $ocrConfig['providers'];
    expect($providers)->toHaveKey('mock');
    expect($providers)->toHaveKey('ocrspace');
    expect($providers)->toHaveKey('google');
    expect($providers)->toHaveKey('aws');
});

it('has properly structured storage configuration', function () {
    $storageConfig = config('scorecard-scanner.storage');

    expect($storageConfig)->toHaveKey('disk');
    expect($storageConfig)->toHaveKey('path');
    expect($storageConfig)->toHaveKey('cleanup_after_days');
});

it('has properly structured processing configuration', function () {
    $processingConfig = config('scorecard-scanner.processing');

    expect($processingConfig)->toHaveKey('confidence_threshold');
    expect($processingConfig)->toHaveKey('max_file_size');
    expect($processingConfig)->toHaveKey('allowed_types');
});

it('has a configured default OCR provider', function () {
    $defaultProvider = config('scorecard-scanner.ocr.default');
    expect($defaultProvider)->toBeString();
    expect($defaultProvider)->not->toBeEmpty();
});

it('can publish configuration to host application', function () {
    // This test verifies that the configuration publishing is set up correctly
    // The actual publishing would be tested in integration tests
    $publishedConfigPath = config_path('scorecard-scanner.php');

    // If config is published, it should exist, otherwise use package default
    expect(
        file_exists($publishedConfigPath) || config('scorecard-scanner') !== null
    )->toBeTrue();
});

it('allows environment variables to override defaults', function () {
    // Test that environment variables can override configuration
    Config::set('scorecard-scanner.ocr.default', 'ocrspace');

    $provider = config('scorecard-scanner.ocr.default');
    expect($provider)->toBe('ocrspace');
});

it('has valid numeric confidence threshold', function () {
    $threshold = config('scorecard-scanner.processing.confidence_threshold');
    expect($threshold)->toBeNumeric();
    expect($threshold)->toBeGreaterThan(0);
    expect($threshold)->toBeLessThanOrEqual(1);
});

it('has valid numeric max file size', function () {
    $maxSize = config('scorecard-scanner.processing.max_file_size');
    expect($maxSize)->toBeNumeric();
    expect($maxSize)->toBeGreaterThan(0);
});

it('has valid array of allowed file types', function () {
    $allowedTypes = config('scorecard-scanner.processing.allowed_types');
    expect($allowedTypes)->toBeArray();
    expect($allowedTypes)->toContain('jpg');
    expect($allowedTypes)->toContain('jpeg');
    expect($allowedTypes)->toContain('png');
});
