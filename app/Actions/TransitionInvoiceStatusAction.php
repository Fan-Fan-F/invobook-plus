<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceStatusTransitionException;
use App\Models\Invoice;

final class TransitionInvoiceStatusAction
{
    /**
     * Transition the invoice to a target status with domain validation.
     *
     * @throws InvalidInvoiceStatusTransitionException
     */
    public function execute(Invoice $invoice, InvoiceStatus $targetStatus): Invoice
    {
        if ($invoice->status === $targetStatus) {
            return $invoice;
        }

        if (! $invoice->status->canTransitionTo($targetStatus)) {
            throw new InvalidInvoiceStatusTransitionException($invoice->status, $targetStatus);
        }

        $invoice->status = $targetStatus;
        $invoice->save();

        return $invoice;
    }
}
