<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Database\Factories\ScorecardScanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ScorecardScan extends Model
{
    /** @use HasFactory<ScorecardScanFactory> */
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return ScorecardScanFactory
     */
    protected static function newFactory()
    {
        return ScorecardScanFactory::new();
    }

    /** @var array<int, string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'raw_ocr_data' => 'array',
        'parsed_data' => 'array',
        'confidence_scores' => 'array',
    ];

    /**
     * @return BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<\App\Models\User> $userModel */
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        return $this->belongsTo($userModel);
    }

    /**
     * @return HasOne<Round, $this>
     */
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

    /**
     * @return array<int, string>
     */
    public function hasLowConfidenceFields(float $threshold = 0.85): array
    {
        if (! $this->confidence_scores || ! is_array($this->confidence_scores)) {
            return [];
        }

        return array_keys(array_filter(
            $this->confidence_scores,
            fn ($confidence) => (float) $confidence < $threshold
        ));
    }
}
