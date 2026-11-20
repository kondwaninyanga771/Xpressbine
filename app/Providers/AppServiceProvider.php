<?php

namespace App\Providers;

use App\Services\Ai\LlmProblemEstimator;
use App\Services\Ai\MockProblemEstimator;
use App\Services\Ai\ProblemEstimator;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProblemEstimator::class, function ($app) {
            return match (config('xpressbine.ai.driver')) {
                'llm' => $app->make(LlmProblemEstimator::class),
                'mock' => $app->make(MockProblemEstimator::class),
                default => throw new InvalidArgumentException('XPRESSBINE_AI_DRIVER must be mock or llm.'),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
