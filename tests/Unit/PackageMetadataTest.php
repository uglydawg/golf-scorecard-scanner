<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\File;
use Composer\Semver\VersionParser;
use Composer\Semver\Comparator;

class PackageMetadataTest extends TestCase
{
    private array $composerData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $composerPath = base_path('composer.json');
        $this->assertTrue(File::exists($composerPath), 'composer.json file must exist');
        
        $this->composerData = json_decode(File::get($composerPath), true);
        $this->assertIsArray($this->composerData, 'composer.json must contain valid JSON');
    }

    public function test_composer_json_has_required_package_metadata()
    {
        $requiredFields = [
            'name',
            'description', 
            'type',
            'license',
            'authors',
            'keywords',
            'homepage',
            'require'
        ];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $this->composerData, "composer.json must have '{$field}' field");
        }
    }

    public function test_package_name_follows_vendor_package_format()
    {
        $packageName = $this->composerData['name'];
        
        $this->assertMatchesRegularExpression(
            '/^[a-z0-9]([_.-]?[a-z0-9]+)*\/[a-z0-9]([_.-]?[a-z0-9]+)*$/',
            $packageName,
            'Package name must follow vendor/package format'
        );
        
        $this->assertStringContainsString('/', $packageName, 'Package name must contain vendor/package separator');
    }

    public function test_package_type_is_library()
    {
        $this->assertEquals('library', $this->composerData['type'], 'Package type must be "library"');
    }

    public function test_package_has_valid_license()
    {
        $validLicenses = ['MIT', 'Apache-2.0', 'GPL-2.0', 'GPL-3.0', 'BSD-2-Clause', 'BSD-3-Clause'];
        
        $this->assertContains(
            $this->composerData['license'],
            $validLicenses,
            'Package must have a valid open source license'
        );
    }

    public function test_package_description_is_descriptive()
    {
        $description = $this->composerData['description'];
        
        $this->assertIsString($description, 'Description must be a string');
        $this->assertGreaterThan(20, strlen($description), 'Description must be descriptive (>20 chars)');
        $this->assertLessThan(200, strlen($description), 'Description should be concise (<200 chars)');
        
        // Should contain relevant keywords
        $keywords = ['laravel', 'golf', 'ocr', 'scorecard'];
        $descriptionLower = strtolower($description);
        
        $foundKeywords = array_filter($keywords, fn($keyword) => str_contains($descriptionLower, $keyword));
        $this->assertGreaterThan(0, count($foundKeywords), 'Description should contain relevant keywords');
    }

    public function test_package_has_relevant_keywords()
    {
        $keywords = $this->composerData['keywords'];
        
        $this->assertIsArray($keywords, 'Keywords must be an array');
        $this->assertGreaterThan(3, count($keywords), 'Package should have multiple relevant keywords');
        
        $expectedKeywords = ['laravel', 'golf', 'ocr', 'scorecard', 'package'];
        foreach ($expectedKeywords as $expectedKeyword) {
            $this->assertContains($expectedKeyword, $keywords, "Keywords should include '{$expectedKeyword}'");
        }
    }

    public function test_package_has_author_information()
    {
        $authors = $this->composerData['authors'];
        
        $this->assertIsArray($authors, 'Authors must be an array');
        $this->assertGreaterThan(0, count($authors), 'Package must have at least one author');
        
        $firstAuthor = $authors[0];
        $this->assertArrayHasKey('name', $firstAuthor, 'Author must have name');
        $this->assertArrayHasKey('email', $firstAuthor, 'Author must have email');
    }

    public function test_package_requires_compatible_php_version()
    {
        $phpRequirement = $this->composerData['require']['php'];
        
        $this->assertIsString($phpRequirement, 'PHP requirement must be specified');
        
        // Should require PHP 8.1 or higher for Laravel 11+ compatibility
        $versionParser = new VersionParser();
        $constraint = $versionParser->parseConstraints($phpRequirement);
        
        // Test that PHP 8.4 is satisfied (our target version)
        $php84Constraint = $versionParser->parseConstraints('8.4.0');
        $this->assertTrue(
            $constraint->matches($php84Constraint),
            'Package should be compatible with PHP 8.4'
        );
    }

    public function test_package_requires_compatible_laravel_version()
    {
        $laravelRequirement = $this->composerData['require']['laravel/framework'];
        
        $this->assertIsString($laravelRequirement, 'Laravel framework requirement must be specified');
        
        // Should require Laravel 11+ for modern features
        $versionParser = new VersionParser();
        $constraint = $versionParser->parseConstraints($laravelRequirement);
        
        // Test that Laravel 11.0 is satisfied
        $laravel11Constraint = $versionParser->parseConstraints('11.0.0');
        $this->assertTrue(
            $constraint->matches($laravel11Constraint),
            'Package should be compatible with Laravel 11+'
        );
    }

    public function test_package_has_required_dependencies()
    {
        $requiredDependencies = [
            'php',
            'laravel/framework',
            'intervention/image-laravel'
        ];
        
        foreach ($requiredDependencies as $dependency) {
            $this->assertArrayHasKey(
                $dependency,
                $this->composerData['require'],
                "Package must require '{$dependency}'"
            );
        }
    }

    public function test_package_has_proper_autoload_configuration()
    {
        $this->assertArrayHasKey('autoload', $this->composerData, 'Package must have autoload configuration');
        
        $autoload = $this->composerData['autoload'];
        $this->assertArrayHasKey('psr-4', $autoload, 'Package must use PSR-4 autoloading');
        
        $psr4 = $autoload['psr-4'];
        $this->assertArrayHasKey('ScorecardScanner\\', $psr4, 'Package must define ScorecardScanner namespace');
        $this->assertEquals('src/', $psr4['ScorecardScanner\\'], 'ScorecardScanner namespace must map to src/ directory');
    }

    public function test_package_has_service_provider_discovery()
    {
        $this->assertArrayHasKey('extra', $this->composerData, 'Package must have extra configuration');
        
        $extra = $this->composerData['extra'];
        $this->assertArrayHasKey('laravel', $extra, 'Package must have Laravel-specific configuration');
        
        $laravel = $extra['laravel'];
        $this->assertArrayHasKey('providers', $laravel, 'Package must define service providers');
        
        $providers = $laravel['providers'];
        $this->assertContains(
            'ScorecardScanner\\Providers\\ScorecardScannerServiceProvider',
            $providers,
            'Package must include ScorecardScannerServiceProvider in auto-discovery'
        );
    }

    public function test_package_has_minimum_stability_preference()
    {
        // For production packages, minimum-stability should be 'stable'
        if (isset($this->composerData['minimum-stability'])) {
            $validStabilities = ['stable', 'RC', 'beta'];
            $this->assertContains(
                $this->composerData['minimum-stability'],
                $validStabilities,
                'Package should prefer stable dependencies'
            );
        }
    }

    public function test_package_version_constraint_syntax_is_valid()
    {
        $versionParser = new VersionParser();
        
        foreach ($this->composerData['require'] as $package => $constraint) {
            try {
                $versionParser->parseConstraints($constraint);
                $this->assertTrue(true, "Version constraint for '{$package}' is valid");
            } catch (\Exception $e) {
                $this->fail("Invalid version constraint for '{$package}': {$constraint}");
            }
        }
    }

    public function test_package_homepage_is_valid_url()
    {
        if (isset($this->composerData['homepage'])) {
            $homepage = $this->composerData['homepage'];
            $this->assertMatchesRegularExpression(
                '/^https?:\/\/.+/',
                $homepage,
                'Homepage must be a valid URL'
            );
        }
    }

    public function test_package_support_urls_are_valid()
    {
        if (isset($this->composerData['support'])) {
            $support = $this->composerData['support'];
            
            $urlFields = ['issues', 'source', 'docs'];
            foreach ($urlFields as $field) {
                if (isset($support[$field])) {
                    $this->assertMatchesRegularExpression(
                        '/^https?:\/\/.+/',
                        $support[$field],
                        "Support URL '{$field}' must be valid"
                    );
                }
            }
        }
    }

    public function test_package_can_be_validated_by_composer()
    {
        // Simple validation - ensure composer.json can be decoded
        $composerPath = base_path('composer.json');
        $content = File::get($composerPath);
        $decoded = json_decode($content, true);
        
        $this->assertIsArray($decoded, 'composer.json must be valid JSON');
        $this->assertEquals(JSON_ERROR_NONE, json_last_error(), 'composer.json must have no JSON syntax errors');
    }
}