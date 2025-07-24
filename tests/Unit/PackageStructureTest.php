<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Composer\Autoload\ClassLoader;
use ReflectionClass;

class PackageStructureTest extends TestCase
{
    public function test_psr4_autoloading_works_for_controllers()
    {
        $this->assertTrue(class_exists('ScorecardScanner\Http\Controllers\Api\ScorecardScanController'));
    }

    public function test_psr4_autoloading_works_for_models()
    {
        $this->assertTrue(class_exists('ScorecardScanner\Models\ScorecardScan'));
        $this->assertTrue(class_exists('ScorecardScanner\Models\Course'));
        $this->assertTrue(class_exists('ScorecardScanner\Models\Round'));
        $this->assertTrue(class_exists('ScorecardScanner\Models\RoundScore'));
        $this->assertTrue(class_exists('ScorecardScanner\Models\UnverifiedCourse'));
        $this->assertTrue(class_exists('ScorecardScanner\Models\User'));
    }

    public function test_psr4_autoloading_works_for_services()
    {
        $this->assertTrue(class_exists('ScorecardScanner\Services\ScorecardProcessingService'));
        $this->assertTrue(class_exists('ScorecardScanner\Services\ImageProcessingService'));
        $this->assertTrue(class_exists('ScorecardScanner\Services\OcrService'));
    }

    public function test_psr4_autoloading_works_for_requests()
    {
        $this->assertTrue(class_exists('ScorecardScanner\Http\Requests\StoreScorecardScanRequest'));
    }

    public function test_psr4_autoloading_works_for_resources()
    {
        $this->assertTrue(class_exists('ScorecardScanner\Http\Resources\ScorecardScanResource'));
    }

    public function test_psr4_autoloading_works_for_policies()
    {
        $this->assertTrue(class_exists('ScorecardScanner\Policies\ScorecardScanPolicy'));
    }

    public function test_src_directory_structure_exists()
    {
        $basePath = dirname(__DIR__, 2);
        $this->assertDirectoryExists($basePath . '/src');
        $this->assertDirectoryExists($basePath . '/src/Http');
        $this->assertDirectoryExists($basePath . '/src/Http/Controllers');
        $this->assertDirectoryExists($basePath . '/src/Http/Controllers/Api');
        $this->assertDirectoryExists($basePath . '/src/Http/Requests');
        $this->assertDirectoryExists($basePath . '/src/Http/Resources');
        $this->assertDirectoryExists($basePath . '/src/Models');
        $this->assertDirectoryExists($basePath . '/src/Services');
        $this->assertDirectoryExists($basePath . '/src/Policies');
    }

    public function test_package_classes_are_in_correct_namespace()
    {
        $reflection = new ReflectionClass('ScorecardScanner\Models\ScorecardScan');
        $this->assertEquals('ScorecardScanner\Models', $reflection->getNamespaceName());
        
        $reflection = new ReflectionClass('ScorecardScanner\Services\ScorecardProcessingService');
        $this->assertEquals('ScorecardScanner\Services', $reflection->getNamespaceName());
        
        $reflection = new ReflectionClass('ScorecardScanner\Http\Controllers\Api\ScorecardScanController');
        $this->assertEquals('ScorecardScanner\Http\Controllers\Api', $reflection->getNamespaceName());
    }

    public function test_composer_autoload_includes_package_namespace()
    {
        $basePath = dirname(__DIR__, 2);
        $composerContent = json_decode(file_get_contents($basePath . '/composer.json'), true);
        
        $this->assertArrayHasKey('autoload', $composerContent);
        $this->assertArrayHasKey('psr-4', $composerContent['autoload']);
        $this->assertArrayHasKey('ScorecardScanner\\', $composerContent['autoload']['psr-4']);
        $this->assertEquals('src/', $composerContent['autoload']['psr-4']['ScorecardScanner\\']);
    }
}