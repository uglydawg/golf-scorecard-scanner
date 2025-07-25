<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ScorecardScanner\Services\ImageProcessingService;
use ScorecardScanner\Services\OcrService;

class ProcessScorecardWorkflowCommand extends Command
{
    protected $signature = 'scorecard:process 
                          {image1 : Path to first scorecard image}
                          {image2 : Path to second scorecard image}
                          {--user-id=1 : User ID to associate with the scans}
                          {--enhanced : Use enhanced OCR prompt}
                          {--provider=openrouter : OCR provider to use}';

    protected $description = 'Process two scorecard images through the complete workflow and store results in scorecard_scans table';

    private ImageProcessingService $imageService;

    private OcrService $ocrService;

    public function __construct(ImageProcessingService $imageService, OcrService $ocrService)
    {
        parent::__construct();
        $this->imageService = $imageService;
        $this->ocrService = $ocrService;
    }

    public function handle(): int
    {
        $image1Path = $this->argument('image1');
        $image2Path = $this->argument('image2');
        $userId = (int) $this->option('user-id');
        $useEnhanced = $this->option('enhanced');
        $provider = $this->option('provider');

        $this->info('🏌️ Golf Scorecard Processing Workflow');
        $this->info('=====================================');

        // Validate input files
        if (! file_exists($image1Path)) {
            $this->error("Image 1 not found: {$image1Path}");

            return 1;
        }

        if (! file_exists($image2Path)) {
            $this->error("Image 2 not found: {$image2Path}");

            return 1;
        }

        // Configure OCR provider and enhanced mode
        if ($useEnhanced) {
            config(['scorecard-scanner.ocr.enhanced_prompt_enabled' => true]);
            $this->info('📈 Enhanced OCR mode enabled');
        }

        config(['scorecard-scanner.ocr.default' => $provider]);
        $this->info("🔧 OCR Provider: {$provider}");

        // Process both images
        $results = [];
        $images = [
            ['path' => $image1Path, 'name' => basename($image1Path)],
            ['path' => $image2Path, 'name' => basename($image2Path)],
        ];

        foreach ($images as $index => $image) {
            $this->info("\n".str_repeat('=', 60));
            $this->info('📷 Processing Image '.($index + 1).": {$image['name']}");
            $this->info(str_repeat('=', 60));

            try {
                $result = $this->processImage($image['path'], $image['name'], $userId);
                $results[] = $result;

                $this->info("✅ Successfully processed {$image['name']}");
                $this->displayResults($result);

            } catch (\Exception $e) {
                $this->error("❌ Failed to process {$image['name']}: ".$e->getMessage());
                $results[] = ['error' => $e->getMessage(), 'image' => $image['name']];
            }
        }

        // Summary
        $this->displaySummary($results);

        return 0;
    }

    private function processImage(string $imagePath, string $imageName, int $userId): array
    {
        $this->info("📁 File: {$imageName}");
        $this->info('📊 Size: '.number_format(filesize($imagePath) / 1024, 2).' KB');

        // Step 1: Store original image
        $this->line('🔄 Step 1: Storing original image...');
        $originalStoredPath = $this->storeOriginalImage($imagePath, $imageName);
        $this->info("   ✅ Stored at: {$originalStoredPath}");

        // Step 2: Image preprocessing
        $this->line('🔄 Step 2: Image preprocessing...');
        $processedPath = $this->imageService->preprocessImage($originalStoredPath);
        $this->info("   ✅ Preprocessed: {$processedPath}");

        // Step 3: OCR processing
        $this->line('🔄 Step 3: OCR processing...');
        $ocrResults = $this->ocrService->extractText($originalStoredPath);
        $confidence = is_array($ocrResults['confidence']) ?
            max($ocrResults['confidence']) :
            (is_numeric($ocrResults['confidence']) ? $ocrResults['confidence'] : 0);

        $this->info('   ✅ OCR completed with '.number_format($confidence, 1).'% confidence');

        // Step 4: Store in database
        $this->line('🔄 Step 4: Storing scan record...');
        $scanRecord = $this->storeScanRecord($userId, $originalStoredPath, $processedPath, $ocrResults);
        $this->info("   ✅ Scan ID: {$scanRecord['id']}");

        return [
            'scan_id' => $scanRecord['id'],
            'image_name' => $imageName,
            'original_path' => $originalStoredPath,
            'processed_path' => $processedPath,
            'confidence' => $confidence,
            'ocr_results' => $ocrResults,
            'status' => $scanRecord['status'],
        ];
    }

    private function storeOriginalImage(string $imagePath, string $imageName): string
    {
        $imageContent = file_get_contents($imagePath);
        $storedPath = 'scorecards/originals/'.date('Y/m/d/').time().'_'.$imageName;

        Storage::disk('local')->put($storedPath, $imageContent);

        return $storedPath;
    }

    private function storeScanRecord(int $userId, string $originalPath, string $processedPath, array $ocrResults): array
    {
        // Determine status based on OCR results
        $status = 'completed';
        $errorMessage = null;

        if (isset($ocrResults['error'])) {
            $status = 'failed';
            $errorMessage = $ocrResults['error'];
        } elseif (empty($ocrResults['raw_text'])) {
            $status = 'failed';
            $errorMessage = 'No text extracted from image';
        }

        // Prepare confidence scores
        $confidenceScores = [];
        if (isset($ocrResults['confidence'])) {
            $confidenceScores['overall'] = $ocrResults['confidence'];
        }
        if (isset($ocrResults['words'])) {
            $confidenceScores['words'] = count($ocrResults['words']);
        }
        if (isset($ocrResults['structured_data']['overall_confidence'])) {
            $confidenceScores['enhanced'] = $ocrResults['structured_data']['overall_confidence'];
        }

        // Prepare parsed data (extract golf course properties if available)
        $parsedData = [];
        if (isset($ocrResults['golf_course_properties'])) {
            $parsedData = $ocrResults['golf_course_properties'];
        }
        if (isset($ocrResults['structured_data'])) {
            $parsedData['enhanced_data'] = $ocrResults['structured_data'];
        }

        $scanData = [
            'user_id' => $userId,
            'original_image_path' => $originalPath,
            'processed_image_path' => $processedPath,
            'raw_ocr_data' => json_encode($ocrResults),
            'parsed_data' => json_encode($parsedData),
            'confidence_scores' => json_encode($confidenceScores),
            'status' => $status,
            'error_message' => $errorMessage,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $scanId = DB::table('scorecard_scans')->insertGetId($scanData);

        return array_merge($scanData, ['id' => $scanId]);
    }

    private function displayResults(array $result): void
    {
        if (isset($result['error'])) {
            $this->error("❌ Error: {$result['error']}");

            return;
        }

        $this->table(
            ['Property', 'Value'],
            [
                ['Scan ID', $result['scan_id']],
                ['Status', $result['status']],
                ['Confidence', number_format($result['confidence'], 1).'%'],
                ['Original Path', $result['original_path']],
                ['Processed Path', $result['processed_path']],
            ]
        );

        // Display golf course data if available
        if (isset($result['ocr_results']['golf_course_properties'])) {
            $this->displayGolfCourseData($result['ocr_results']['golf_course_properties']);
        }

        // Display enhanced data if available
        if (isset($result['ocr_results']['structured_data']) && isset($result['ocr_results']['enhanced_format'])) {
            $this->displayEnhancedData($result['ocr_results']['structured_data']);
        }
    }

    private function displayGolfCourseData(array $properties): void
    {
        $this->info("\n⛳ Golf Course Data Extracted:");

        $courseData = [];
        if (! empty($properties['course_name'])) {
            $courseData[] = ['Course Name', $properties['course_name']];
        }
        if (! empty($properties['course_location'])) {
            $courseData[] = ['Location', $properties['course_location']];
        }
        if (! empty($properties['tee_name'])) {
            $courseData[] = ['Tee Name', $properties['tee_name']];
        }
        if (! empty($properties['course_rating'])) {
            $courseData[] = ['Course Rating', $properties['course_rating']];
        }
        if (! empty($properties['slope_rating'])) {
            $courseData[] = ['Slope Rating', $properties['slope_rating']];
        }
        if (! empty($properties['total_par'])) {
            $courseData[] = ['Total Par', $properties['total_par']];
        }
        if (! empty($properties['total_yardage'])) {
            $courseData[] = ['Total Yardage', $properties['total_yardage']];
        }

        if (! empty($courseData)) {
            $this->table(['Property', 'Value'], $courseData);
        }

        // Display players if found
        if (! empty($properties['players'])) {
            $this->info("\n👥 Players Found:");
            foreach ($properties['players'] as $index => $player) {
                $this->line('  '.($index + 1).". {$player}");
            }
        }

        // Display par values if available
        if (! empty($properties['par_values']) && is_array($properties['par_values'])) {
            $this->info("\n🏌️ Par Values: ".implode(', ', array_slice($properties['par_values'], 0, 9)));
            if (count($properties['par_values']) > 9) {
                $this->line('              '.implode(', ', array_slice($properties['par_values'], 9)));
            }
        }
    }

    private function displayEnhancedData(array $structuredData): void
    {
        $this->info("\n🚀 Enhanced OCR Data:");

        // Course information
        if (isset($structuredData['course_information'])) {
            $courseInfo = $structuredData['course_information'];
            $this->line('📍 Course: '.($courseInfo['course_name'] ?? 'Unknown'));
            if (isset($courseInfo['location'])) {
                $location = is_array($courseInfo['location'])
                    ? implode(', ', array_filter($courseInfo['location']))
                    : $courseInfo['location'];
                $this->line("🌍 Location: {$location}");
            }
            if (isset($courseInfo['confidence'])) {
                $this->line('✅ Confidence: '.number_format($courseInfo['confidence'] * 100, 1).'%');
            }
        }

        // Tee box information
        if (isset($structuredData['tee_boxes']) && is_array($structuredData['tee_boxes'])) {
            $this->info("\n🎯 Tee Boxes Found: ".count($structuredData['tee_boxes']));
            foreach ($structuredData['tee_boxes'] as $index => $teeBox) {
                $this->line('  '.($index + 1).'. '.($teeBox['tee_name'] ?? 'Unknown').' Tees');
                if (isset($teeBox['course_rating'])) {
                    $this->line("     Rating: {$teeBox['course_rating']} / Slope: ".($teeBox['slope_rating'] ?? 'N/A'));
                }
                if (isset($teeBox['par_values']) && is_array($teeBox['par_values'])) {
                    $this->line('     Par: '.implode('-', array_slice($teeBox['par_values'], 0, 9)));
                }
            }
        }

        // Player scores
        if (isset($structuredData['player_scores']) && is_array($structuredData['player_scores'])) {
            $this->info("\n📊 Player Scores Found: ".count($structuredData['player_scores']));
            foreach ($structuredData['player_scores'] as $player) {
                $name = $player['player_name'] ?? 'Unknown Player';
                $total = $player['total_score'] ?? 'N/A';
                $this->line("  • {$name}: {$total}");
            }
        }
    }

    private function displaySummary(array $results): void
    {
        $this->info("\n".str_repeat('=', 60));
        $this->info('📋 PROCESSING SUMMARY');
        $this->info(str_repeat('=', 60));

        $successful = 0;
        $failed = 0;
        $scanIds = [];

        foreach ($results as $result) {
            if (isset($result['error'])) {
                $failed++;
                $this->line("❌ {$result['image']}: {$result['error']}");
            } else {
                $successful++;
                $scanIds[] = $result['scan_id'];
                $this->line("✅ {$result['image_name']}: Scan ID {$result['scan_id']} ({$result['status']})");
            }
        }

        $this->info("\n📊 Results:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Successful', $successful],
                ['Failed', $failed],
                ['Total', count($results)],
            ]
        );

        if (! empty($scanIds)) {
            $this->info('🔍 Database Records Created:');
            $this->line('   Scan IDs: '.implode(', ', $scanIds));
            $this->line('');
            $this->line('📝 To view stored data:');
            foreach ($scanIds as $scanId) {
                $this->line("   php artisan tinker -c \"DB::table('scorecard_scans')->find({$scanId})\"");
            }
        }

        $this->info("\n✅ Workflow completed!");
    }
}
