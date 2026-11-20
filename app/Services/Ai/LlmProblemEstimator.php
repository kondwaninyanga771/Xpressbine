<?php

namespace App\Services\Ai;

use App\Exceptions\DomainException;
use App\Models\Category;
use App\Support\Money;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI-compatible chat completion. Enabled with XPRESSBINE_AI_DRIVER=llm
 * and XPRESSBINE_AI_API_KEY. A photo is sent as a data URL when the model
 * accepts image input.
 */
class LlmProblemEstimator implements ProblemEstimator
{
    public function estimate(string $description, ?string $photoPath = null): Estimate
    {
        $apiKey = config('xpressbine.ai.api_key');

        if (! $apiKey) {
            throw new DomainException('XPRESSBINE_AI_DRIVER is llm but XPRESSBINE_AI_API_KEY is empty.');
        }

        $trades = Category::query()->where('is_active', true)->get();
        $tradeList = $trades->map(fn (Category $category) => $category->slug.' ('.$category->name.', K'.$category->price_min.'-K'.$category->price_max.')')->implode('; ');

        $content = [
            ['type' => 'text', 'text' => $description],
        ];

        if ($photoPath) {
            $mime = mime_content_type($photoPath) ?: 'image/jpeg';
            $encoded = base64_encode((string) file_get_contents($photoPath));
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => "data:{$mime};base64,{$encoded}"],
            ];
        }

        $response = Http::withToken($apiKey)
            ->timeout((int) config('xpressbine.ai.timeout'))
            ->acceptJson()
            ->post(rtrim((string) config('xpressbine.ai.base_url'), '/').'/chat/completions', [
                'model' => config('xpressbine.ai.model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You estimate in-person home services in Lusaka, Zambia, priced in Zambian kwacha (ZMW). '
                            .'Reply with JSON only: {"trade": slug or null, "urgency": "low"|"medium"|"high"|"emergency", "price_min": number, "price_max": number, "confidence": number between 0 and 1, "rationale": string}. '
                            .'Allowed trade slugs: '.$tradeList.'. Use null if the description is not one of those trades. '
                            .'Keep prices inside a realistic Lusaka band for that trade.',
                    ],
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

        if ($response->failed()) {
            throw new DomainException('The AI estimate service failed: '.$response->status().'.');
        }

        $raw = $response->json('choices.0.message.content');
        $data = is_array($raw) ? $raw : json_decode((string) $raw, true);

        if (! is_array($data)) {
            throw new DomainException('The AI estimate service returned something that was not JSON.');
        }

        $slug = isset($data['trade']) ? (string) $data['trade'] : null;
        $category = $slug ? $trades->firstWhere('slug', $slug) : null;

        if ($slug && ! $category) {
            $slug = null;
        }

        $urgency = in_array($data['urgency'] ?? '', ['low', 'medium', 'high', 'emergency'], true)
            ? $data['urgency']
            : 'medium';

        $min = Money::of($data['price_min'] ?? ($category->price_min ?? '150'));
        $max = Money::of($data['price_max'] ?? ($category->price_max ?? '800'));

        if (Money::gt($min, $max)) {
            [$min, $max] = [$max, $min];
        }

        $confidence = (float) ($data['confidence'] ?? 0.5);
        $confidence = max(0, min(1, $confidence));

        return new Estimate(
            trade: $category?->slug,
            tradeLabel: $category?->name,
            categoryId: $category?->id,
            urgency: $urgency,
            priceMin: $min,
            priceMax: $max,
            currency: (string) config('xpressbine.currency'),
            confidence: $confidence,
            rationale: (string) ($data['rationale'] ?? 'Estimate from the language model.'),
            photoProvided: $photoPath !== null,
            driver: 'llm',
        );
    }
}
