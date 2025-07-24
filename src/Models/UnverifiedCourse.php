<?php

declare(strict_types=1);

namespace ScorecardScanner\Models;

use Illuminate\Database\Eloquent\Model;

class UnverifiedCourse extends Model
{
    protected $fillable = [
        'name',
        'tee_name',
        'par_values',
        'handicap_values',
        'slope',
        'rating',
        'location',
        'submission_count',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'par_values' => 'array',
        'handicap_values' => 'array',
        'slope' => 'integer',
        'rating' => 'decimal:1',
        'submission_count' => 'integer',
    ];

    public function incrementSubmissionCount(): void
    {
        $this->increment('submission_count');
    }

    public function approve(): Course
    {
        $course = Course::create([
            'name' => $this->name,
            'tee_name' => $this->tee_name,
            'par_values' => $this->par_values,
            'handicap_values' => $this->handicap_values,
            'slope' => $this->slope,
            'rating' => $this->rating,
            'location' => $this->location,
            'is_verified' => true,
        ]);

        $this->update(['status' => 'approved']);

        return $course;
    }

    public function reject(?string $reason = null): void
    {
        $this->update([
            'status' => 'rejected',
            'admin_notes' => $reason,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getTotalParAttribute(): int
    {
        return array_sum($this->par_values);
    }

    public static function findOrCreateSimilar(array $courseData): self
    {
        $existing = self::where('name', $courseData['name'])
            ->where('tee_name', $courseData['tee_name'])
            ->where('status', 'pending')
            ->first();

        if ($existing &&
            $existing->par_values === $courseData['par_values'] &&
            $existing->handicap_values === $courseData['handicap_values']) {
            $existing->incrementSubmissionCount();

            return $existing;
        }

        return self::create($courseData);
    }
}
