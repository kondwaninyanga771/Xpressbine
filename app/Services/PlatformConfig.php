<?php

namespace App\Services;

use App\Exceptions\DomainException;
use App\Models\PlatformSetting;

class PlatformConfig
{
    /**
     * Decimal settings are returned as strings suitable for bcmath.
     *
     * @var list<string>
     */
    private array $decimalKeys = [
        'commission_rate',
        'late_cancel_fee_rate',
        'call_out_fee_rate',
        'low_rating_threshold',
        'emergency_budget_rate',
        'budget_alert_warn',
        'budget_alert_full',
        'default_search_radius_km',
        'default_service_radius_km',
    ];

    public function get(string $key): mixed
    {
        $stored = PlatformSetting::query()->where('key', $key)->value('value');
        $value = $stored !== null ? $stored : config('xpressbine.'.$key);

        if (in_array($key, $this->decimalKeys, true)) {
            return number_format((float) $value, 4, '.', '');
        }

        if (is_numeric($value) && ! is_string($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    public function rate(string $key): string
    {
        return number_format((float) $this->get($key), 4, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    public function allEditable(): array
    {
        $keys = array_keys(config('xpressbine.editable_settings'));
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function update(array $settings): array
    {
        $rules = config('xpressbine.editable_settings');

        foreach ($settings as $key => $value) {
            if (! isset($rules[$key])) {
                throw new DomainException("Setting [{$key}] cannot be changed.");
            }

            if (! is_numeric($value)) {
                throw new DomainException("Setting [{$key}] must be a number.");
            }

            $numeric = (float) $value;

            if ($numeric < $rules[$key]['min'] || $numeric > $rules[$key]['max']) {
                throw new DomainException("Setting [{$key}] is outside the allowed range.");
            }

            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value],
            );
        }

        return $this->allEditable();
    }
}
