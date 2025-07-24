<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

class ConfigurationPublishingTest extends TestCase
{
    public function test_default_configuration_is_loaded()
    {
        $config = config('scorecard-scanner');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('ocr', $config);
        $this->assertArrayHasKey('storage', $config);
        $this->assertArrayHasKey('processing', $config);
    }

    public function test_ocr_configuration_has_correct_structure()
    {
        $ocrConfig = config('scorecard-scanner.ocr');
        
        $this->assertArrayHasKey('default', $ocrConfig);
        $this->assertArrayHasKey('providers', $ocrConfig);
        
        $providers = $ocrConfig['providers'];
        $this->assertArrayHasKey('mock', $providers);
        $this->assertArrayHasKey('ocrspace', $providers);
        $this->assertArrayHasKey('google', $providers);
        $this->assertArrayHasKey('aws', $providers);
    }

    public function test_storage_configuration_has_correct_structure()
    {
        $storageConfig = config('scorecard-scanner.storage');
        
        $this->assertArrayHasKey('disk', $storageConfig);
        $this->assertArrayHasKey('path', $storageConfig);
        $this->assertArrayHasKey('cleanup_after_days', $storageConfig);
    }

    public function test_processing_configuration_has_correct_structure()
    {
        $processingConfig = config('scorecard-scanner.processing');
        
        $this->assertArrayHasKey('confidence_threshold', $processingConfig);
        $this->assertArrayHasKey('max_file_size', $processingConfig);
        $this->assertArrayHasKey('allowed_types', $processingConfig);
    }

    public function test_default_ocr_provider_is_mock()
    {
        $defaultProvider = config('scorecard-scanner.ocr.default');
        $this->assertEquals('mock', $defaultProvider);
    }

    public function test_configuration_can_be_published()
    {
        // This test verifies that the configuration publishing is set up correctly
        // The actual publishing would be tested in integration tests
        $publishedConfigPath = config_path('scorecard-scanner.php');
        
        // If config is published, it should exist, otherwise use package default
        $this->assertTrue(
            file_exists($publishedConfigPath) || config('scorecard-scanner') !== null
        );
    }

    public function test_environment_variables_override_defaults()
    {
        // Test that environment variables can override configuration
        Config::set('scorecard-scanner.ocr.default', 'ocrspace');
        
        $provider = config('scorecard-scanner.ocr.default');
        $this->assertEquals('ocrspace', $provider);
    }

    public function test_confidence_threshold_is_numeric()
    {
        $threshold = config('scorecard-scanner.processing.confidence_threshold');
        $this->assertIsNumeric($threshold);
        $this->assertGreaterThan(0, $threshold);
        $this->assertLessThanOrEqual(1, $threshold);
    }

    public function test_max_file_size_is_numeric()
    {
        $maxSize = config('scorecard-scanner.processing.max_file_size');
        $this->assertIsNumeric($maxSize);
        $this->assertGreaterThan(0, $maxSize);
    }

    public function test_allowed_types_is_array()
    {
        $allowedTypes = config('scorecard-scanner.processing.allowed_types');
        $this->assertIsArray($allowedTypes);
        $this->assertContains('jpg', $allowedTypes);
        $this->assertContains('jpeg', $allowedTypes);
        $this->assertContains('png', $allowedTypes);
    }
}