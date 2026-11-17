<?php

declare(strict_types=1);

namespace App\Enums;

enum Currency: string
{
    case USD = 'USD';
    case EUR = 'EUR';
    case CNY = 'CNY';
    case GBP = 'GBP';
    case JPY = 'JPY';

    /**
     * Currency symbol.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::EUR => '€',
            self::CNY => '¥',
            self::GBP => '£',
            self::JPY => '¥',
        };
    }

    /**
     * Number of decimal places. JPY is zero-decimal, others are 2.
     */
    public function decimalPlaces(): int
    {
        return match ($this) {
            self::JPY => 0,
            default => 2,
        };
    }

    /**
     * Format an amount in cents / minor unit into human readable money string.
     */
    public function format(int $cents): string
    {
        if ($this->decimalPlaces() === 0) {
            return sprintf('%s%s', $this->symbol(), number_format($cents));
        }

        $amount = $cents / 100;

        return sprintf('%s%s', $this->symbol(), number_format($amount, 2));
    }
}
