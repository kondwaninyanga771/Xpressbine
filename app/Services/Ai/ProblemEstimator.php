<?php

namespace App\Services\Ai;

interface ProblemEstimator
{
    /**
     * Turn a customer's description, and an optional photo, into a trade,
     * an urgency and a kwacha price range.
     */
    public function estimate(string $description, ?string $photoPath = null): Estimate;
}
