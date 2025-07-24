<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ScorecardScan;
use App\Models\User;

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
