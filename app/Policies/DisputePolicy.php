<?php

namespace App\Policies;

use App\Models\Dispute;
use App\Models\User;

class DisputePolicy
{
    public function view(User $user, Dispute $dispute): bool
    {
        return $user->isAdmin() || $dispute->booking->involves($user);
    }

    public function respond(User $user, Dispute $dispute): bool
    {
        return $user->isProvider()
            && $user->providerProfile
            && $user->providerProfile->id === $dispute->booking->provider_profile_id;
    }
}
