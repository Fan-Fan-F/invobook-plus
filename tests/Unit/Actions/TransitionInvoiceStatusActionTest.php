<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\TransitionInvoiceStatusAction;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvalidInvoiceStatusTransitionException;
use App\Models\Invoice;

beforeEach(function (): void {
    $this->action = new TransitionInvoiceStatusAction;
});

it('allows valid status transitions', function (InvoiceStatus $from, InvoiceStatus $to): void {
    $invoice = Invoice::factory()->create(['status' => $from]);

    $updated = $this->action->execute($invoice, $to);

    expect($updated->status)->toBe($to)
        ->and($invoice->fresh()->status)->toBe($to);
})->with([
    [InvoiceStatus::Draft, InvoiceStatus::Sent],
    [InvoiceStatus::Draft, InvoiceStatus::Cancelled],
    [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid],
    [InvoiceStatus::Sent, InvoiceStatus::Paid],
    [InvoiceStatus::Sent, InvoiceStatus::Overdue],
    [InvoiceStatus::Sent, InvoiceStatus::Cancelled],
    [InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid],
    [InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue],
    [InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid],
    [InvoiceStatus::Overdue, InvoiceStatus::Paid],
]);

it('rejects invalid status transitions with exception', function (InvoiceStatus $from, InvoiceStatus $to): void {
    $invoice = Invoice::factory()->create(['status' => $from]);

    expect(fn () => $this->action->execute($invoice, $to))
        ->toThrow(InvalidInvoiceStatusTransitionException::class);
})->with([
    [InvoiceStatus::Paid, InvoiceStatus::Draft],
    [InvoiceStatus::Paid, InvoiceStatus::Sent],
    [InvoiceStatus::Paid, InvoiceStatus::Cancelled],
    [InvoiceStatus::Cancelled, InvoiceStatus::Draft],
    [InvoiceStatus::Cancelled, InvoiceStatus::Paid],
    [InvoiceStatus::Draft, InvoiceStatus::Paid],
]);

it('returns same invoice when transitioning to identical status', function (): void {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);

    $result = $this->action->execute($invoice, InvoiceStatus::Sent);

    expect($result->status)->toBe(InvoiceStatus::Sent);
});
