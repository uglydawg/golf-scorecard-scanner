<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->cleanupPublishedMigrations();
});

afterEach(function () {
    $this->cleanupPublishedMigrations();
});

it('has migrations directory in package', function () {
    $migrationsPath = base_path('src/database/migrations');
    expect($migrationsPath)->toBeDirectory();
});

it('contains all required migration files', function () {
    $migrationsPath = base_path('src/database/migrations');
    $migrationFiles = File::files($migrationsPath);

    expect(count($migrationFiles))->toBeGreaterThan(0);

    // Check for expected migration files
    $expectedMigrations = [
        'golf_courses',
        'golf_holes',
        'scorecard_scans',
        'scorecard_players',
        'player_scores',
    ];

    foreach ($expectedMigrations as $expectedTable) {
        $found = false;
        foreach ($migrationFiles as $file) {
            if (str_contains($file->getFilename(), $expectedTable)) {
                $found = true;
                break;
            }
        }
        expect($found)->toBeTrue("Migration for table '{$expectedTable}' not found");
    }
});

it('can publish migrations to host application', function () {
    // Publish migrations using vendor:publish command
    Artisan::call('vendor:publish', [
        '--tag' => 'scorecard-scanner-migrations',
        '--force' => true,
    ]);

    // Check that migrations were published to database/migrations
    $publishedPath = database_path('migrations');
    $publishedFiles = File::files($publishedPath);

    $packageMigrations = File::files(base_path('src/database/migrations'));

    // Should have at least the same number of package migrations published
    expect(count($publishedFiles))->toBeGreaterThanOrEqual(count($packageMigrations));

    // Check that specific migration files exist
    $publishedFilenames = collect($publishedFiles)->map(fn ($file) => $file->getFilename())->toArray();

    foreach ($packageMigrations as $packageMigration) {
        $migrationName = extractMigrationName($packageMigration->getFilename());
        $found = false;

        foreach ($publishedFilenames as $publishedFile) {
            if (str_contains($publishedFile, $migrationName)) {
                $found = true;
                break;
            }
        }

        expect($found)->toBeTrue("Migration '{$migrationName}' was not published");
    }
});

it('publishes migrations with valid Laravel structure', function () {
    // Publish migrations first
    Artisan::call('vendor:publish', [
        '--tag' => 'scorecard-scanner-migrations',
        '--force' => true,
    ]);

    $publishedPath = database_path('migrations');
    $publishedFiles = File::files($publishedPath);

    foreach ($publishedFiles as $file) {
        $content = File::get($file->getPathname());

        // Check that migration has proper Laravel migration structure
        expect($content)->toContain('<?php');
        expect($content)->toContain('use Illuminate\Database\Migrations\Migration');
        expect($content)->toContain('use Illuminate\Database\Schema\Blueprint');
        expect($content)->toContain('class ');
        expect($content)->toContain('public function up()');
        expect($content)->toContain('public function down()');
    }
});

it('publishes migrations with sequential timestamps', function () {
    // Publish migrations
    Artisan::call('vendor:publish', [
        '--tag' => 'scorecard-scanner-migrations',
        '--force' => true,
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

    expect($timestamps)->toBe($sortedTimestamps, 'Migration timestamps should be sequential');
});

it('publishes runnable migrations', function () {
    // Publish migrations
    Artisan::call('vendor:publish', [
        '--tag' => 'scorecard-scanner-migrations',
        '--force' => true,
    ]);

    // Run migrations (this will test that they are syntactically correct)
    $exitCode = Artisan::call('migrate', ['--pretend' => true]);

    expect($exitCode)->toBe(0, 'Published migrations should be valid and runnable');
});

it('preserves table names when publishing migrations', function () {
    // Publish migrations
    Artisan::call('vendor:publish', [
        '--tag' => 'scorecard-scanner-migrations',
        '--force' => true,
    ]);

    $publishedPath = database_path('migrations');
    $publishedFiles = File::files($publishedPath);

    $expectedTables = [
        'golf_courses',
        'golf_holes',
        'scorecard_scans',
        'scorecard_players',
        'player_scores',
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
        expect($found)->toBeTrue("Published migrations should preserve table name '{$expectedTable}'");
    }
});

it('overwrites existing migrations when force publishing', function () {
    // Publish migrations first time
    Artisan::call('vendor:publish', [
        '--tag' => 'scorecard-scanner-migrations',
    ]);

    $publishedPath = database_path('migrations');
    $firstPublishFiles = File::files($publishedPath);
    $firstPublishCount = count($firstPublishFiles);

    // Publish again with force
    Artisan::call('vendor:publish', [
        '--tag' => 'scorecard-scanner-migrations',
        '--force' => true,
    ]);

    $secondPublishFiles = File::files($publishedPath);
    $secondPublishCount = count($secondPublishFiles);

    // Should have same number of files (overwritten, not duplicated)
    expect($secondPublishCount)->toBe($firstPublishCount);
});

function extractMigrationName(string $filename): string
{
    // Extract migration name from filename (e.g., "create_golf_courses_table" from "2024_01_01_000000_create_golf_courses_table.php")
    if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)\.php$/', $filename, $matches)) {
        return $matches[1];
    }

    return $filename;
}

function cleanupPublishedMigrations(): void
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
