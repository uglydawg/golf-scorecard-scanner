<?php

declare(strict_types=1);

it('can autoload controllers via PSR-4', function () {
    expect(class_exists('ScorecardScanner\Http\Controllers\Api\ScorecardScanController'))->toBeTrue();
});

it('can autoload models via PSR-4', function () {
    expect(class_exists('ScorecardScanner\Models\ScorecardScan'))->toBeTrue();
    expect(class_exists('ScorecardScanner\Models\Course'))->toBeTrue();
    expect(class_exists('ScorecardScanner\Models\Round'))->toBeTrue();
    expect(class_exists('ScorecardScanner\Models\RoundScore'))->toBeTrue();
    expect(class_exists('ScorecardScanner\Models\UnverifiedCourse'))->toBeTrue();
    expect(class_exists('ScorecardScanner\Models\User'))->toBeTrue();
});

it('can autoload services via PSR-4', function () {
    expect(class_exists('ScorecardScanner\Services\ScorecardProcessingService'))->toBeTrue();
    expect(class_exists('ScorecardScanner\Services\ImageProcessingService'))->toBeTrue();
    expect(class_exists('ScorecardScanner\Services\OcrService'))->toBeTrue();
});

it('can autoload requests via PSR-4', function () {
    expect(class_exists('ScorecardScanner\Http\Requests\StoreScorecardScanRequest'))->toBeTrue();
});

it('can autoload resources via PSR-4', function () {
    expect(class_exists('ScorecardScanner\Http\Resources\ScorecardScanResource'))->toBeTrue();
});

it('can autoload policies via PSR-4', function () {
    expect(class_exists('ScorecardScanner\Policies\ScorecardScanPolicy'))->toBeTrue();
});

it('has proper source directory structure', function () {
    $basePath = dirname(__DIR__, 2);
    
    expect($basePath.'/src')->toBeDirectory();
    expect($basePath.'/src/Http')->toBeDirectory();
    expect($basePath.'/src/Http/Controllers')->toBeDirectory();
    expect($basePath.'/src/Http/Controllers/Api')->toBeDirectory();
    expect($basePath.'/src/Http/Requests')->toBeDirectory();
    expect($basePath.'/src/Http/Resources')->toBeDirectory();
    expect($basePath.'/src/Models')->toBeDirectory();
    expect($basePath.'/src/Services')->toBeDirectory();
    expect($basePath.'/src/Policies')->toBeDirectory();
});

it('places package classes in correct namespaces', function () {
    $reflection = new ReflectionClass('ScorecardScanner\Models\ScorecardScan');
    expect($reflection->getNamespaceName())->toBe('ScorecardScanner\Models');

    $reflection = new ReflectionClass('ScorecardScanner\Services\ScorecardProcessingService');
    expect($reflection->getNamespaceName())->toBe('ScorecardScanner\Services');

    $reflection = new ReflectionClass('ScorecardScanner\Http\Controllers\Api\ScorecardScanController');
    expect($reflection->getNamespaceName())->toBe('ScorecardScanner\Http\Controllers\Api');
});

it('includes package namespace in composer autoload', function () {
    $basePath = dirname(__DIR__, 2);
    $composerContent = json_decode(file_get_contents($basePath.'/composer.json'), true);

    expect($composerContent)->toHaveKey('autoload');
    expect($composerContent['autoload'])->toHaveKey('psr-4');
    expect($composerContent['autoload']['psr-4'])->toHaveKey('ScorecardScanner\\');
    expect($composerContent['autoload']['psr-4']['ScorecardScanner\\'])->toBe('src/');
});
