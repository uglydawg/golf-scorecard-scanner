<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ScorecardScanner\Models\ScanTrainingData;
use ScorecardScanner\Services\TrainingDataService;

class AnalyzeTrainingDataCommand extends Command
{
    protected $signature = 'scorecard:analyze-training-data 
                          {--export : Export training data to JSON file}
                          {--format=json : Export format (json, csv)}
                          {--verified-only : Only include verified data}
                          {--min-confidence=0.0 : Minimum confidence threshold}
                          {--provider= : Filter by OCR provider}
                          {--enhanced-only : Only include enhanced prompt data}';

    protected $description = 'Analyze collected training data and optionally export for model improvement';

    private TrainingDataService $trainingDataService;

    public function __construct(TrainingDataService $trainingDataService)
    {
        parent::__construct();
        $this->trainingDataService = $trainingDataService;
    }

    public function handle(): int
    {
        $this->info('📊 Training Data Analysis');
        $this->info('=========================');

        // Get all training data
        $trainingData = ScanTrainingData::with('scorecardScan')->get();

        if ($trainingData->isEmpty()) {
            $this->warn('No training data found. Run: php artisan scorecard:collect-training-data');

            return 1;
        }

        $this->info("📋 Found {$trainingData->count()} training data records\n");

        // Perform analysis
        $this->analyzeOverallMetrics($trainingData);
        $this->analyzeByProvider($trainingData);
        $this->analyzeByConfidence($trainingData);
        $this->analyzeValidationErrors($trainingData);
        $this->analyzeTrainingCandidates($trainingData);

        // Export if requested
        if ($this->option('export')) {
            $this->exportTrainingData();
        }

        return 0;
    }

    private function analyzeOverallMetrics($trainingData): void
    {
        $this->info('📊 Overall Metrics:');

        $totalRecords = $trainingData->count();
        $verifiedRecords = $trainingData->where('is_verified', true)->count();
        $trainingCandidates = $trainingData->where('is_training_candidate', true)->count();
        $enhancedPromptUsage = $trainingData->where('used_enhanced_prompt', true)->count();

        $avgConfidence = $trainingData->avg('confidence_score');
        $avgCompleteness = $trainingData->avg('data_completeness_score');
        $avgProcessingTime = $trainingData->avg('processing_time_ms');

        $overallData = [
            ['Total Records', $totalRecords],
            ['Verified Records', "{$verifiedRecords} (".round(($verifiedRecords / $totalRecords) * 100, 1).'%)'],
            ['Training Candidates', "{$trainingCandidates} (".round(($trainingCandidates / $totalRecords) * 100, 1).'%)'],
            ['Enhanced Prompt Used', "{$enhancedPromptUsage} (".round(($enhancedPromptUsage / $totalRecords) * 100, 1).'%)'],
            ['Avg Confidence Score', number_format($avgConfidence ?? 0, 3)],
            ['Avg Data Completeness', number_format($avgCompleteness ?? 0, 1).'%'],
            ['Avg Processing Time', number_format($avgProcessingTime ?? 0, 0).'ms'],
        ];

        $this->table(['Metric', 'Value'], $overallData);
    }

    private function analyzeByProvider($trainingData): void
    {
        $this->info("\n🔧 Analysis by OCR Provider:");

        $providerStats = $trainingData->groupBy('ocr_provider')->map(function ($records, $provider) {
            $count = $records->count();
            $avgConfidence = $records->avg('confidence_score');
            $avgCompleteness = $records->avg('data_completeness_score');
            $trainingCandidates = $records->where('is_training_candidate', true)->count();

            return [
                'provider' => $provider,
                'count' => $count,
                'avg_confidence' => number_format($avgConfidence ?? 0, 3),
                'avg_completeness' => number_format($avgCompleteness ?? 0, 1).'%',
                'training_candidates' => $trainingCandidates,
                'candidate_rate' => number_format(($trainingCandidates / $count) * 100, 1).'%',
            ];
        })->values();

        $providerData = $providerStats->map(function ($stats) {
            return [
                $stats['provider'],
                $stats['count'],
                $stats['avg_confidence'],
                $stats['avg_completeness'],
                $stats['training_candidates'],
                $stats['candidate_rate'],
            ];
        })->toArray();

        $this->table(
            ['Provider', 'Count', 'Avg Confidence', 'Avg Completeness', 'Training Candidates', 'Candidate Rate'],
            $providerData
        );
    }

    private function analyzeByConfidence($trainingData): void
    {
        $this->info("\n📈 Analysis by Confidence Score:");

        $confidenceRanges = [
            'Very High (0.9+)' => $trainingData->where('confidence_score', '>=', 0.9)->count(),
            'High (0.8-0.89)' => $trainingData->whereBetween('confidence_score', [0.8, 0.89])->count(),
            'Medium (0.7-0.79)' => $trainingData->whereBetween('confidence_score', [0.7, 0.79])->count(),
            'Low (0.6-0.69)' => $trainingData->whereBetween('confidence_score', [0.6, 0.69])->count(),
            'Very Low (<0.6)' => $trainingData->where('confidence_score', '<', 0.6)->count(),
        ];

        $confidenceData = [];
        foreach ($confidenceRanges as $range => $count) {
            $percentage = round(($count / $trainingData->count()) * 100, 1);
            $confidenceData[] = [$range, $count, "{$percentage}%"];
        }

        $this->table(['Confidence Range', 'Count', 'Percentage'], $confidenceData);
    }

    private function analyzeValidationErrors($trainingData): void
    {
        $this->info("\n⚠️  Validation Error Analysis:");

        $recordsWithErrors = $trainingData->filter(function ($record) {
            return ! empty($record->validation_errors);
        });

        if ($recordsWithErrors->isEmpty()) {
            $this->line('✅ No validation errors found in training data');

            return;
        }

        $this->warn("Found {$recordsWithErrors->count()} records with validation errors");

        // Aggregate error types
        $errorTypes = [];
        foreach ($recordsWithErrors as $record) {
            foreach ($record->validation_errors as $error) {
                $key = $error['field'].': '.$error['error'];
                $errorTypes[$key] = ($errorTypes[$key] ?? 0) + 1;
            }
        }

        arsort($errorTypes);

        $errorData = [];
        foreach (array_slice($errorTypes, 0, 10) as $error => $count) {
            $errorData[] = [$error, $count];
        }

        $this->table(['Error Type', 'Count'], $errorData);
    }

    private function analyzeTrainingCandidates($trainingData): void
    {
        $this->info("\n🎯 Training Candidate Analysis:");

        $candidates = $trainingData->where('is_training_candidate', true);
        $nonCandidates = $trainingData->where('is_training_candidate', false);

        if ($candidates->isEmpty()) {
            $this->warn('No training candidates found');

            return;
        }

        $candidateData = [
            ['Training Candidates', $candidates->count()],
            ['Non-Candidates', $nonCandidates->count()],
            ['Candidate Avg Confidence', number_format($candidates->avg('confidence_score') ?? 0, 3)],
            ['Non-Candidate Avg Confidence', number_format($nonCandidates->avg('confidence_score') ?? 0, 3)],
            ['Candidate Avg Completeness', number_format($candidates->avg('data_completeness_score') ?? 0, 1).'%'],
            ['Non-Candidate Avg Completeness', number_format($nonCandidates->avg('data_completeness_score') ?? 0, 1).'%'],
        ];

        $this->table(['Metric', 'Value'], $candidateData);

        // Show top candidates
        $topCandidates = $candidates->sortByDesc('confidence_score')->take(5);

        $this->info("\n🏆 Top Training Candidates:");
        $topData = $topCandidates->map(function ($candidate) {
            $courseName = $candidate->extracted_data['course_name'] ?? 'Unknown';

            return [
                $candidate->id,
                $courseName,
                number_format((float) $candidate->confidence_score, 3),
                $candidate->data_completeness_score.'%',
                $candidate->ocr_provider,
                $candidate->used_enhanced_prompt ? 'Yes' : 'No',
            ];
        })->toArray();

        $this->table(
            ['ID', 'Course', 'Confidence', 'Completeness', 'Provider', 'Enhanced'],
            $topData
        );
    }

    private function exportTrainingData(): void
    {
        $this->info("\n📤 Exporting Training Data...");

        // Build filters
        $filters = [];

        if ($this->option('verified-only')) {
            $filters['verified_only'] = true;
        }

        if ($this->option('min-confidence') > 0) {
            $filters['min_confidence'] = (float) $this->option('min-confidence');
        }

        if ($this->option('provider')) {
            $filters['ocr_provider'] = $this->option('provider');
        }

        if ($this->option('enhanced-only')) {
            $filters['enhanced_prompt_only'] = true;
        }

        // Export data
        $exportData = $this->trainingDataService->exportTrainingData($filters);

        if (empty($exportData)) {
            $this->warn('No data matches the export criteria');

            return;
        }

        $filename = 'training_data_'.date('Y-m-d_H-i-s').'.json';
        $filePath = 'exports/'.$filename;

        // Ensure exports directory exists
        Storage::disk('local')->makeDirectory('exports');

        // Save data
        $format = $this->option('format');

        if ($format === 'json') {
            Storage::disk('local')->put($filePath, json_encode($exportData, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            $csvPath = str_replace('.json', '.csv', $filePath);
            $this->exportToCsv($exportData, $csvPath);
            $filePath = $csvPath;
        }

        $fullPath = Storage::disk('local')->path($filePath);
        $this->info('✅ Exported '.count($exportData)." records to: {$fullPath}");

        // Show export summary
        $this->info("\n📋 Export Summary:");
        $this->line('   • Records exported: '.count($exportData));
        $this->line('   • Format: '.strtoupper($format));
        $this->line("   • File: {$filename}");

        if (! empty($filters)) {
            $this->line('   • Filters applied:');
            foreach ($filters as $key => $value) {
                $this->line("     - {$key}: {$value}");
            }
        }
    }

    private function exportToCsv(array $data, string $filePath): void
    {
        $csv = [];

        // Headers
        $headers = [
            'id', 'image_path', 'confidence_score', 'ocr_provider', 'used_enhanced_prompt',
            'course_name', 'tee_name', 'course_rating', 'slope_rating', 'total_par',
            'data_completeness', 'is_verified', 'created_at',
        ];
        $csv[] = $headers;

        // Data rows
        foreach ($data as $record) {
            $row = [
                $record['id'],
                $record['image_path'],
                $record['confidence_score'],
                $record['ocr_provider'],
                $record['used_enhanced_prompt'] ? '1' : '0',
                $record['extracted_data']['course_name'] ?? '',
                $record['extracted_data']['tee_name'] ?? '',
                $record['extracted_data']['course_rating'] ?? '',
                $record['extracted_data']['slope_rating'] ?? '',
                $record['extracted_data']['total_par'] ?? '',
                $record['quality_metrics']['data_completeness'] ?? '',
                $record['verified_data'] ? '1' : '0',
                $record['created_at'],
            ];
            $csv[] = $row;
        }

        // Convert to CSV string
        $csvContent = '';
        foreach ($csv as $row) {
            $csvContent .= '"'.implode('","', $row).'"'."\n";
        }

        Storage::disk('local')->put($filePath, $csvContent);
    }
}
