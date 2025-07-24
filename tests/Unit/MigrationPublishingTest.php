<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

class MigrationPublishingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clean up any published migrations from previous tests
        $this->cleanupPublishedMigrations();
    }

    protected function tearDown(): void
    {
        $this->cleanupPublishedMigrations();
        parent::tearDown();
    }

    public function test_package_migrations_directory_exists()
    {
        $migrationsPath = base_path('src/database/migrations');
        $this->assertDirectoryExists($migrationsPath);
    }

    public function test_package_contains_required_migration_files()
    {
        $migrationsPath = base_path('src/database/migrations');
        $migrationFiles = File::files($migrationsPath);
        
        $this->assertGreaterThan(0, count($migrationFiles));
        
        // Check for expected migration files
        $expectedMigrations = [
            'golf_courses',
            'golf_holes', 
            'scorecard_scans',
            'scorecard_players',
            'player_scores'
        ];
        
        foreach ($expectedMigrations as $expectedTable) {
            $found = false;
            foreach ($migrationFiles as $file) {
                if (str_contains($file->getFilename(), $expectedTable)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Migration for table '{$expectedTable}' not found");
        }
    }

    public function test_migrations_can_be_published()
    {
        // Publish migrations using vendor:publish command
        Artisan::call('vendor:publish', [
            '--tag' => 'scorecard-scanner-migrations',
            '--force' => true
        ]);
        
        // Check that migrations were published to database/migrations
        $publishedPath = database_path('migrations');
        $publishedFiles = File::files($publishedPath);
        
        $packageMigrations = File::files(base_path('src/database/migrations'));
        
        // Should have at least the same number of package migrations published
        $this->assertGreaterThanOrEqual(count($packageMigrations), count($publishedFiles));
        
        // Check that specific migration files exist
        $publishedFilenames = collect($publishedFiles)->map(fn($file) => $file->getFilename())->toArray();
        
        foreach ($packageMigrations as $packageMigration) {
            $migrationName = $this->extractMigrationName($packageMigration->getFilename());
            $found = false;
            
            foreach ($publishedFilenames as $publishedFile) {
                if (str_contains($publishedFile, $migrationName)) {
                    $found = true;
                    break;
                }
            }
            
            $this->assertTrue($found, "Migration '{$migrationName}' was not published");
        }
    }

    public function test_published_migrations_have_valid_structure()
    {
        // Publish migrations first
        Artisan::call('vendor:publish', [
            '--tag' => 'scorecard-scanner-migrations',
            '--force' => true
        ]);
        
        $publishedPath = database_path('migrations');
        $publishedFiles = File::files($publishedPath);
        
        foreach ($publishedFiles as $file) {
            $content = File::get($file->getPathname());
            
            // Check that migration has proper Laravel migration structure
            $this->assertStringContainsString('<?php', $content);
            $this->assertStringContainsString('use Illuminate\Database\Migrations\Migration', $content);
            $this->assertStringContainsString('use Illuminate\Database\Schema\Blueprint', $content);
            $this->assertStringContainsString('class ', $content);
            $this->assertStringContainsString('public function up()', $content);
            $this->assertStringContainsString('public function down()', $content);
        }
    }

    public function test_migration_timestamps_are_sequential()
    {
        // Publish migrations
        Artisan::call('vendor:publish', [
            '--tag' => 'scorecard-scanner-migrations',
            '--force' => true
        ]);
        
        $publishedPath = database_path('migrations');
        $publishedFiles = File::files($publishedPath);
        
        $timestamps = [];
        foreach ($publishedFiles as $file) {
            $filename = $file->getFilename();
            if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $filename, $matches)) {
                $timestamps[] = $matches[1];
            }
        }
        
        // Timestamps should be in ascending order
        $sortedTimestamps = $timestamps;
        sort($sortedTimestamps);
        
        $this->assertEquals($sortedTimestamps, $timestamps, 'Migration timestamps should be sequential');
    }

    public function test_published_migrations_can_be_run()
    {
        // Publish migrations
        Artisan::call('vendor:publish', [
            '--tag' => 'scorecard-scanner-migrations',
            '--force' => true
        ]);
        
        // Run migrations (this will test that they are syntactically correct)
        $exitCode = Artisan::call('migrate', ['--pretend' => true]);
        
        $this->assertEquals(0, $exitCode, 'Published migrations should be valid and runnable');
    }

    public function test_migration_publishing_preserves_table_names()
    {
        // Publish migrations
        Artisan::call('vendor:publish', [
            '--tag' => 'scorecard-scanner-migrations',
            '--force' => true
        ]);
        
        $publishedPath = database_path('migrations');
        $publishedFiles = File::files($publishedPath);
        
        $expectedTables = [
            'golf_courses',
            'golf_holes',
            'scorecard_scans', 
            'scorecard_players',
            'player_scores'
        ];
        
        foreach ($expectedTables as $expectedTable) {
            $found = false;
            foreach ($publishedFiles as $file) {
                $content = File::get($file->getPathname());
                if (str_contains($content, "create('{$expectedTable}'")) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Published migrations should preserve table name '{$expectedTable}'");
        }
    }

    public function test_force_publish_overwrites_existing_migrations()
    {
        // Publish migrations first time
        Artisan::call('vendor:publish', [
            '--tag' => 'scorecard-scanner-migrations'
        ]);
        
        $publishedPath = database_path('migrations');
        $firstPublishFiles = File::files($publishedPath);
        $firstPublishCount = count($firstPublishFiles);
        
        // Publish again with force
        Artisan::call('vendor:publish', [
            '--tag' => 'scorecard-scanner-migrations',
            '--force' => true
        ]);
        
        $secondPublishFiles = File::files($publishedPath);
        $secondPublishCount = count($secondPublishFiles);
        
        // Should have same number of files (overwritten, not duplicated)
        $this->assertEquals($firstPublishCount, $secondPublishCount);
    }

    private function extractMigrationName(string $filename): string
    {
        // Extract migration name from filename (e.g., "create_golf_courses_table" from "2024_01_01_000000_create_golf_courses_table.php")
        if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)\.php$/', $filename, $matches)) {
            return $matches[1];
        }
        
        return $filename;
    }

    private function cleanupPublishedMigrations(): void
    {
        $publishedPath = database_path('migrations');
        
        if (File::exists($publishedPath)) {
            $files = File::files($publishedPath);
            foreach ($files as $file) {
                // Only delete files that look like our package migrations
                if (str_contains($file->getFilename(), 'golf_') || 
                    str_contains($file->getFilename(), 'scorecard_') || 
                    str_contains($file->getFilename(), 'player_scores')) {
                    File::delete($file->getPathname());
                }
            }
        }
    }
}