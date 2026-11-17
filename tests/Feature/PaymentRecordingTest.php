<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\RecordPaymentAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\InvalidPaymentException;
use App\Models\Invoice;

beforeEach(function (): void {
    $this->action = new RecordPaymentAction;
});

it('records partial payment and updates invoice status to partially_paid', function (): void {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Sent,
        'total_cents' => 10000, // $100.00
        'paid_cents' => 0,
    ]);

    $payment = $this->action->execute(
        invoice: $invoice,
        amountCents: 4000,
        paymentMethod: PaymentMethod::BankTransfer,
        reference: 'REF-001'
    );

    expect($payment->amount_cents)->toBe(4000)
        ->and($payment->reference)->toBe('REF-001')
        ->and($invoice->fresh()->paid_cents)->toBe(4000)
        ->and($invoice->fresh()->balanceCents())->toBe(6000)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);
});

it('records multiple payments until fully paid', function (): void {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Sent,
        'total_cents' => 10000,
        'paid_cents' => 0,
    ]);

    // Payment 1: 3000
    $this->action->execute($invoice, 3000);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->fresh()->paid_cents)->toBe(3000);

    // Payment 2: 7000 (remainder)
    $this->action->execute($invoice, 7000);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_cents)->toBe(10000)
        ->and($invoice->fresh()->balanceCents())->toBe(0)
        ->and($invoice->fresh()->isFullyPaid())->toBeTrue();

    expect($invoice->payments)->toHaveCount(2);
});

it('rejects recording payment on draft invoice', function (): void {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Draft,
        'total_cents' => 5000,
    ]);

    expect(fn () => $this->action->execute($invoice, 2000))
        ->toThrow(InvalidPaymentException::class, 'Cannot record payment on a draft invoice');
});

it('rejects recording payment on cancelled invoice', function (): void {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Cancelled,
        'total_cents' => 5000,
    ]);

    expect(fn () => $this->action->execute($invoice, 2000))
        ->toThrow(InvalidPaymentException::class, 'Cannot record payment on a cancelled invoice');
});

it('rejects payments exceeding outstanding balance', function (): void {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Sent,
        'total_cents' => 5000,
        'paid_cents' => 3000, // remaining balance = 2000
    ]);

    expect(fn () => $this->action->execute($invoice, 2500))
        ->toThrow(InvalidPaymentException::class, 'exceeds outstanding invoice balance');
});

it('records payment via API endpoint', function (): void {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Sent,
        'total_cents' => 8000,
        'paid_cents' => 0,
    ]);

    $response = $this->postJson(route('api.invoices.payments.store', $invoice), [
        'amount_cents' => 3500,
        'payment_method' => 'stripe',
        'reference' => 'ch_test_123',
        'notes' => 'Online payment via Stripe',
    ]);

    $response->assertCreated()
        ->assertJsonPath('payment.amount_cents', 3500)
        ->assertJsonPath('payment.payment_method', 'stripe')
        ->assertJsonPath('invoice.paid_cents', 3500)
        ->assertJsonPath('invoice.status', 'partially_paid');

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'amount_cents' => 3500,
        'reference' => 'ch_test_123',
    ]);
});
