<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'name',
        'tee_name',
        'par_values',
        'handicap_values',
        'slope',
        'rating',
        'location',
        'is_verified',
    ];

    protected $casts = [
        'par_values' => 'array',
        'handicap_values' => 'array',
        'slope' => 'integer',
        'rating' => 'decimal:1',
        'is_verified' => 'boolean',
    ];

    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function getTotalParAttribute(): int
    {
        return array_sum($this->par_values);
    }

    public function getFrontNineParAttribute(): int
    {
        return array_sum(array_slice($this->par_values, 0, 9));
    }

    public function getBackNineParAttribute(): int
    {
        return array_sum(array_slice($this->par_values, 9, 9));
    }
}