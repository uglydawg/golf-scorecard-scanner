<?php

declare(strict_types=1);

namespace ScorecardScanner\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ScorecardScanner\Models\ScanTrainingData;
use ScorecardScanner\Models\ScorecardScan;

class TrainingDataService
{
    public function __construct(
        private string $modelVersion = '1.0.0'
    ) {}

    /**
     * Save training data from a completed scan
     *
     * @param  array<string, mixed>  $rawOcrResponse
     * @param  array<string, mixed>  $extractedData
     * @param  array<string, mixed>  $processingMetadata
     */
    public function saveFromScan(
        ScorecardScan $scan,
        array $rawOcrResponse,
        array $extractedData,
        array $processingMetadata = []
    ): ScanTrainingData {
        $startTime = microtime(true);

        // Calculate processing metrics
        $confidence = $this->calculateOverallConfidence($rawOcrResponse);
        $completeness = $this->calculateDataCompleteness($extractedData);
        $fieldConfidences = $this->extractFieldConfidences($rawOcrResponse, $extractedData);
        $validationErrors = $this->validateGolfData($extractedData);

        $trainingData = ScanTrainingData::create([
            'scorecard_scan_id' => $scan->id,
            'original_image_path' => $scan->original_image_path,
            'processed_image_path' => $scan->processed_image_path,
            'raw_ocr_response' => $rawOcrResponse,
            'extracted_data' => $extractedData,
            'confidence_score' => $confidence,
            'ocr_provider' => $rawOcrResponse['provider'] ?? 'unknown',
            'used_enhanced_prompt' => $rawOcrResponse['enhanced_format'] ?? false,
            'processing_metadata' => array_merge($processingMetadata, [
                'image_size' => $this->getImageSize($scan->original_image_path),
                'preprocessing_steps' => $this->getPreprocessingSteps($processingMetadata),
            ]),
            'data_completeness_score' => $completeness,
            'field_confidence_scores' => $fieldConfidences,
            'validation_errors' => $validationErrors,
            'processing_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            'model_version' => $this->modelVersion,
            'processed_at' => now(),
            'is_training_candidate' => $this->isTrainingCandidate($confidence, $completeness, $validationErrors),
        ]);

        Log::info('Training data saved for scan', [
            'scan_id' => $scan->id,
            'training_data_id' => $trainingData->id,
            'confidence' => $confidence,
            'completeness' => $completeness,
            'is_candidate' => $trainingData->is_training_candidate,
        ]);

        return $trainingData;
    }

    /**
     * Calculate overall confidence score from OCR response
     */
    /**
     * @param  array<string, mixed>  $ocrResponse
     */
    private function calculateOverallConfidence(array $ocrResponse): float
    {
        // Check for explicit confidence in response
        if (isset($ocrResponse['confidence'])) {
            $confidence = $ocrResponse['confidence'];

            // Normalize to 0-1 range if needed
            if ($confidence > 1.0) {
                $confidence = $confidence / 100.0;
            }

            return min(max($confidence, 0.0), 1.0);
        }

        // Calculate from enhanced data if available
        if (isset($ocrResponse['structured_data']['overall_confidence'])) {
            return min(max($ocrResponse['structured_data']['overall_confidence'], 0.0), 1.0);
        }

        // Calculate from golf course properties
        if (isset($ocrResponse['golf_course_properties']['confidence_score'])) {
            return min(max($ocrResponse['golf_course_properties']['confidence_score'], 0.0), 1.0);
        }

        // Fallback: estimate from word confidences
        if (isset($ocrResponse['words']) && is_array($ocrResponse['words'])) {
            $confidences = array_column($ocrResponse['words'], 'confidence');

            return count($confidences) > 0 ? array_sum($confidences) / count($confidences) : 0.5;
        }

        return 0.5; // Default neutral confidence
    }

    /**
     * Calculate data completeness percentage
     *
     * @param  array<string, mixed>  $extractedData
     */
    private function calculateDataCompleteness(array $extractedData): int
    {
        $requiredFields = [
            'course_name' => 3,      // High weight
            'tee_name' => 2,
            'course_rating' => 2,
            'slope_rating' => 2,
        ];

        $optionalFields = [
            'total_par' => 1,
            'total_yardage' => 1,
            'par_values' => 2,
            'handicap_values' => 1,
            'players' => 1,
            'date' => 1,
            'location' => 1,
        ];

        $totalWeight = array_sum($requiredFields) + array_sum($optionalFields);
        $achievedWeight = 0;

        // Check required fields
        foreach ($requiredFields as $field => $weight) {
            if (! empty($extractedData[$field])) {
                $achievedWeight += $weight;
            }
        }

        // Check optional fields
        foreach ($optionalFields as $field => $weight) {
            if (! empty($extractedData[$field])) {
                $achievedWeight += $weight;
            }
        }

        return (int) (($achievedWeight / $totalWeight) * 100);
    }

    /**
     * Extract field-level confidence scores
     *
     * @param  array<string, mixed>  $ocrResponse
     * @param  array<string, mixed>  $extractedData
     * @return array<string, mixed>
     */
    private function extractFieldConfidences(array $ocrResponse, array $extractedData): array
    {
        $confidences = [];

        // Check for structured confidence data
        if (isset($ocrResponse['structured_data'])) {
            $structured = $ocrResponse['structured_data'];

            if (isset($structured['course_information']['confidence'])) {
                $confidences['course_name'] = $structured['course_information']['confidence'];
            }

            if (isset($structured['tee_boxes']) && is_array($structured['tee_boxes'])) {
                foreach ($structured['tee_boxes'] as $tee) {
                    if (isset($tee['confidence'])) {
                        $confidences['tee_data'] = $tee['confidence'];
                        break;
                    }
                }
            }
        }

        // Check golf course properties
        if (isset($ocrResponse['golf_course_properties'])) {
            $props = $ocrResponse['golf_course_properties'];

            if (isset($props['confidence_score'])) {
                $confidences['overall'] = $props['confidence_score'];
            }

            if (isset($props['data_completeness'])) {
                $confidences['completeness'] = $props['data_completeness'];
            }
        }

        // Add field-specific estimates based on data quality
        foreach (['course_name', 'tee_name', 'course_rating', 'slope_rating'] as $field) {
            if (isset($extractedData[$field]) && ! isset($confidences[$field])) {
                $confidences[$field] = $this->estimateFieldConfidence($field, $extractedData[$field]);
            }
        }

        return $confidences;
    }

    /**
     * Estimate confidence for a specific field based on its value
     */
    private function estimateFieldConfidence(string $field, mixed $value): float
    {
        switch ($field) {
            case 'course_name':
                // High confidence if reasonable length and contains expected words
                if (is_string($value) && strlen($value) > 5) {
                    $golfWords = ['golf', 'club', 'course', 'country', 'links', 'hills', 'ridge', 'valley'];
                    $hasGolfWord = false;
                    foreach ($golfWords as $word) {
                        if (stripos($value, $word) !== false) {
                            $hasGolfWord = true;
                            break;
                        }
                    }

                    return $hasGolfWord ? 0.9 : 0.7;
                }

                return 0.5;

            case 'course_rating':
                // High confidence if in valid range
                if (is_numeric($value)) {
                    $rating = (float) $value;

                    return ($rating >= 67.0 && $rating <= 77.0) ? 0.95 : 0.6;
                }

                return 0.3;

            case 'slope_rating':
                // High confidence if in USGA range
                if (is_numeric($value)) {
                    $slope = (int) $value;

                    return ($slope >= 55 && $slope <= 155) ? 0.95 : 0.6;
                }

                return 0.3;

            case 'tee_name':
                // High confidence for common tee names
                $commonTees = ['championship', 'blue', 'white', 'red', 'gold', 'black', 'tips', 'back', 'front'];
                if (is_string($value)) {
                    foreach ($commonTees as $tee) {
                        if (stripos($value, $tee) !== false) {
                            return 0.9;
                        }
                    }

                    return 0.7;
                }

                return 0.5;

            default:
                return 0.7;
        }
    }

    /**
     * Validate golf-specific data
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, string|int|float>>
     */
    private function validateGolfData(array $data): array
    {
        $errors = [];

        // Course rating validation
        if (isset($data['course_rating'])) {
            $rating = (float) $data['course_rating'];
            if ($rating < 67.0 || $rating > 77.0) {
                $errors[] = [
                    'field' => 'course_rating',
                    'error' => 'Outside typical range (67.0-77.0)',
                    'value' => $rating,
                ];
            }
        }

        // Slope rating validation
        if (isset($data['slope_rating'])) {
            $slope = (int) $data['slope_rating'];
            if ($slope < 55 || $slope > 155) {
                $errors[] = [
                    'field' => 'slope_rating',
                    'error' => 'Outside USGA range (55-155)',
                    'value' => $slope,
                ];
            }
        }

        // Par values validation
        if (isset($data['par_values']) && is_array($data['par_values'])) {
            $parValues = $data['par_values'];

            if (count($parValues) !== 18) {
                $errors[] = [
                    'field' => 'par_values',
                    'error' => 'Not 18 holes',
                    'count' => count($parValues),
                ];
            }

            foreach ($parValues as $index => $par) {
                if ($par < 3 || $par > 6) {
                    $errors[] = [
                        'field' => 'par_values',
                        'error' => 'Invalid par value',
                        'hole' => $index + 1,
                        'value' => $par,
                    ];
                }
            }
        }

        // Total par validation
        if (isset($data['total_par'])) {
            $totalPar = (int) $data['total_par'];
            if ($totalPar < 54 || $totalPar > 108) { // 18 holes * 3-6 par range
                $errors[] = [
                    'field' => 'total_par',
                    'error' => 'Outside reasonable range (54-108)',
                    'value' => $totalPar,
                ];
            }
        }

        return $errors;
    }

    /**
     * Determine if this scan is a good training candidate
     *
     * @param  array<int, array<string, string|int|float>>  $validationErrors
     */
    private function isTrainingCandidate(float $confidence, int $completeness, array $validationErrors): bool
    {
        // High confidence and completeness with no major validation errors
        if ($confidence >= 0.8 && $completeness >= 70 && empty($validationErrors)) {
            return true;
        }

        // Or interesting edge cases for training (low confidence but good structure)
        if ($confidence < 0.7 && $completeness >= 60) {
            return true;
        }

        // Or scans with validation errors for negative training examples
        if (! empty($validationErrors)) {
            return true;
        }

        return false;
    }

    /**
     * Get image metadata
     *
     * @return array{width: int, height: int, type: string, size_bytes: int|false}|null
     */
    private function getImageSize(string $imagePath): ?array
    {
        try {
            if (Storage::disk('local')->exists($imagePath)) {
                $fullPath = Storage::disk('local')->path($imagePath);
                $size = getimagesize($fullPath);

                if ($size !== false) {
                    return [
                        'width' => $size[0],
                        'height' => $size[1],
                        'type' => $size['mime'],
                        'size_bytes' => filesize($fullPath),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not get image size', [
                'path' => $imagePath,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Extract preprocessing steps from metadata
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, bool>
     */
    private function getPreprocessingSteps(array $metadata): array
    {
        return [
            'grayscale_applied' => $metadata['grayscale'] ?? true,
            'contrast_enhanced' => $metadata['contrast'] ?? true,
            'noise_reduction' => $metadata['noise_reduction'] ?? false,
            'perspective_correction' => $metadata['perspective'] ?? false,
        ];
    }

    /**
     * Export training data for model improvement
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function exportTrainingData(array $filters = []): array
    {
        $query = ScanTrainingData::where('is_training_candidate', true);

        // Apply filters
        if (isset($filters['verified_only']) && $filters['verified_only']) {
            $query->where('is_verified', true);
        }

        if (isset($filters['min_confidence'])) {
            $query->where('confidence_score', '>=', $filters['min_confidence']);
        }

        if (isset($filters['ocr_provider'])) {
            $query->where('ocr_provider', $filters['ocr_provider']);
        }

        if (isset($filters['enhanced_prompt_only']) && $filters['enhanced_prompt_only']) {
            $query->where('used_enhanced_prompt', true);
        }

        $trainingData = $query->with('scorecardScan')->get();

        return $trainingData->map(function ($data) {
            return [
                'id' => $data->id,
                'image_path' => $data->original_image_path,
                'processed_image_path' => $data->processed_image_path,
                'raw_response' => $data->raw_ocr_response,
                'extracted_data' => $data->extracted_data,
                'verified_data' => $data->verified_data,
                'confidence_score' => $data->confidence_score,
                'quality_metrics' => $data->getQualityMetrics(),
                'processing_metadata' => $data->processing_metadata,
                'ocr_provider' => $data->ocr_provider,
                'used_enhanced_prompt' => $data->used_enhanced_prompt,
                'created_at' => $data->created_at->toISOString(),
            ];
        })->toArray();
    }
}
