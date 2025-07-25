<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Services\CourseDataService;

class ConvertScansToCoursesCommand extends Command
{
    protected $signature = 'scorecard:convert-to-courses 
                          {--scan-id=* : Specific scan IDs to process}
                          {--all : Process all scans}
                          {--dry-run : Show what would be processed without making changes}
                          {--confidence=0.85 : Minimum confidence threshold}
                          {--completeness=70 : Minimum data completeness percentage}';

    protected $description = 'Convert scorecard scan results into golf course and round records';

    private CourseDataService $courseDataService;

    public function __construct(CourseDataService $courseDataService)
    {
        parent::__construct();
        $this->courseDataService = $courseDataService;
    }

    public function handle(): int
    {
        $this->info('🏌️ Golf Course Data Conversion Utility');
        $this->info('========================================');

        // Configure service with command options
        $confidence = (float) $this->option('confidence');
        $completeness = (int) $this->option('completeness');

        $this->courseDataService = new CourseDataService($confidence, $completeness);

        $this->info('📊 Configuration:');
        $this->line("   Confidence Threshold: {$confidence}");
        $this->line("   Data Completeness: {$completeness}%");
        $this->line('   Dry Run: '.($this->option('dry-run') ? 'Yes' : 'No'));

        // Get scans to process
        $scans = $this->getScansToProcess();

        if ($scans->isEmpty()) {
            $this->warn('No scans found to process');

            return 0;
        }

        $this->info("\n📋 Found ".$scans->count().' scans to process');

        // Process each scan
        $results = [
            'processed' => 0,
            'courses_created' => 0,
            'rounds_created' => 0,
            'scores_created' => 0,
            'errors' => 0,
            'unverified_courses' => 0,
        ];

        foreach ($scans as $scan) {
            $this->info("\n".str_repeat('=', 60));
            $this->info("📷 Processing Scan ID: {$scan->id}");
            $this->info(str_repeat('=', 60));

            try {
                $result = $this->processScan($scan);
                $this->displayScanResult($scan, $result);

                // Update totals
                $results['processed']++;
                if ($result['course_created']) {
                    $results['courses_created']++;
                }
                if ($result['round_created']) {
                    $results['rounds_created']++;
                }
                $results['scores_created'] += $result['scores_created'];
                if (! empty($result['errors'])) {
                    $results['errors']++;
                }
                if (in_array('Course added to unverified database for admin review', $result['warnings'] ?? [])) {
                    $results['unverified_courses']++;
                }

            } catch (\Exception $e) {
                $this->error("❌ Failed to process scan {$scan->id}: ".$e->getMessage());
                $results['errors']++;
            }
        }

        // Display final summary
        $this->displayFinalSummary($results);

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
                    // Handle comma-separated values in single option
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
            ->get(['id', 'created_at', 'parsed_data', 'raw_ocr_data']);

        if ($availableScans->isEmpty()) {
            return collect();
        }

        $tableData = [];
        foreach ($availableScans as $scan) {
            $courseName = $this->extractCourseName($scan);
            $tableData[] = [
                $scan->id,
                $scan->created_at->format('Y-m-d H:i'),
                $courseName ?: 'Unknown Course',
            ];
        }

        $this->table(['ID', 'Created', 'Course Name'], $tableData);

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

    private function processScan(ScorecardScan $scan): array
    {
        $this->line("📊 Scan Status: {$scan->status}");
        $this->line("👤 User ID: {$scan->user_id}");
        $this->line('📅 Created: '.$scan->created_at->format('Y-m-d H:i:s'));

        // Extract and display course info
        $courseName = $this->extractCourseName($scan);
        $teeName = $this->extractTeeName($scan);
        $players = $this->extractPlayers($scan);

        $this->line("🏌️ Course: {$courseName}");
        $this->line("🎯 Tee: {$teeName}");
        $this->line('👥 Players: '.(count($players) > 0 ? implode(', ', $players) : 'None found'));

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN - No changes will be made');

            return [
                'course_created' => false,
                'course_id' => null,
                'round_created' => false,
                'round_id' => null,
                'scores_created' => 0,
                'errors' => [],
                'warnings' => ['Dry run mode - no changes made'],
            ];
        }

        // Process the scan
        return $this->courseDataService->processScanToCourseData($scan);
    }

    private function displayScanResult(ScorecardScan $scan, array $result): void
    {
        $this->info("\n📊 Processing Results:");

        $resultData = [];

        if ($result['course_created']) {
            $resultData[] = ['Course Created', "✅ Yes (ID: {$result['course_id']})"];
        } elseif ($result['course_id']) {
            $resultData[] = ['Course Matched', "✅ Existing (ID: {$result['course_id']})"];
        } else {
            $resultData[] = ['Course', '❌ Not created'];
        }

        if ($result['round_created']) {
            $resultData[] = ['Round Created', "✅ Yes (ID: {$result['round_id']})"];
        } else {
            $resultData[] = ['Round Created', '❌ No'];
        }

        $resultData[] = ['Hole Scores', $result['scores_created'].' created'];

        $this->table(['Result', 'Status'], $resultData);

        // Display warnings
        if (! empty($result['warnings'])) {
            $this->warn('⚠️  Warnings:');
            foreach ($result['warnings'] as $warning) {
                $this->line("   • {$warning}");
            }
        }

        // Display errors
        if (! empty($result['errors'])) {
            $this->error('❌ Errors:');
            foreach ($result['errors'] as $error) {
                $this->line("   • {$error}");
            }
        }
    }

    private function displayFinalSummary(array $results): void
    {
        $this->info("\n".str_repeat('=', 60));
        $this->info('📋 CONVERSION SUMMARY');
        $this->info(str_repeat('=', 60));

        $summaryData = [
            ['Scans Processed', $results['processed']],
            ['Courses Created', $results['courses_created']],
            ['Rounds Created', $results['rounds_created']],
            ['Hole Scores Created', $results['scores_created']],
            ['Unverified Courses', $results['unverified_courses']],
            ['Errors', $results['errors']],
        ];

        $this->table(['Metric', 'Count'], $summaryData);

        // Additional information
        if ($results['unverified_courses'] > 0) {
            $this->info("\n📝 Next Steps:");
            $this->line('   • Review unverified courses in admin panel');
            $this->line('   • Approve high-quality course submissions');
            $this->line('   • Re-run conversion after course approval');
        }

        if ($results['courses_created'] > 0 || $results['rounds_created'] > 0) {
            $this->info("\n🔍 Database Queries to Explore Results:");

            if ($results['courses_created'] > 0) {
                $this->line('   • Recent Courses: SELECT * FROM courses ORDER BY created_at DESC LIMIT 5;');
            }

            if ($results['rounds_created'] > 0) {
                $this->line('   • Recent Rounds: SELECT * FROM rounds ORDER BY created_at DESC LIMIT 5;');
                $this->line('   • Round Scores: SELECT * FROM round_scores ORDER BY created_at DESC LIMIT 10;');
            }
        }

        $this->info("\n✅ Conversion completed!");
    }

    // Helper methods to extract data from scans
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

    private function extractTeeName(ScorecardScan $scan): string
    {
        $parsedData = $scan->parsed_data ?? [];
        $rawData = $scan->raw_ocr_data ?? [];

        return $parsedData['tee_name']
            ?? $parsedData['enhanced_data']['tee_name']
            ?? $rawData['golf_course_properties']['tee_name']
            ?? $rawData['structured_data']['tee_name']
            ?? 'Unknown Tee';
    }

    private function extractPlayers(ScorecardScan $scan): array
    {
        $parsedData = $scan->parsed_data ?? [];
        $rawData = $scan->raw_ocr_data ?? [];

        return $parsedData['players']
            ?? $parsedData['enhanced_data']['players']
            ?? $rawData['golf_course_properties']['players']
            ?? $rawData['structured_data']['players']
            ?? [];
    }
}
