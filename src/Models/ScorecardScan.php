<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScorecardScan extends Model
{
    use HasFactory;
    
    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\ScorecardScanFactory::new();
    }
    
    protected $fillable = [
        'user_id',
        'original_image_path',
        'processed_image_path',
        'raw_ocr_data',
        'parsed_data',
        'confidence_scores',
        'status',
        'error_message',
    ];

    protected $casts = [
        'raw_ocr_data' => 'array',
        'parsed_data' => 'array',
        'confidence_scores' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function round(): HasOne
    {
        return $this->hasOne(Round::class);
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function hasLowConfidenceFields(float $threshold = 0.85): array
    {
        if (!$this->confidence_scores) {
            return [];
        }

        return array_keys(array_filter(
            $this->confidence_scores,
            fn($confidence) => $confidence < $threshold
        ));
    }
}