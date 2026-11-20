<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function accept(User $user, Quote $quote): bool
    {
        return $user->booksAsCustomer() && $quote->jobRequest && $quote->jobRequest->customer_id === $user->id;
    }
}
