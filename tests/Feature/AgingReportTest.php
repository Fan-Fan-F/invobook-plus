<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\AgingReportService;
use Illuminate\Support\Carbon;

it('generates accounts receivable aging report grouped by client and aging buckets', function (): void {
    $clientA = Client::factory()->create(['name' => 'Acme Corp']);
    $clientB = Client::factory()->create(['name' => 'Beta Ltd']);

    $today = Carbon::today();

    // Client A: 1 current invoice ($100), 1 overdue 15 days ($200, half paid -> balance $100)
    Invoice::factory()->create([
        'client_id' => $clientA->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => (clone $today)->addDays(5),
        'total_cents' => 10000,
        'paid_cents' => 0,
    ]);
    Invoice::factory()->create([
        'client_id' => $clientA->id,
        'status' => InvoiceStatus::PartiallyPaid,
        'due_date' => (clone $today)->subDays(15),
        'total_cents' => 20000,
        'paid_cents' => 10000, // balance 10000 (days 1-30)
    ]);

    // Client B: 1 invoice overdue 45 days ($300), 1 invoice overdue 100 days ($500)
    Invoice::factory()->create([
        'client_id' => $clientB->id,
        'status' => InvoiceStatus::Overdue,
        'due_date' => (clone $today)->subDays(45),
        'total_cents' => 30000,
        'paid_cents' => 0, // balance 30000 (days 31-60)
    ]);
    Invoice::factory()->create([
        'client_id' => $clientB->id,
        'status' => InvoiceStatus::Overdue,
        'due_date' => (clone $today)->subDays(100),
        'total_cents' => 50000,
        'paid_cents' => 0, // balance 50000 (days > 90)
    ]);

    // Ignored invoices: Draft or Paid
    Invoice::factory()->create([
        'client_id' => $clientA->id,
        'status' => InvoiceStatus::Draft,
        'total_cents' => 99999,
        'paid_cents' => 0,
    ]);
    Invoice::factory()->create([
        'client_id' => $clientA->id,
        'status' => InvoiceStatus::Paid,
        'total_cents' => 50000,
        'paid_cents' => 50000,
    ]);

    $service = new AgingReportService;
    $report = $service->generate($today);

    expect($report['summary']['total_invoices'])->toBe(4)
        ->and($report['summary']['current_cents'])->toBe(10000)
        ->and($report['summary']['days_1_30_cents'])->toBe(10000)
        ->and($report['summary']['days_31_60_cents'])->toBe(30000)
        ->and($report['summary']['days_61_90_cents'])->toBe(0)
        ->and($report['summary']['days_over_90_cents'])->toBe(50000)
        ->and($report['summary']['grand_total_cents'])->toBe(100000); // $1000.00

    expect($report['clients'])->toHaveCount(2);

    $clientARow = collect($report['clients'])->firstWhere('client_id', $clientA->id);
    expect($clientARow['client_name'])->toBe('Acme Corp')
        ->and($clientARow['current_cents'])->toBe(10000)
        ->and($clientARow['days_1_30_cents'])->toBe(10000)
        ->and($clientARow['total_outstanding_cents'])->toBe(20000)
        ->and($clientARow['invoice_count'])->toBe(2);

    $clientBRow = collect($report['clients'])->firstWhere('client_id', $clientB->id);
    expect($clientBRow['client_name'])->toBe('Beta Ltd')
        ->and($clientBRow['days_31_60_cents'])->toBe(30000)
        ->and($clientBRow['days_over_90_cents'])->toBe(50000)
        ->and($clientBRow['total_outstanding_cents'])->toBe(80000)
        ->and($clientBRow['invoice_count'])->toBe(2);
});

it('exposes aging report via API endpoint', function (): void {
    $client = Client::factory()->create(['name' => 'Demo Customer']);
    Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => InvoiceStatus::Sent,
        'due_date' => Carbon::today()->addDays(2),
        'total_cents' => 12500,
        'paid_cents' => 0,
    ]);

    $response = $this->getJson(route('api.reports.aging'));

    $response->assertOk()
        ->assertJsonPath('summary.total_invoices', 1)
        ->assertJsonPath('summary.current_cents', 12500)
        ->assertJsonPath('summary.grand_total_cents', 12500);
});
