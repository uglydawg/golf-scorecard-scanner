<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /**
     * @param  Builder<Course>  $query
     * @return Builder<Course>
     */
    public function scopeWhere($query, string $column, mixed $value): Builder
    {
        return $query->where($column, $value);
    }

    /** @var array<int, string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'par_values' => 'array',
        'handicap_values' => 'array',
        'slope' => 'integer',
        'rating' => 'decimal:1',
        'is_verified' => 'boolean',
    ];

    /**
     * @return HasMany<Round, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }

    public function getTotalParAttribute(): int
    {
        return array_sum((array) $this->par_values);
    }

    public function getFrontNineParAttribute(): int
    {
        return array_sum(array_slice((array) $this->par_values, 0, 9));
    }

    public function getBackNineParAttribute(): int
    {
        return array_sum(array_slice((array) $this->par_values, 9, 9));
    }
}
