<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Integer-minor-unit money helper. All arithmetic happens in cents (2 decimal places).
 * Do not use this for FX rates or percentages — only currency amounts.
 */
final class Money
{
    public const SCALE = 2;

    public static function minor(int|float|string $value): int
    {
        if (is_int($value)) {
            return $value * 100;
        }

        if (is_float($value)) {
            $value = number_format($value, self::SCALE, '.', '');
        }

        $normalized = self::normalizeString((string) $value);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        if ($normalized === '' || ! preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '00');
        $fraction = substr(str_pad($fraction, self::SCALE, '0'), 0, self::SCALE);
        $minor = ((int) $whole) * 100 + (int) $fraction;

        return $negative ? -$minor : $minor;
    }

    public static function fromMinor(int $minor): string
    {
        $negative = $minor < 0;
        $absolute = abs($minor);
        $formatted = intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), self::SCALE, '0', STR_PAD_LEFT);

        return $negative ? '-'.$formatted : $formatted;
    }

    public static function of(int|float|string $value): string
    {
        return self::fromMinor(self::minor($value));
    }

    public static function round(int|float|string $value): float
    {
        return (float) self::of($value);
    }

    public static function add(int|float|string ...$values): string
    {
        $sum = 0;
        foreach ($values as $value) {
            $sum += self::minor($value);
        }

        return self::fromMinor($sum);
    }

    public static function sub(int|float|string $left, int|float|string $right): string
    {
        return self::fromMinor(self::minor($left) - self::minor($right));
    }

    public static function mul(int|float|string $left, int|float|string $right): string
    {
        return self::fromMinor(intdiv(self::minor($left) * self::minor($right), 100));
    }

    public static function cmp(int|float|string $left, int|float|string $right): int
    {
        return self::minor($left) <=> self::minor($right);
    }

    public static function isZero(int|float|string $value): bool
    {
        return self::minor($value) === 0;
    }

    public static function isPositive(int|float|string $value): bool
    {
        return self::minor($value) > 0;
    }

    private static function normalizeString(string $value): string
    {
        $value = trim(str_replace([',', ' '], '', $value));
        if ($value === '') {
            throw new InvalidArgumentException('Monetary amount cannot be empty.');
        }

        return $value;
    }
}
