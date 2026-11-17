<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidPaymentException;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class RecordPaymentAction
{
    /**
     * Record a payment against an invoice and update its balance and status.
     *
     * @throws InvalidPaymentException
     */
    public function execute(
        Invoice $invoice,
        int $amountCents,
        PaymentMethod $paymentMethod = PaymentMethod::BankTransfer,
        ?CarbonInterface $paymentDate = null,
        ?string $reference = null,
        ?string $notes = null
    ): Payment {
        if ($amountCents <= 0) {
            throw new InvalidPaymentException('Payment amount must be greater than zero.');
        }

        if ($invoice->status === InvoiceStatus::Draft) {
            throw new InvalidPaymentException('Cannot record payment on a draft invoice. Please send the invoice first.');
        }

        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw new InvalidPaymentException('Cannot record payment on a cancelled invoice.');
        }

        $balance = $invoice->balanceCents();
        if ($amountCents > $balance) {
            throw new InvalidPaymentException(sprintf(
                'Payment amount (%s) exceeds outstanding invoice balance (%s).',
                $invoice->currency->format($amountCents),
                $invoice->currency->format($balance)
            ));
        }

        return DB::transaction(function () use ($invoice, $amountCents, $paymentMethod, $paymentDate, $reference, $notes): Payment {
            $payment = $invoice->payments()->create([
                'amount_cents' => $amountCents,
                'payment_date' => $paymentDate ?? Carbon::today(),
                'payment_method' => $paymentMethod,
                'reference' => $reference,
                'notes' => $notes,
            ]);

            $invoice->paid_cents += $amountCents;

            if ($invoice->paid_cents >= $invoice->total_cents) {
                $invoice->status = InvoiceStatus::Paid;
            } else {
                $invoice->status = InvoiceStatus::PartiallyPaid;
            }

            $invoice->save();

            return $payment;
        });
    }
}
