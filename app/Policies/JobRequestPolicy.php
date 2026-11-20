<?php

namespace App\Policies;

use App\Models\JobRequest;
use App\Models\User;

class JobRequestPolicy
{
    public function view(User $user, JobRequest $jobRequest): bool
    {
        if ($user->isAdmin() || $user->isProvider()) {
            return true;
        }

        return $jobRequest->customer_id === $user->id;
    }
}
