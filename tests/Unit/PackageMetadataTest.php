<?php

declare(strict_types=1);

use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

$composerData = null;

beforeEach(function () use (&$composerData) {
    $composerPath = base_path('composer.json');
    expect(File::exists($composerPath))->toBeTrue('composer.json file must exist');

    $composerData = json_decode(File::get($composerPath), true);
    expect($composerData)->toBeArray('composer.json must contain valid JSON');

    $this->composerData = $composerData;
});

it('has all required package metadata fields', function () {
    $requiredFields = [
        'name',
        'description',
        'type',
        'license',
        'authors',
        'keywords',
        'homepage',
        'require',
    ];

    foreach ($requiredFields as $field) {
        expect($this->composerData)->toHaveKey($field);
    }
});

it('has valid vendor/package name format', function () {
    $packageName = $this->composerData['name'];

    expect($packageName)->toMatch(
        '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/',
        'Package name must follow vendor/package format'
    );

    expect($packageName)->toContain('/');
});

it('is configured as a library package', function () {
    expect($this->composerData['type'])->toBe('library', 'Package type must be "library"');
});

it('has a valid open source license', function () {
    $validLicenses = ['MIT', 'Apache-2.0', 'GPL-2.0', 'GPL-3.0', 'BSD-2-Clause', 'BSD-3-Clause'];

    expect($validLicenses)->toContain($this->composerData['license']);
});

it('has a descriptive package description', function () {
    $description = $this->composerData['description'];

    expect($description)->toBeString('Description must be a string');
    expect(strlen($description))->toBeGreaterThan(20, 'Description must be descriptive (>20 chars)');
    expect(strlen($description))->toBeLessThan(200, 'Description should be concise (<200 chars)');

    // Should contain relevant keywords
    $keywords = ['laravel', 'golf', 'ocr', 'scorecard'];
    $descriptionLower = strtolower($description);

    $foundKeywords = array_filter($keywords, fn ($keyword) => str_contains($descriptionLower, $keyword));
    expect(count($foundKeywords))->toBeGreaterThan(0, 'Description should contain relevant keywords');
});

it('includes relevant keywords', function () {
    $keywords = $this->composerData['keywords'];

    expect($keywords)->toBeArray('Keywords must be an array');
    expect(count($keywords))->toBeGreaterThan(3, 'Package should have multiple relevant keywords');

    $expectedKeywords = ['laravel', 'golf', 'ocr', 'scorecard', 'package'];
    foreach ($expectedKeywords as $expectedKeyword) {
        expect($keywords)->toContain($expectedKeyword);
    }
});

it('has valid author information', function () {
    $authors = $this->composerData['authors'];

    expect($authors)->toBeArray('Authors must be an array');
    expect(count($authors))->toBeGreaterThan(0, 'Package must have at least one author');

    $firstAuthor = $authors[0];
    expect($firstAuthor)->toHaveKey('name');
    expect($firstAuthor['name'])->toBe('Golf Scorecard Scanner Team');
    expect($firstAuthor)->toHaveKey('email');
    expect($firstAuthor['email'])->toBe('team@scorecard-scanner.com');
});

it('requires compatible PHP version', function () {
    $phpRequirement = $this->composerData['require']['php'];

    expect($phpRequirement)->toBeString('PHP requirement must be specified');

    // Should require PHP 8.1 or higher for Laravel 11+ compatibility
    $versionParser = new VersionParser;
    $constraint = $versionParser->parseConstraints($phpRequirement);

    // Test that PHP 8.4 is satisfied (our target version)
    $php84Constraint = $versionParser->parseConstraints('8.4.0');
    expect(
        $constraint->matches($php84Constraint)
    )->toBeTrue('Package should be compatible with PHP 8.4');
});

it('requires compatible Laravel version', function () {
    $laravelRequirement = $this->composerData['require']['laravel/framework'];

    expect($laravelRequirement)->toBeString('Laravel framework requirement must be specified');

    // Should require Laravel 11+ for modern features
    $versionParser = new VersionParser;
    $constraint = $versionParser->parseConstraints($laravelRequirement);

    // Test that Laravel 11.0 is satisfied
    $laravel11Constraint = $versionParser->parseConstraints('11.0.0');
    expect(
        $constraint->matches($laravel11Constraint)
    )->toBeTrue('Package should be compatible with Laravel 11+');
});

it('declares all required dependencies', function () {
    $requiredDependencies = [
        'php',
        'laravel/framework',
        'intervention/image-laravel',
    ];

    foreach ($requiredDependencies as $dependency) {
        expect($this->composerData['require'])->toHaveKey($dependency);
    }
});

it('has proper PSR-4 autoload configuration', function () {
    expect($this->composerData)->toHaveKey('autoload');

    $autoload = $this->composerData['autoload'];
    expect($autoload)->toHaveKey('psr-4');

    $psr4 = $autoload['psr-4'];
    expect($psr4)->toHaveKey('ScorecardScanner\\');
    expect($psr4['ScorecardScanner\\'])->toBe('src/');
});

it('enables Laravel service provider auto-discovery', function () {
    expect($this->composerData)->toHaveKey('extra');

    $extra = $this->composerData['extra'];
    expect($extra)->toHaveKey('laravel');

    $laravel = $extra['laravel'];
    expect($laravel)->toHaveKey('providers');

    $providers = $laravel['providers'];
    expect($providers)->toContain('ScorecardScanner\\Providers\\ScorecardScannerServiceProvider');
});

it('prefers stable dependencies when configured', function () {
    // For production packages, minimum-stability should be 'stable'
    if (isset($this->composerData['minimum-stability'])) {
        $validStabilities = ['stable', 'RC', 'beta'];
        expect($validStabilities)->toContain($this->composerData['minimum-stability']);
    } else {
        $this->markTestSkipped('No minimum-stability configured');
    }
});

it('has valid version constraint syntax', function () {
    $versionParser = new VersionParser;

    foreach ($this->composerData['require'] as $package => $constraint) {
        expect(function () use ($versionParser, $constraint) {
            $versionParser->parseConstraints($constraint);
        })->not->toThrow(Exception::class, "Version constraint for '{$package}' should be valid: {$constraint}");
    }
});

it('has valid homepage URL when configured', function () {
    if (isset($this->composerData['homepage'])) {
        $homepage = $this->composerData['homepage'];
        expect($homepage)->toMatch(
            '/^https?:\/\/.+/',
            'Homepage must be a valid URL'
        );
    } else {
        $this->markTestSkipped('No homepage URL configured');
    }
});

it('has valid support URLs when configured', function () {
    if (isset($this->composerData['support'])) {
        $support = $this->composerData['support'];

        $urlFields = ['issues', 'source', 'docs'];
        foreach ($urlFields as $field) {
            if (isset($support[$field])) {
                expect($support[$field])->toMatch(
                    '/^https?:\/\/.+/',
                    "Support URL '{$field}' must be valid"
                );
            }
        }
    } else {
        $this->markTestSkipped('No support URLs configured');
    }
});

it('can be validated by Composer', function () {
    // Simple validation - ensure composer.json can be decoded
    $composerPath = base_path('composer.json');
    $content = File::get($composerPath);
    $decoded = json_decode($content, true);

    expect($decoded)->toBeArray('composer.json must be valid JSON');
    expect(json_last_error())->toBe(JSON_ERROR_NONE, 'composer.json must have no JSON syntax errors');
});
