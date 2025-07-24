<?php

declare(strict_types=1);

namespace ScorecardScanner\Services;

use Illuminate\Http\UploadedFile;
use ScorecardScanner\Models\Course;
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Models\UnverifiedCourse;

class ScorecardProcessingService
{
    public function __construct(
        private ImageProcessingService $imageProcessor,
        private OcrService $ocrService
    ) {}

    public function processUploadedImage(UploadedFile $image, int $userId): ScorecardScan
    {
        $scan = $this->createScorecardScan($image, $userId);

        try {
            $this->processImage($scan);

            return $scan;
        } catch (\Exception $e) {
            $scan->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function createScorecardScan(UploadedFile $image, int $userId): ScorecardScan
    {
        $originalPath = $image->store('scorecard-scans/originals', 'public');

        return ScorecardScan::create([
            'user_id' => $userId,
            'original_image_path' => $originalPath,
            'status' => 'processing',
        ]);
    }

    private function processImage(ScorecardScan $scan): void
    {
        // Step 1: Image preprocessing
        $processedImagePath = $this->imageProcessor->preprocessImage($scan->original_image_path);
        $scan->update(['processed_image_path' => $processedImagePath]);

        // Step 2: OCR extraction
        $ocrData = $this->ocrService->extractText($processedImagePath);
        $scan->update(['raw_ocr_data' => $ocrData]);

        // Step 3: Parse structured data
        $parsedData = $this->parseOcrData($ocrData);
        $confidenceScores = $this->calculateConfidenceScores($ocrData, $parsedData);

        $scan->update([
            'parsed_data' => $parsedData,
            'confidence_scores' => $confidenceScores,
            'status' => 'completed',
        ]);

        // Step 4: Handle course database population
        $this->handleCourseData($parsedData);
    }

    /**
     * @param  array<string, mixed>  $ocrData
     * @return array<string, mixed>
     */
    private function parseOcrData(array $ocrData): array
    {
        // This would contain the logic to parse the OCR data into structured format
        // For now, returning a mock structure
        return [
            'course_name' => $this->extractCourseName($ocrData),
            'date' => $this->extractDate($ocrData),
            'tee_name' => $this->extractTeeName($ocrData),
            'players' => $this->extractPlayers($ocrData),
            'holes' => $this->extractHoleData($ocrData),
            'totals' => $this->calculateTotals($ocrData),
            'slope' => $this->extractSlope($ocrData),
            'rating' => $this->extractRating($ocrData),
        ];
    }

    /**
     * @param  array<string, mixed>  $ocrData
     * @param  array<string, mixed>  $parsedData
     * @return array<string, mixed>
     */
    private function calculateConfidenceScores(array $ocrData, array $parsedData): array
    {
        // Calculate confidence scores for each parsed field
        return [
            'course_name' => 0.95,
            'date' => 0.88,
            'tee_name' => 0.92,
            'players' => [
                'player_1' => 0.89,
                'player_2' => 0.91,
            ],
            'hole_scores' => array_fill(1, 18, 0.87),
            'totals' => 0.94,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedData
     */
    private function handleCourseData(array $parsedData): void
    {
        $courseName = $parsedData['course_name'];
        $teeName = $parsedData['tee_name'];

        // Check if course exists in verified database
        $existingCourse = Course::where('name', $courseName)
            ->where('tee_name', $teeName)
            ->first();

        if (! $existingCourse) {
            // Add to unverified courses or increment count
            $courseData = [
                'name' => $courseName,
                'tee_name' => $teeName,
                'par_values' => $this->extractParValues($parsedData),
                'handicap_values' => $this->extractHandicapValues($parsedData),
                'slope' => $parsedData['slope'] ?? null,
                'rating' => $parsedData['rating'] ?? null,
            ];

            UnverifiedCourse::findOrCreateSimilar($courseData);
        }
    }

    /**
     * @param  array<string, mixed>  $ocrData
     */
    private function extractCourseName(array $ocrData): ?string
    {
        // Mock implementation - would use pattern matching on OCR text
        return 'Pebble Beach Golf Links';
    }

    /**
     * @param  array<string, mixed>  $ocrData
     */
    private function extractDate(array $ocrData): ?string
    {
        // Mock implementation
        return '2024-07-24';
    }

    /**
     * @param  array<string, mixed>  $ocrData
     */
    private function extractTeeName(array $ocrData): ?string
    {
        // Mock implementation
        return 'Championship';
    }

    /**
     * @param  array<string, mixed>  $ocrData
     * @return array<int, string>
     */
    private function extractPlayers(array $ocrData): array
    {
        // Mock implementation
        return [
            'Player 1',
            'Player 2',
        ];
    }

    /**
     * @param  array<string, mixed>  $ocrData
     * @return array<int, array<string, mixed>>
     */
    private function extractHoleData(array $ocrData): array
    {
        // Mock implementation - would extract hole-by-hole data
        $holes = [];
        for ($i = 1; $i <= 18; $i++) {
            $holes[$i] = [
                'par' => rand(3, 5),
                'handicap' => $i,
                'scores' => [
                    'Player 1' => rand(3, 7),
                    'Player 2' => rand(3, 7),
                ],
            ];
        }

        return $holes;
    }

    /**
     * @param  array<string, mixed>  $ocrData
     * @return array<string, array<string, int>>
     */
    private function calculateTotals(array $ocrData): array
    {
        // Mock implementation
        return [
            'Player 1' => [
                'out' => 42,
                'in' => 41,
                'total' => 83,
            ],
            'Player 2' => [
                'out' => 45,
                'in' => 44,
                'total' => 89,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $ocrData
     */
    private function extractSlope(array $ocrData): int
    {
        return 113;
    }

    /**
     * @param  array<string, mixed>  $ocrData
     */
    private function extractRating(array $ocrData): float
    {
        return 72.1;
    }

    /**
     * @param  array<string, mixed>  $parsedData
     * @return array<int, int>
     */
    private function extractParValues(array $parsedData): array
    {
        $parValues = [];
        if (isset($parsedData['holes']) && is_array($parsedData['holes'])) {
            foreach ($parsedData['holes'] as $hole) {
                if (is_array($hole) && isset($hole['par'])) {
                    $parValues[] = (int) $hole['par'];
                }
            }
        }

        return $parValues;
    }

    /**
     * @param  array<string, mixed>  $parsedData
     * @return array<int, int>
     */
    private function extractHandicapValues(array $parsedData): array
    {
        $handicapValues = [];
        if (isset($parsedData['holes']) && is_array($parsedData['holes'])) {
            foreach ($parsedData['holes'] as $hole) {
                if (is_array($hole) && isset($hole['handicap'])) {
                    $handicapValues[] = (int) $hole['handicap'];
                }
            }
        }

        return $handicapValues;
    }
}
