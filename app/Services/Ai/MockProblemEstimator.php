<?php

namespace App\Services\Ai;

use App\Models\Category;
use App\Support\Money;

/**
 * Deterministic estimator used for the demo and for tests. It does not call
 * a network and does not need an API key. Keyword lists are ordered so a
 * more specific trade (pool cleaning) wins over a broader one (cleaning).
 */
class MockProblemEstimator implements ProblemEstimator
{
    public function estimate(string $description, ?string $photoPath = null): Estimate
    {
        $haystack = mb_strtolower($description);
        $trade = null;
        $matchedWord = null;

        foreach ($this->rules() as $slug => $words) {
            foreach ($words as $word) {
                if (str_contains($haystack, $word)) {
                    $trade = $slug;
                    $matchedWord = $word;
                    break 2;
                }
            }
        }

        $urgency = $this->urgency($haystack);
        $category = $trade ? Category::query()->where('slug', $trade)->first() : null;
        $multiplier = (string) config('xpressbine.urgency_multipliers.'.$urgency, '1.00');

        $min = Money::mul($category->price_min ?? '150.00', $multiplier);
        $max = Money::mul($category->price_max ?? '800.00', $multiplier);

        if (Money::gt($min, $max)) {
            [$min, $max] = [$max, $min];
        }

        $photo = $photoPath !== null;
        $label = $category?->name;

        if ($trade === null) {
            $rationale = 'No trade keyword matched. Showing a wide Lusaka call-out range until the description is clearer.';
            $confidence = 0.35;
        } else {
            $rationale = "Matched \"{$matchedWord}\" to {$label}. Urgency is {$urgency}, so the category price band is scaled by {$multiplier}.";
            $confidence = 0.86;
        }

        if ($photo) {
            $rationale .= ' A photo was attached. This mock driver does not inspect the image; a vision model does that when XPRESSBINE_AI_DRIVER=llm.';
            $confidence = min(0.95, $confidence + 0.04);
        }

        return new Estimate(
            trade: $trade,
            tradeLabel: $label,
            categoryId: $category?->id,
            urgency: $urgency,
            priceMin: $min,
            priceMax: $max,
            currency: (string) config('xpressbine.currency'),
            confidence: $confidence,
            rationale: $rationale,
            photoProvided: $photo,
            driver: 'mock',
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'pool-cleaning' => ['pool', 'algae', 'chlorine'],
            'electrical' => ['wire', 'wiring', 'socket', 'power', 'light', 'shock', 'breaker', 'electric', 'plug'],
            'plumbing' => ['leak', 'pipe', 'sink', 'geyser', 'toilet', 'drain', 'tap', 'plumb', 'burst', 'water'],
            'event-planning' => ['wedding', 'party', 'event', 'decor', 'birthday'],
            'carpentry' => ['furniture', 'door', 'cupboard', 'wood', 'carpenter', 'shelf', 'cabinet'],
            'cleaning' => ['clean', 'dirty', 'dust', 'maid', 'mop'],
            'student-help' => ['student', 'tutor', 'tutoring', 'assignment', 'assemble'],
        ];
    }

    private function urgency(string $haystack): string
    {
        foreach (['burst', 'flood', 'spark', 'shock', 'no power', 'smoke', 'emergency', 'overflow'] as $word) {
            if (str_contains($haystack, $word)) {
                return 'emergency';
            }
        }

        foreach (['leak', 'broken', 'not working', 'urgent', 'fault', 'tripping'] as $word) {
            if (str_contains($haystack, $word)) {
                return 'high';
            }
        }

        foreach (['routine', 'regular', 'weekly', 'dusty', 'schedule'] as $word) {
            if (str_contains($haystack, $word)) {
                return 'low';
            }
        }

        return 'medium';
    }
}
