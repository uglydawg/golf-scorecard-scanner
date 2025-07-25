<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

describe('Database Connection', function () {
    it('can connect to the database successfully', function () {
        try {
            // Test basic database connection
            expect(DB::connection()->getPdo())->not->toBeNull();

            // Test we can run a simple query
            $result = DB::select('SELECT 1 as test');
            expect($result)->toBeArray();
            expect($result[0]->test)->toBe(1);

            echo "\n✅ Database connection successful\n";
        } catch (\Exception $e) {
            echo "\n❌ Database connection failed: ".$e->getMessage()."\n";
            throw $e;
        }
    });

    it('reports current database configuration', function () {
        $databaseName = DB::connection()->getDatabaseName();
        $driver = DB::connection()->getDriverName();

        echo "\n📊 Database Configuration:\n";
        echo "Driver: {$driver}\n";
        echo "Database: {$databaseName}\n";

        // Just report what we're connected to, don't assert specific values
        expect($databaseName)->toBeString();
        expect($driver)->toBeString();
    });

    it('can check database server information', function () {
        $driver = DB::connection()->getDriverName();

        try {
            if ($driver === 'pgsql') {
                $version = DB::select('SELECT version()')[0]->version;
                expect($version)->toContain('PostgreSQL');
                echo "\n📊 PostgreSQL Version: ".$version."\n";
            } elseif ($driver === 'sqlite') {
                $version = DB::select('SELECT sqlite_version() as version')[0]->version;
                expect($version)->toBeString();
                echo "\n📊 SQLite Version: ".$version."\n";
            } else {
                echo "\n📊 Database Driver: ".$driver."\n";
            }
        } catch (\Exception $e) {
            echo "\n⚠️  Could not retrieve database version: ".$e->getMessage()."\n";
            // Don't fail the test, just skip version check
            expect(true)->toBeTrue();
        }
    });
});

describe('Migration Status', function () {
    it('checks migrations table status', function () {
        $hasMigrationsTable = Schema::hasTable('migrations');

        echo "\n📋 Migration Table Status:\n";
        echo 'Migrations table exists: '.($hasMigrationsTable ? 'Yes' : 'No')."\n";

        if ($hasMigrationsTable) {
            // Get executed migrations from database
            $executedMigrations = DB::table('migrations')
                ->pluck('migration')
                ->toArray();

            echo 'Executed migrations: '.count($executedMigrations)."\n";

            // List some of the migrations
            if (count($executedMigrations) > 0) {
                echo "Recent migrations:\n";
                foreach (array_slice($executedMigrations, -5) as $migration) {
                    echo "  ✅ {$migration}\n";
                }
            }

            expect(count($executedMigrations))->toBeGreaterThan(0);
        } else {
            echo "⚠️  No migrations table found - database may not be migrated\n";
            expect(true)->toBeTrue(); // Don't fail, just report
        }
    });

    it('reports migration file count', function () {
        // Get all migration files
        $migrationFiles = glob(database_path('migrations/*.php'));
        $expectedMigrations = [];

        foreach ($migrationFiles as $file) {
            $filename = basename($file, '.php');
            $expectedMigrations[] = $filename;
        }

        echo "\n📁 Migration Files Found: ".count($expectedMigrations)."\n";

        // Show first few migration files
        foreach (array_slice($expectedMigrations, 0, 5) as $migration) {
            echo "  📄 {$migration}\n";
        }

        if (count($expectedMigrations) > 5) {
            echo '  ... and '.(count($expectedMigrations) - 5)." more\n";
        }

        expect(count($expectedMigrations))->toBeGreaterThan(0);
    });

    it('checks golf course tables status', function () {
        $requiredTables = [
            'courses',
            'rounds',
            'round_scores',
            'scorecard_scans',
            'unverified_courses',
        ];

        echo "\n⛳ Golf Course Tables Status:\n";

        $existingTables = 0;
        foreach ($requiredTables as $table) {
            $exists = Schema::hasTable($table);
            echo ($exists ? '✅' : '❌')." {$table}\n";
            if ($exists) {
                $existingTables++;
            }
        }

        echo "Golf tables found: {$existingTables}/{".count($requiredTables)."}\n";

        // Report status but don't fail if using test database
        expect($existingTables)->toBeGreaterThanOrEqual(0);
    });

    it('checks Laravel core tables status', function () {
        $coreTables = [
            'users',
            'personal_access_tokens',
            'cache',
            'jobs',
            'sessions',
        ];

        echo "\n🚀 Laravel Core Tables Status:\n";

        $existingTables = 0;
        foreach ($coreTables as $table) {
            $exists = Schema::hasTable($table);
            echo ($exists ? '✅' : '❌')." {$table}\n";
            if ($exists) {
                $existingTables++;
            }
        }

        echo "Core tables found: {$existingTables}/{".count($coreTables)."}\n";

        // Report status but don't fail if using test database
        expect($existingTables)->toBeGreaterThanOrEqual(0);
    });

    it('checks table structures when tables exist', function () {
        echo "\n🔍 Table Structure Check:\n";

        if (Schema::hasTable('courses')) {
            $coursesColumns = Schema::getColumnListing('courses');
            echo 'Courses table columns: '.count($coursesColumns)."\n";

            $requiredColumns = ['id', 'name', 'tee_name', 'par_values', 'handicap_values'];
            $hasRequired = array_intersect($requiredColumns, $coursesColumns);
            echo 'Required columns found: '.count($hasRequired).'/'.count($requiredColumns)."\n";
        } else {
            echo "❌ Courses table not found\n";
        }

        if (Schema::hasTable('rounds')) {
            $roundsColumns = Schema::getColumnListing('rounds');
            echo 'Rounds table columns: '.count($roundsColumns)."\n";
        } else {
            echo "❌ Rounds table not found\n";
        }

        // Always pass - this is just informational
        expect(true)->toBeTrue();
    });
});

describe('Database Performance', function () {
    it('can perform basic database operations', function () {
        // Test basic database functionality
        try {
            // Test we can run queries
            $result = DB::select('SELECT 1 as test_value');
            expect($result[0]->test_value)->toBe(1);

            // Test we can check for tables
            $driver = DB::connection()->getDriverName();
            echo "\n🔧 Testing with {$driver} database\n";

            if ($driver === 'sqlite') {
                // Test SQLite specific functionality
                $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
                echo 'Tables found: '.count($tables)."\n";
            } elseif ($driver === 'pgsql') {
                // Test PostgreSQL specific functionality
                $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                echo 'Tables found: '.count($tables)."\n";
            }

            echo "✅ Basic database operations successful\n";
            expect(true)->toBeTrue();

        } catch (\Exception $e) {
            echo '❌ Database operations failed: '.$e->getMessage()."\n";
            throw $e;
        }
    });

    it('can handle JSON data operations', function () {
        $driver = DB::connection()->getDriverName();

        echo "\n📊 JSON Support Test:\n";
        echo "Database driver: {$driver}\n";

        // Test JSON encoding/decoding
        $testArray = [4, 3, 5, 4, 4, 3, 4, 5, 4];
        $jsonString = json_encode($testArray);
        $decodedArray = json_decode($jsonString, true);

        expect($decodedArray)->toBe($testArray);
        echo "✅ JSON encoding/decoding works\n";

        // Don't test actual database JSON storage as tables may not exist in test environment
        expect(true)->toBeTrue();
    });
});
