<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Kwacha amounts as decimal strings so escrow math does not use floats.
 */
class Money
{
    public static function of(string|int|float|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.00';
        }

        if (is_int($amount)) {
            return number_format($amount, 2, '.', '');
        }

        if (is_float($amount)) {
            return number_format($amount, 2, '.', '');
        }

        $amount = trim($amount);

        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("Invalid money amount [{$amount}].");
        }

        return bcadd($amount, '0', 2);
    }

    public static function add(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcadd(self::of($a), self::of($b), 2);
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::of($a), self::of($b), 2);
    }

    public static function mul(string|int|float|null $amount, string|int|float $rate): string
    {
        $rate = is_float($rate) ? number_format($rate, 4, '.', '') : (string) $rate;

        return bcmul(self::of($amount), $rate, 2);
    }

    public static function neg(string|int|float|null $amount): string
    {
        return bcsub('0', self::of($amount), 2);
    }

    public static function comp(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::of($a), self::of($b), 2);
    }

    public static function gt(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::comp($a, $b) === 1;
    }

    public static function gte(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::comp($a, $b) >= 0;
    }

    public static function lt(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::comp($a, $b) === -1;
    }

    public static function eq(string|int|float|null $a, string|int|float|null $b): bool
    {
        return self::comp($a, $b) === 0;
    }

    public static function isZero(string|int|float|null $amount): bool
    {
        return self::eq($amount, '0');
    }

    public static function isPositive(string|int|float|null $amount): bool
    {
        return self::gt($amount, '0');
    }

    public static function min(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::lt($a, $b) ? self::of($a) : self::of($b);
    }

    public static function max(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::gt($a, $b) ? self::of($a) : self::of($b);
    }
}
