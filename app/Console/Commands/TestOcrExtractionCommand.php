<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ScorecardScanner\Services\OcrService;

class TestOcrExtractionCommand extends Command
{
    protected $signature = 'ocr:test 
                          {image1 : Path to first scorecard image}
                          {image2 : Path to second scorecard image}
                          {--provider=openrouter : OCR provider to use (openai, openrouter, ocrspace)}
                          {--output=table : Output format (table, json, csv, dump)}
                          {--save-results= : Save results to file}';

    protected $description = 'Test OCR extraction on two scorecard images and compare results';

    public function handle(): int
    {
        $image1Path = $this->argument('image1');
        $image2Path = $this->argument('image2');
        $provider = $this->option('provider');
        $outputFormat = $this->option('output');
        $saveResults = $this->option('save-results');

        $this->info("🔬 Testing OCR extraction with provider: {$provider}");

        // Validate provider (including mock for testing)
        $validProviders = ['openai', 'openrouter', 'ocrspace', 'mock'];
        if (! in_array($provider, $validProviders)) {
            $this->error("❌ Invalid provider: {$provider}. Valid providers: ".implode(', ', $validProviders));

            return self::FAILURE;
        }
        $this->newLine();

        // Validate image files exist
        if (! $this->validateImageFile($image1Path)) {
            return self::FAILURE;
        }

        if (! $this->validateImageFile($image2Path)) {
            return self::FAILURE;
        }

        // Set OCR provider and storage disk temporarily
        $originalProvider = config('scorecard-scanner.ocr.default');
        $originalDisk = config('scorecard-scanner.storage.disk');
        config(['scorecard-scanner.ocr.default' => $provider]);
        config(['scorecard-scanner.storage.disk' => 'public']);

        // Create OcrService AFTER setting the provider config
        $ocrService = new \ScorecardScanner\Services\OcrService;

        try {
            // Process first image
            $this->info("📷 Processing Image 1: {$image1Path}");
            $result1 = $this->processImage($ocrService, $image1Path, 'Image 1');

            $this->newLine();

            // Process second image
            $this->info("📷 Processing Image 2: {$image2Path}");
            $result2 = $this->processImage($ocrService, $image2Path, 'Image 2');

            // Display comparison
            $this->displayComparison($result1, $result2, $outputFormat);

            // Save results if requested
            if ($saveResults) {
                $this->saveResults($result1, $result2, $saveResults);
            }

        } catch (\Exception $e) {
            $this->error("❌ OCR processing failed: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            // Restore original configurations
            config(['scorecard-scanner.ocr.default' => $originalProvider]);
            config(['scorecard-scanner.storage.disk' => $originalDisk]);
        }

        return self::SUCCESS;
    }

    private function validateImageFile(string $path): bool
    {
        if (! file_exists($path)) {
            $this->error("❌ Image file not found: {$path}");

            return false;
        }

        $mimeType = mime_content_type($path);
        if (! str_starts_with($mimeType, 'image/')) {
            $this->error("❌ File is not an image: {$path} (type: {$mimeType})");

            return false;
        }

        $this->info("✅ Valid image file: {$path} ({$mimeType})");

        return true;
    }

    private function processImage(OcrService $ocrService, string $imagePath, string $label): array
    {
        $startTime = microtime(true);

        // Copy image to storage for processing
        $filename = basename($imagePath);
        $storagePath = "temp/{$filename}";

        Storage::disk('public')->put($storagePath, file_get_contents($imagePath));

        try {
            $result = $ocrService->extractText($storagePath);
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("✅ {$label} processed in {$processingTime}ms");
            $this->info('📊 Confidence: '.($result['confidence'] ?? 'N/A').'%');
            $this->info('🔧 Provider: '.($result['provider'] ?? 'N/A'));

            // Clean up temp file
            Storage::disk('public')->delete($storagePath);

            return array_merge($result, [
                'processing_time_ms' => $processingTime,
                'original_path' => $imagePath,
                'label' => $label,
            ]);

        } catch (\Exception $e) {
            Storage::disk('public')->delete($storagePath);
            throw $e;
        }
    }

    private function displayComparison(array $result1, array $result2, string $format): void
    {
        $this->newLine();
        $this->info('🔍 Extraction Comparison');
        $this->line(str_repeat('=', 80));

        switch ($format) {
            case 'json':
                $this->displayJsonGolfProperties($result1, $result2);
                break;
            case 'csv':
                $this->displayCsvGolfProperties($result1, $result2);
                break;
            case 'dump':
                $this->displayDumpComparison($result1, $result2);
                break;
            case 'table':
            default:
                $this->displayTableGolfProperties($result1, $result2);
                break;
        }
    }

    private function displayTableGolfProperties(array $result1, array $result2): void
    {
        // Processing Information
        $this->info('📊 Processing Information');
        $this->table(['Metric', 'Image 1', 'Image 2'], [
            ['Processing Time', $result1['processing_time_ms'].'ms', $result2['processing_time_ms'].'ms'],
            ['Confidence', ($result1['confidence'] ?? 'N/A').'%', ($result2['confidence'] ?? 'N/A').'%'],
            ['Provider', $result1['provider'] ?? 'N/A', $result2['provider'] ?? 'N/A'],
        ]);

        $props1 = $result1['golf_course_properties'] ?? [];
        $props2 = $result2['golf_course_properties'] ?? [];

        // Basic Course Information
        $this->newLine();
        $this->info('🏌️ Course Information');
        $this->table(['Property', 'Image 1', 'Image 2'], [
            ['Course Name', $props1['course_name'] ?? 'N/A', $props2['course_name'] ?? 'N/A'],
            ['Course Location', $props1['course_location'] ?? 'N/A', $props2['course_location'] ?? 'N/A'],
            ['Tee Name', $props1['tee_name'] ?? 'N/A', $props2['tee_name'] ?? 'N/A'],
            ['Designer', $props1['designer'] ?? 'N/A', $props2['designer'] ?? 'N/A'],
            ['Established Year', $props1['established_year'] ?? 'N/A', $props2['established_year'] ?? 'N/A'],
            ['Phone', $props1['phone'] ?? 'N/A', $props2['phone'] ?? 'N/A'],
            ['Website', $props1['website'] ?? 'N/A', $props2['website'] ?? 'N/A'],
        ]);

        // Course Ratings and Totals
        $this->newLine();
        $this->info('📏 Course Ratings & Totals');
        $this->table(['Property', 'Image 1', 'Image 2'], [
            ['Course Rating', $props1['course_rating'] ?? 'N/A', $props2['course_rating'] ?? 'N/A'],
            ['Slope Rating', $props1['slope_rating'] ?? 'N/A', $props2['slope_rating'] ?? 'N/A'],
            ['Total Par', $props1['total_par'] ?? 'N/A', $props2['total_par'] ?? 'N/A'],
            ['Total Yardage', $props1['total_yardage'] ?? 'N/A', $props2['total_yardage'] ?? 'N/A'],
            ['Front Nine Par', $props1['front_nine_par'] ?? 'N/A', $props2['front_nine_par'] ?? 'N/A'],
            ['Back Nine Par', $props1['back_nine_par'] ?? 'N/A', $props2['back_nine_par'] ?? 'N/A'],
            ['Front Nine Yardage', $props1['front_nine_yardage'] ?? 'N/A', $props2['front_nine_yardage'] ?? 'N/A'],
            ['Back Nine Yardage', $props1['back_nine_yardage'] ?? 'N/A', $props2['back_nine_yardage'] ?? 'N/A'],
        ]);

        // Hole-by-Hole Data
        $this->displayHoleByHoleTable($props1, $props2);

        // Round Information
        if (! empty($props1['players']) || ! empty($props2['players'])) {
            $this->newLine();
            $this->info('👥 Round Information');
            $this->table(['Property', 'Image 1', 'Image 2'], [
                ['Date Played', $props1['date_played'] ?? 'N/A', $props2['date_played'] ?? 'N/A'],
                ['Players', implode(', ', $props1['players'] ?? []) ?: 'N/A', implode(', ', $props2['players'] ?? []) ?: 'N/A'],
                ['Tournament', $props1['tournament_info'] ?? 'N/A', $props2['tournament_info'] ?? 'N/A'],
                ['Weather', $props1['weather_conditions'] ?? 'N/A', $props2['weather_conditions'] ?? 'N/A'],
            ]);
        }

        // Data Quality Metrics
        $this->newLine();
        $this->info('📈 Data Quality Metrics');
        $this->table(['Metric', 'Image 1', 'Image 2'], [
            ['Data Completeness', round(($props1['data_completeness'] ?? 0) * 100).'%', round(($props2['data_completeness'] ?? 0) * 100).'%'],
            ['Confidence Score', round(($props1['confidence_score'] ?? 0) * 100).'%', round(($props2['confidence_score'] ?? 0) * 100).'%'],
            ['Holes Found', count($props1['hole_details'] ?? []), count($props2['hole_details'] ?? [])],
            ['Missing Fields', implode(', ', $props1['missing_data_fields'] ?? []) ?: 'None', implode(', ', $props2['missing_data_fields'] ?? []) ?: 'None'],
        ]);
    }

    private function displayHoleByHoleTable(array $props1, array $props2): void
    {
        $holes1 = $props1['hole_details'] ?? [];
        $holes2 = $props2['hole_details'] ?? [];

        if (empty($holes1) && empty($holes2)) {
            return;
        }

        $this->newLine();
        $this->info('⛳ Hole-by-Hole Data');

        // Create combined hole data for comparison
        $maxHoles = max(count($holes1), count($holes2));
        $holeRows = [];

        for ($i = 0; $i < $maxHoles; $i++) {
            $hole1 = $holes1[$i] ?? null;
            $hole2 = $holes2[$i] ?? null;

            $holeNum = $hole1['number'] ?? $hole2['number'] ?? ($i + 1);

            $holeRows[] = [
                'Hole '.$holeNum,
                $this->formatHoleData($hole1),
                $this->formatHoleData($hole2),
            ];
        }

        $this->table(['Hole', 'Image 1 (Par/Hdcp/Yds)', 'Image 2 (Par/Hdcp/Yds)'], $holeRows);
    }

    private function formatHoleData(?array $hole): string
    {
        if (! $hole) {
            return 'N/A';
        }

        $par = $hole['par'] ?? 'N/A';
        $handicap = $hole['handicap'] ?? 'N/A';
        $yardage = $hole['yardage'] ?? 'N/A';

        return "{$par}/{$handicap}/{$yardage}";
    }

    private function displayJsonGolfProperties(array $result1, array $result2): void
    {
        $golfData = [
            'extraction_summary' => [
                'timestamp' => now()->toISOString(),
                'processing_time_ms' => [
                    'image_1' => $result1['processing_time_ms'],
                    'image_2' => $result2['processing_time_ms'],
                ],
                'confidence' => [
                    'image_1' => $result1['confidence'] ?? 'N/A',
                    'image_2' => $result2['confidence'] ?? 'N/A',
                ],
            ],
            'golf_course_properties' => [
                'image_1' => $this->extractGolfProperties($result1),
                'image_2' => $this->extractGolfProperties($result2),
            ],
            'comparison' => $this->compareGolfProperties(
                $this->extractGolfProperties($result1),
                $this->extractGolfProperties($result2)
            ),
        ];

        $this->line(json_encode($golfData, JSON_PRETTY_PRINT));
    }

    private function displayCsvGolfProperties(array $result1, array $result2): void
    {
        $props1 = $this->extractGolfProperties($result1);
        $props2 = $this->extractGolfProperties($result2);

        // CSV Header
        $csvData = [];
        $csvData[] = [
            'Property', 'Image_1', 'Image_2', 'Match',
        ];

        // Basic course information
        $basicProperties = [
            'course_name' => 'Course Name',
            'course_location' => 'Course Location',
            'tee_name' => 'Tee Name',
            'designer' => 'Designer',
            'established_year' => 'Established Year',
            'phone' => 'Phone',
            'website' => 'Website',
            'course_rating' => 'Course Rating',
            'slope_rating' => 'Slope Rating',
            'total_par' => 'Total Par',
            'total_yardage' => 'Total Yardage',
            'front_nine_par' => 'Front Nine Par',
            'back_nine_par' => 'Back Nine Par',
            'front_nine_yardage' => 'Front Nine Yardage',
            'back_nine_yardage' => 'Back Nine Yardage',
            'date_played' => 'Date Played',
            'tournament_info' => 'Tournament',
            'weather_conditions' => 'Weather',
            'data_completeness' => 'Data Completeness %',
            'confidence_score' => 'Confidence Score %',
        ];

        foreach ($basicProperties as $key => $label) {
            $val1 = $props1[$key] ?? 'N/A';
            $val2 = $props2[$key] ?? 'N/A';
            $match = ($val1 === $val2) ? 'Yes' : 'No';

            // Format percentages
            if (in_array($key, ['data_completeness', 'confidence_score'])) {
                $val1 = is_numeric($val1) ? round($val1 * 100).'%' : $val1;
                $val2 = is_numeric($val2) ? round($val2 * 100).'%' : $val2;
            }

            $csvData[] = [$label, $val1, $val2, $match];
        }

        // Players
        $players1 = implode('; ', $props1['players'] ?? []) ?: 'N/A';
        $players2 = implode('; ', $props2['players'] ?? []) ?: 'N/A';
        $csvData[] = ['Players', $players1, $players2, ($players1 === $players2) ? 'Yes' : 'No'];

        // Hole-by-hole data
        $holes1 = $props1['hole_details'] ?? [];
        $holes2 = $props2['hole_details'] ?? [];
        $maxHoles = max(count($holes1), count($holes2));

        for ($i = 0; $i < $maxHoles; $i++) {
            $hole1 = $holes1[$i] ?? null;
            $hole2 = $holes2[$i] ?? null;

            $holeNum = $hole1['number'] ?? $hole2['number'] ?? ($i + 1);
            $data1 = $this->formatHoleData($hole1);
            $data2 = $this->formatHoleData($hole2);

            $csvData[] = ["Hole {$holeNum} (Par/Hdcp/Yds)", $data1, $data2, ($data1 === $data2) ? 'Yes' : 'No'];
        }

        // Output CSV
        foreach ($csvData as $row) {
            $this->line(implode(',', array_map(function ($field) {
                return '"'.str_replace('"', '""', $field).'"';
            }, $row)));
        }
    }

    private function extractGolfProperties(array $result): array
    {
        return $result['golf_course_properties'] ?? [];
    }

    private function compareGolfProperties(array $props1, array $props2): array
    {
        $comparison = [
            'matches' => [],
            'differences' => [],
            'missing_in_image1' => [],
            'missing_in_image2' => [],
        ];

        $allKeys = array_unique(array_merge(array_keys($props1), array_keys($props2)));

        foreach ($allKeys as $key) {
            $val1 = $props1[$key] ?? null;
            $val2 = $props2[$key] ?? null;

            if ($val1 === null && $val2 !== null) {
                $comparison['missing_in_image1'][] = $key;
            } elseif ($val2 === null && $val1 !== null) {
                $comparison['missing_in_image2'][] = $key;
            } elseif ($val1 === $val2) {
                $comparison['matches'][] = $key;
            } else {
                $comparison['differences'][$key] = [
                    'image_1' => $val1,
                    'image_2' => $val2,
                ];
            }
        }

        return $comparison;
    }

    private function displayJsonComparison(array $result1, array $result2): void
    {
        $comparison = [
            'image_1' => $this->sanitizeResultForJson($result1),
            'image_2' => $this->sanitizeResultForJson($result2),
            'comparison_summary' => [
                'processing_time_diff_ms' => $result2['processing_time_ms'] - $result1['processing_time_ms'],
                'confidence_diff' => $result2['confidence'] - $result1['confidence'],
                'text_length_diff' => strlen($result2['text'] ?? $result2['raw_text'] ?? '') - strlen($result1['text'] ?? $result1['raw_text'] ?? ''),
            ],
        ];

        $this->line(json_encode($comparison, JSON_PRETTY_PRINT));
    }

    private function displayDumpComparison(array $result1, array $result2): void
    {
        $this->info('🔍 Image 1 Results:');
        dump($result1);

        $this->newLine();
        $this->info('🔍 Image 2 Results:');
        dump($result2);
    }

    private function sanitizeResultForJson(array $result): array
    {
        // Remove binary data and large arrays for cleaner JSON output
        unset($result['words']);
        unset($result['lines']);

        return $result;
    }

    private function saveResults(array $result1, array $result2, string $filename): void
    {
        $data = [
            'timestamp' => now()->toISOString(),
            'image_1' => $result1,
            'image_2' => $result2,
            'comparison' => [
                'processing_time_diff_ms' => $result2['processing_time_ms'] - $result1['processing_time_ms'],
                'confidence_diff' => $result2['confidence'] - $result1['confidence'],
                'text_length_diff' => strlen($result2['text'] ?? $result2['raw_text'] ?? '') - strlen($result1['text'] ?? $result1['raw_text'] ?? ''),
            ],
        ];

        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
        $this->info("💾 Results saved to: {$filename}");
    }
}
