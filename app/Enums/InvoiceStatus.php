<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /**
     * Determine if this status can transition to the given status.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($this) {
            self::Draft => in_array($target, [self::Sent, self::Cancelled], true),
            self::Sent => in_array($target, [self::PartiallyPaid, self::Paid, self::Overdue, self::Cancelled], true),
            self::PartiallyPaid => in_array($target, [self::Paid, self::Overdue, self::Cancelled], true),
            self::Overdue => in_array($target, [self::PartiallyPaid, self::Paid, self::Cancelled], true),
            self::Paid => false,
            self::Cancelled => false,
        };
    }

    /**
     * Whether this invoice can accept payments.
     */
    public function isPayable(): bool
    {
        return in_array($this, [self::Sent, self::PartiallyPaid, self::Overdue], true);
    }

    /**
     * Whether this invoice is in a terminal status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled], true);
    }
}
