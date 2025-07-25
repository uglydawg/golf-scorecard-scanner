<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Stub User model for package testing
 * In actual implementation, this would extend the host app's User model
 */
class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'timestamp',
        'password' => 'hashed',
    ];

    /**
     * @return HasMany<ScorecardScan, $this>
     */
    public function scorecardScans(): HasMany
    {
        return $this->hasMany(ScorecardScan::class);
    }
}
