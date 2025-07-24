<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundScore extends Model
{
    protected $fillable = [
        'round_id',
        'player_name',
        'hole_number',
        'score',
        'par',
        'handicap',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'hole_number' => 'integer',
        'score' => 'integer',
        'par' => 'integer',
        'handicap' => 'integer',
    ];

    /**
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    public function getScoreOverParAttribute(): int
    {
        return $this->score - $this->par;
    }

    public function isEagle(): bool
    {
        return $this->score_over_par <= -2;
    }

    public function isBirdie(): bool
    {
        return $this->score_over_par === -1;
    }

    public function isPar(): bool
    {
        return $this->score_over_par === 0;
    }

    public function isBogey(): bool
    {
        return $this->score_over_par === 1;
    }

    public function isDoubleBogey(): bool
    {
        return $this->score_over_par === 2;
    }
}
