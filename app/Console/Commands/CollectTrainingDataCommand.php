<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Services\TrainingDataService;

class CollectTrainingDataCommand extends Command
{
    protected $signature = 'scorecard:collect-training-data 
                          {--scan-id=* : Specific scan IDs to process}
                          {--all : Process all completed scans}
                          {--overwrite : Overwrite existing training data records}';

    protected $description = 'Collect training data from existing scorecard scans for AI model improvement';

    private TrainingDataService $trainingDataService;

    public function __construct(TrainingDataService $trainingDataService)
    {
        parent::__construct();
        $this->trainingDataService = $trainingDataService;
    }

    public function handle(): int
    {
        $this->info('🤖 Training Data Collection Utility');
        $this->info('====================================');

        // Run migration first to ensure table exists
        $this->info('📋 Ensuring training data table exists...');
        $this->call('migrate', ['--path' => 'database/migrations', '--force' => true]);

        $scans = $this->getScansToProcess();

        if ($scans->isEmpty()) {
            $this->warn('No scans found to process');

            return 0;
        }

        $this->info("\n📊 Found ".$scans->count().' scans to collect training data from');

        $results = [
            'processed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        foreach ($scans as $scan) {
            $this->info("\n".str_repeat('-', 50));
            $this->info("📷 Processing Scan ID: {$scan->id}");
            $this->info(str_repeat('-', 50));

            try {
                $result = $this->processScan($scan);

                if ($result === 'created') {
                    $results['created']++;
                    $this->info('✅ Training data created');
                } elseif ($result === 'skipped') {
                    $results['skipped']++;
                    $this->warn('⏭️  Training data already exists (use --overwrite to replace)');
                }

                $results['processed']++;

            } catch (\Exception $e) {
                $this->error("❌ Failed to process scan {$scan->id}: ".$e->getMessage());
                $results['errors']++;
            }
        }

        $this->displaySummary($results);

        return 0;
    }

    private function getScansToProcess()
    {
        $scanIds = $this->option('scan-id');
        $processAll = $this->option('all');

        if (! empty($scanIds)) {
            // Convert string scan IDs to integers
            $cleanIds = [];
            foreach ($scanIds as $id) {
                if (str_contains($id, ',')) {
                    $cleanIds = array_merge($cleanIds, array_map('intval', explode(',', $id)));
                } else {
                    $cleanIds[] = (int) $id;
                }
            }

            return ScorecardScan::whereIn('id', $cleanIds)
                ->where('status', 'completed')
                ->orderBy('id')
                ->get();
        }

        if ($processAll) {
            return ScorecardScan::where('status', 'completed')
                ->orderBy('id')
                ->get();
        }

        // Interactive selection
        $this->info('📋 Available completed scans:');
        $availableScans = ScorecardScan::where('status', 'completed')
            ->orderBy('id')
            ->get();

        if ($availableScans->isEmpty()) {
            return collect();
        }

        $choice = $this->choice(
            'Select processing option',
            ['All scans', 'Specific scan IDs', 'Cancel'],
            0
        );

        switch ($choice) {
            case 'All scans':
                return $availableScans;

            case 'Specific scan IDs':
                $selectedIds = $this->ask('Enter scan IDs (comma-separated)');
                $ids = array_map('trim', explode(',', $selectedIds));

                return $availableScans->whereIn('id', $ids);

            default:
                return collect();
        }
    }

    private function processScan(ScorecardScan $scan): string
    {
        // Display scan info
        $courseName = $this->extractCourseName($scan);
        $this->line("🏌️ Course: {$courseName}");
        $this->line('📅 Created: '.$scan->created_at->format('Y-m-d H:i:s'));
        $this->line("📊 Status: {$scan->status}");

        // Check if training data already exists
        $existingTrainingData = DB::table('scan_training_data')
            ->where('scorecard_scan_id', $scan->id)
            ->exists();

        if ($existingTrainingData && ! $this->option('overwrite')) {
            return 'skipped';
        }

        // Delete existing if overwriting
        if ($existingTrainingData && $this->option('overwrite')) {
            DB::table('scan_training_data')
                ->where('scorecard_scan_id', $scan->id)
                ->delete();
            $this->line('🗑️  Deleted existing training data');
        }

        // Extract data from scan
        $rawOcrData = $scan->raw_ocr_data ?? [];
        $parsedData = $scan->parsed_data ?? [];

        if (empty($rawOcrData) && empty($parsedData)) {
            throw new \Exception('No OCR or parsed data found in scan');
        }

        // Prepare extracted data
        $extractedData = $this->prepareExtractedData($rawOcrData, $parsedData);

        // Add processing metadata
        $processingMetadata = [
            'scan_created_at' => $scan->created_at->toISOString(),
            'original_image_path' => $scan->original_image_path,
            'processed_image_path' => $scan->processed_image_path,
            'confidence_scores' => $scan->confidence_scores ?? [],
        ];

        // Save training data
        $trainingData = $this->trainingDataService->saveFromScan(
            $scan,
            $rawOcrData,
            $extractedData,
            $processingMetadata
        );

        // Display analysis
        $this->displayTrainingDataAnalysis($trainingData);

        return 'created';
    }

    private function prepareExtractedData(array $rawOcrData, array $parsedData): array
    {
        // Start with parsed data as base
        $extractedData = $parsedData;

        // Add enhanced data if available
        if (isset($parsedData['enhanced_data'])) {
            $extractedData = array_merge($extractedData, $parsedData['enhanced_data']);
        }

        // Add golf course properties if available
        if (isset($rawOcrData['golf_course_properties'])) {
            $golfProps = $rawOcrData['golf_course_properties'];

            $extractedData = array_merge($extractedData, [
                'course_name' => $golfProps['course_name'] ?? $extractedData['course_name'] ?? null,
                'course_location' => $golfProps['course_location'] ?? $extractedData['course_location'] ?? null,
                'tee_name' => $golfProps['tee_name'] ?? $extractedData['tee_name'] ?? null,
                'course_rating' => $golfProps['course_rating'] ?? $extractedData['course_rating'] ?? null,
                'slope_rating' => $golfProps['slope_rating'] ?? $extractedData['slope_rating'] ?? null,
                'total_par' => $golfProps['total_par'] ?? $extractedData['total_par'] ?? null,
                'total_yardage' => $golfProps['total_yardage'] ?? $extractedData['total_yardage'] ?? null,
                'par_values' => $golfProps['par_values'] ?? $extractedData['par_values'] ?? [],
                'handicap_values' => $golfProps['handicap_values'] ?? $extractedData['handicap_values'] ?? [],
                'players' => $golfProps['players'] ?? $extractedData['players'] ?? [],
                'date_played' => $golfProps['date_played'] ?? $extractedData['date'] ?? null,
            ]);
        }

        // Add structured data if available
        if (isset($rawOcrData['structured_data'])) {
            $structuredData = $rawOcrData['structured_data'];

            $extractedData = array_merge($extractedData, [
                'course_name' => $structuredData['course_name'] ?? $extractedData['course_name'],
                'course_location' => $structuredData['course_location'] ?? $extractedData['course_location'],
                'tee_name' => $structuredData['tee_name'] ?? $extractedData['tee_name'],
                'course_rating' => $structuredData['course_rating'] ?? $extractedData['course_rating'],
                'slope_rating' => $structuredData['slope_rating'] ?? $extractedData['slope_rating'],
                'players' => $structuredData['players'] ?? $extractedData['players'],
                'date' => $structuredData['date'] ?? $extractedData['date'],
            ]);
        }

        return array_filter($extractedData, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    private function displayTrainingDataAnalysis($trainingData): void
    {
        $metrics = $trainingData->getQualityMetrics();

        $this->info("\n📊 Training Data Analysis:");

        $analysisData = [
            ['Confidence Score', number_format((float) ($metrics['confidence_score'] ?? 0), 3)],
            ['Data Completeness', ($metrics['data_completeness'] ?? 0).'%'],
            ['Field Count', $metrics['field_count'] ?? 0],
            ['Processing Time', ($metrics['processing_time'] ?? 0).'ms'],
            ['Has Validation Errors', $metrics['has_validation_errors'] ? 'Yes' : 'No'],
            ['Training Candidate', $trainingData->is_training_candidate ? 'Yes' : 'No'],
            ['OCR Provider', $trainingData->ocr_provider],
            ['Enhanced Prompt', $trainingData->used_enhanced_prompt ? 'Yes' : 'No'],
        ];

        $this->table(['Metric', 'Value'], $analysisData);

        // Show validation errors if any
        if (! empty($trainingData->validation_errors)) {
            $this->warn('⚠️  Validation Errors:');
            foreach ($trainingData->validation_errors as $error) {
                $this->line("   • {$error['field']}: {$error['error']}");
            }
        }
    }

    private function displaySummary(array $results): void
    {
        $this->info("\n".str_repeat('=', 50));
        $this->info('📋 TRAINING DATA COLLECTION SUMMARY');
        $this->info(str_repeat('=', 50));

        $summaryData = [
            ['Scans Processed', $results['processed']],
            ['Training Records Created', $results['created']],
            ['Skipped (Already Exists)', $results['skipped']],
            ['Errors', $results['errors']],
        ];

        $this->table(['Metric', 'Count'], $summaryData);

        if ($results['created'] > 0) {
            $this->info("\n🔍 Next Steps:");
            $this->line('   • Review training data: php artisan scorecard:analyze-training-data');
            $this->line('   • Export for model training: php artisan scorecard:export-training-data');
            $this->line('   • Verify accuracy on samples: Add verified_data to improve model');
        }

        $this->info("\n✅ Training data collection completed!");
    }

    private function extractCourseName(ScorecardScan $scan): string
    {
        $parsedData = $scan->parsed_data ?? [];
        $rawData = $scan->raw_ocr_data ?? [];

        return $parsedData['course_name']
            ?? $parsedData['enhanced_data']['course_name']
            ?? $rawData['golf_course_properties']['course_name']
            ?? $rawData['structured_data']['course_name']
            ?? 'Unknown Course';
    }
}
