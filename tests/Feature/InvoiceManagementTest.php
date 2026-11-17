<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;

it('creates an invoice with line items and calculated totals via API', function (): void {
    $client = Client::factory()->create(['currency' => Currency::USD]);

    $payload = [
        'client_id' => $client->id,
        'invoice_number' => 'INV-2026-TEST-001',
        'issue_date' => '2026-11-01',
        'due_date' => '2026-11-30',
        'currency' => 'USD',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'tax_rate' => 5,
        'notes' => 'Thank you for your business.',
        'items' => [
            ['description' => 'Development Service', 'quantity' => 10, 'unit_price_cents' => 10000], // 100,000 cents
            ['description' => 'Maintenance Support', 'quantity' => 1, 'unit_price_cents' => 20000],  // 20,000 cents
        ],
    ];

    $response = $this->postJson(route('api.invoices.store'), $payload);

    // subtotal = 120,000 cents ($1,200.00)
    // 10% discount = 12,000 cents
    // taxable = 108,000 cents
    // 5% tax = 5,400 cents
    // total = 113,400 cents ($1,134.00)
    $response->assertCreated()
        ->assertJsonPath('invoice_number', 'INV-2026-TEST-001')
        ->assertJsonPath('status', 'draft')
        ->assertJsonPath('subtotal_cents', 120000)
        ->assertJsonPath('discount_cents', 12000)
        ->assertJsonPath('tax_cents', 5400)
        ->assertJsonPath('total_cents', 113400)
        ->assertJsonPath('paid_cents', 0);

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => 'INV-2026-TEST-001',
        'total_cents' => 113400,
    ]);

    $this->assertDatabaseCount('invoice_items', 2);
});

it('shows invoice details with calculated balance and overdue flag', function (): void {
    $invoice = Invoice::factory()->create([
        'total_cents' => 50000,
        'paid_cents' => 15000,
        'status' => InvoiceStatus::Sent,
    ]);

    $response = $this->getJson(route('api.invoices.show', $invoice));

    $response->assertOk()
        ->assertJsonPath('balance_cents', 35000)
        ->assertJsonPath('formatted_balance', '$350.00')
        ->assertJsonPath('is_fully_paid', false);
});

it('sends an invoice transition from draft to sent', function (): void {
    $invoice = Invoice::factory()->create([
        'status' => InvoiceStatus::Draft,
    ]);

    $response = $this->postJson(route('api.invoices.send', $invoice));

    $response->assertOk()
        ->assertJsonPath('invoice.status', 'sent');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('lists invoices with status and client filters', function (): void {
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();

    Invoice::factory()->count(2)->create(['client_id' => $clientA->id, 'status' => InvoiceStatus::Sent]);
    Invoice::factory()->count(1)->create(['client_id' => $clientB->id, 'status' => InvoiceStatus::Draft]);

    $response = $this->getJson(route('api.invoices.index', ['client_id' => $clientA->id, 'status' => 'sent']));

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});
