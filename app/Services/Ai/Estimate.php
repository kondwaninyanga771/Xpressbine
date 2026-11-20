<?php

namespace App\Services\Ai;

class Estimate
{
    public function __construct(
        public ?string $trade,
        public ?string $tradeLabel,
        public ?int $categoryId,
        public string $urgency,
        public string $priceMin,
        public string $priceMax,
        public string $currency,
        public float $confidence,
        public string $rationale,
        public bool $photoProvided,
        public string $driver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trade' => $this->trade,
            'trade_label' => $this->tradeLabel,
            'category_id' => $this->categoryId,
            'urgency' => $this->urgency,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'currency' => $this->currency,
            'confidence' => $this->confidence,
            'rationale' => $this->rationale,
            'photo_provided' => $this->photoProvided,
            'driver' => $this->driver,
        ];
    }
}
