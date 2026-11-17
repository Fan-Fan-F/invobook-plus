<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceOverdueMail;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

it('identifies overdue invoices, updates status, and sends notification email', function (): void {
    Mail::fake();

    $client = Client::factory()->create(['email' => 'finance@client.com']);

    // Overdue invoice (due 5 days ago, sent, unpaid)
    $overdueInvoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => Carbon::today()->subDays(5),
        'total_cents' => 10000,
        'paid_cents' => 0,
    ]);

    // Current invoice (due in future)
    $currentInvoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => Carbon::today()->addDays(10),
        'total_cents' => 10000,
        'paid_cents' => 0,
    ]);

    // Already paid invoice (due in past, but paid)
    $paidInvoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Paid,
        'due_date' => Carbon::today()->subDays(5),
        'total_cents' => 10000,
        'paid_cents' => 10000,
    ]);

    $this->artisan('invoices:check-overdue')
        ->expectsOutput('Found 1 overdue invoice(s).')
        ->assertSuccessful();

    expect($overdueInvoice->fresh()->status)->toBe(InvoiceStatus::Overdue)
        ->and($overdueInvoice->fresh()->overdue_reminder_sent_at)->not->toBeNull()
        ->and($currentInvoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($paidInvoice->fresh()->status)->toBe(InvoiceStatus::Paid);

    Mail::assertQueued(InvoiceOverdueMail::class, function (InvoiceOverdueMail $mail) use ($overdueInvoice, $client): bool {
        return $mail->hasTo($client->email) && $mail->invoice->id === $overdueInvoice->id;
    });

    Mail::assertQueuedCount(1);
});

it('supports dry run mode without modifying database or sending mail', function (): void {
    Mail::fake();

    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Sent,
        'due_date' => Carbon::today()->subDays(3),
        'total_cents' => 5000,
        'paid_cents' => 0,
    ]);

    $this->artisan('invoices:check-overdue --dry-run')
        ->expectsOutputToContain('Dry-run mode: no changes were committed.')
        ->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->fresh()->overdue_reminder_sent_at)->toBeNull();

    Mail::assertNothingQueued();
});
