<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'scorecard_scan_id',
        'played_at',
        'total_score',
        'front_nine_score',
        'back_nine_score',
        'weather',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'played_at' => 'date',
        'total_score' => 'integer',
        'front_nine_score' => 'integer',
        'back_nine_score' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return BelongsTo<ScorecardScan, $this>
     */
    public function scorecardScan(): BelongsTo
    {
        return $this->belongsTo(ScorecardScan::class);
    }

    /**
     * @return HasMany<RoundScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(RoundScore::class);
    }

    public function getScoreOverParAttribute(): int
    {
        return $this->total_score - $this->course->total_par;
    }
}
