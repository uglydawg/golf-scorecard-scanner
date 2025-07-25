<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanTrainingData extends Model
{
    protected $table = 'scan_training_data';

    protected $fillable = [
        'scorecard_scan_id',
        'original_image_path',
        'processed_image_path',
        'raw_ocr_response',
        'extracted_data',
        'confidence_score',
        'verified_data',
        'corrections',
        'is_verified',
        'is_training_candidate',
        'ocr_provider',
        'used_enhanced_prompt',
        'processing_metadata',
        'error_analysis',
        'data_completeness_score',
        'field_confidence_scores',
        'validation_errors',
        'processing_time_ms',
        'model_version',
        'processed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'raw_ocr_response' => 'array',
        'extracted_data' => 'array',
        'verified_data' => 'array',
        'corrections' => 'array',
        'is_verified' => 'boolean',
        'is_training_candidate' => 'boolean',
        'used_enhanced_prompt' => 'boolean',
        'processing_metadata' => 'array',
        'error_analysis' => 'array',
        'field_confidence_scores' => 'array',
        'validation_errors' => 'array',
        'confidence_score' => 'decimal:4',
        'processed_at' => 'timestamp',
    ];

    /**
     * @return BelongsTo<ScorecardScan, $this>
     */
    public function scorecardScan(): BelongsTo
    {
        return $this->belongsTo(ScorecardScan::class);
    }

    /**
     * Scope for training candidates
     *
     * @param  Builder<ScanTrainingData>  $query
     * @return Builder<ScanTrainingData>
     */
    public function scopeTrainingCandidates(Builder $query): Builder
    {
        return $query->where('is_training_candidate', true);
    }

    /**
     * Scope for verified data
     *
     * @param  Builder<ScanTrainingData>  $query
     * @return Builder<ScanTrainingData>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope for high confidence scans
     *
     * @param  Builder<ScanTrainingData>  $query
     * @return Builder<ScanTrainingData>
     */
    public function scopeHighConfidence(Builder $query, float $threshold = 0.9): Builder
    {
        return $query->where('confidence_score', '>=', $threshold);
    }

    /**
     * Scope for low confidence scans (need review)
     *
     * @param  Builder<ScanTrainingData>  $query
     * @return Builder<ScanTrainingData>
     */
    public function scopeLowConfidence(Builder $query, float $threshold = 0.7): Builder
    {
        return $query->where('confidence_score', '<', $threshold);
    }

    /**
     * Scope for enhanced prompt usage
     *
     * @param  Builder<ScanTrainingData>  $query
     * @return Builder<ScanTrainingData>
     */
    public function scopeEnhancedPrompt(Builder $query): Builder
    {
        return $query->where('used_enhanced_prompt', true);
    }

    /**
     * Get accuracy score compared to verified data
     */
    public function getAccuracyScore(): ?float
    {
        if (! $this->is_verified || ! $this->verified_data || ! $this->extracted_data) {
            return null;
        }

        $verified = $this->verified_data;
        $extracted = $this->extracted_data;

        $totalFields = 0;
        $correctFields = 0;

        $fieldsToCheck = [
            'course_name',
            'tee_name',
            'course_rating',
            'slope_rating',
            'total_par',
            'total_yardage',
        ];

        foreach ($fieldsToCheck as $field) {
            if (isset($verified[$field])) {
                $totalFields++;
                if (isset($extracted[$field]) && $this->fieldsMatch($verified[$field], $extracted[$field])) {
                    $correctFields++;
                }
            }
        }

        return $totalFields > 0 ? ($correctFields / $totalFields) : null;
    }

    /**
     * Compare two field values with some tolerance
     */
    private function fieldsMatch(mixed $verified, mixed $extracted): bool
    {
        // Exact match
        if ($verified === $extracted) {
            return true;
        }

        // String comparison with case insensitivity and trimming
        if (is_string($verified) && is_string($extracted)) {
            return strtolower(trim($verified)) === strtolower(trim($extracted));
        }

        // Numeric comparison with tolerance
        if (is_numeric($verified) && is_numeric($extracted)) {
            return abs((float) $verified - (float) $extracted) < 0.1;
        }

        return false;
    }

    /**
     * Get data quality metrics
     *
     * @return array<string, mixed>
     */
    public function getQualityMetrics(): array
    {
        return [
            'confidence_score' => $this->confidence_score,
            'data_completeness' => $this->data_completeness_score,
            'accuracy_score' => $this->getAccuracyScore(),
            'field_count' => count($this->extracted_data ?? []),
            'has_validation_errors' => ! empty($this->validation_errors),
            'processing_time' => $this->processing_time_ms,
        ];
    }

    /**
     * Mark as verified with corrections
     *
     * @param  array<string, mixed>  $verifiedData
     * @param  array<string, mixed>  $corrections
     */
    public function markAsVerified(array $verifiedData, array $corrections = []): void
    {
        $this->update([
            'verified_data' => $verifiedData,
            'corrections' => $corrections,
            'is_verified' => true,
            'error_analysis' => $this->generateErrorAnalysis($verifiedData),
        ]);
    }

    /**
     * Generate error analysis by comparing extracted vs verified data
     *
     * @param  array<string, mixed>  $verifiedData
     * @return array<string, mixed>
     */
    private function generateErrorAnalysis(array $verifiedData): array
    {
        $analysis = [
            'field_errors' => [],
            'missing_fields' => [],
            'incorrect_fields' => [],
            'accuracy_by_field' => [],
        ];

        $extracted = $this->extracted_data ?? [];

        foreach ($verifiedData as $field => $correctValue) {
            if (! isset($extracted[$field])) {
                $analysis['missing_fields'][] = $field;
                $analysis['accuracy_by_field'][$field] = 0.0;
            } elseif (! $this->fieldsMatch($correctValue, $extracted[$field])) {
                $analysis['incorrect_fields'][] = [
                    'field' => $field,
                    'extracted' => $extracted[$field],
                    'correct' => $correctValue,
                ];
                $analysis['accuracy_by_field'][$field] = 0.0;
            } else {
                $analysis['accuracy_by_field'][$field] = 1.0;
            }
        }

        $analysis['overall_accuracy'] = count($verifiedData) > 0
            ? array_sum($analysis['accuracy_by_field']) / count($verifiedData)
            : 0.0;

        return $analysis;
    }
}
