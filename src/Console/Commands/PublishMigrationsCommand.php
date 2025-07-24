<?php

declare(strict_types=1);

namespace ScorecardScanner\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishMigrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'scorecard-scanner:publish-migrations 
                            {--force : Overwrite existing migrations}
                            {--timestamp= : Use specific timestamp prefix}';

    /**
     * The console command description.
     */
    protected $description = 'Publish Scorecard Scanner migrations with sequential timestamps';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $customTimestamp = $this->option('timestamp');

        $sourcePath = __DIR__.'/../../database/migrations';
        $targetPath = database_path('migrations');

        if (! File::exists($sourcePath)) {
            $this->error('Source migrations directory not found: '.$sourcePath);

            return 1;
        }

        if (! File::exists($targetPath)) {
            File::makeDirectory($targetPath, 0755, true);
        }

        $sourceFiles = File::files($sourcePath);

        if (empty($sourceFiles)) {
            $this->error('No migration files found in source directory');

            return 1;
        }

        $baseTimestamp = null;
        if ($customTimestamp && is_string($customTimestamp)) {
            $baseTimestamp = Carbon::createFromFormat('Y_m_d_His', $customTimestamp);
        }

        if (! $baseTimestamp) {
            $baseTimestamp = Carbon::now();
        }

        $publishedCount = 0;

        foreach ($sourceFiles as $index => $sourceFile) {
            $timestamp = $baseTimestamp->copy()->addMinutes($index)->format('Y_m_d_His');
            $originalFilename = $sourceFile->getFilename();

            // Extract migration name (remove old timestamp if present)
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)\.php$/', $originalFilename, $matches)) {
                $migrationName = $matches[1];
            } else {
                $migrationName = pathinfo($originalFilename, PATHINFO_FILENAME);
            }

            $newFilename = $timestamp.'_'.$migrationName.'.php';
            $targetFile = $targetPath.DIRECTORY_SEPARATOR.$newFilename;

            // Check if migration already exists
            if (File::exists($targetFile) && ! $force) {
                $this->warn("Migration already exists: {$newFilename}");
                $this->info('Use --force to overwrite existing migrations');

                continue;
            }

            // Read source content and update timestamps in class
            $content = File::get($sourceFile->getPathname());

            // Copy file to target location
            if (File::put($targetFile, $content)) {
                $this->info("Published: {$newFilename}");
                $publishedCount++;
            } else {
                $this->error("Failed to publish: {$newFilename}");
            }
        }

        if ($publishedCount > 0) {
            $this->comment("\nPublished {$publishedCount} migration(s) successfully!");
            $this->comment("Run 'php artisan migrate' to execute the published migrations.");
        } else {
            $this->comment('No migrations were published.');
        }

        return 0;
    }
}
