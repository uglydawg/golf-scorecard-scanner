<?php

declare(strict_types=1);

namespace ScorecardScanner\Policies;

use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Models\User;

class ScorecardScanPolicy
{
    public function view(User $user, ScorecardScan $scan): bool
    {
        return $user->id === $scan->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ScorecardScan $scan): bool
    {
        return $user->id === $scan->user_id;
    }

    public function delete(User $user, ScorecardScan $scan): bool
    {
        return $user->id === $scan->user_id;
    }
}