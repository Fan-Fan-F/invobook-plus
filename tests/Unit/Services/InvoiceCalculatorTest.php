<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceCalculator;

beforeEach(function (): void {
    $this->calculator = new InvoiceCalculator;
});

it('calculates line items and subtotal accurately in integer cents', function (): void {
    $items = [
        ['description' => 'Web Design', 'quantity' => 2, 'unit_price_cents' => 5000],   // 10000
        ['description' => 'Hosting', 'quantity' => 1.5, 'unit_price_cents' => 4000],    // 6000
    ];

    $result = $this->calculator->calculate($items);

    expect($result['subtotal_cents'])->toBe(16000)
        ->and($result['discount_cents'])->toBe(0)
        ->and($result['tax_cents'])->toBe(0)
        ->and($result['total_cents'])->toBe(16000)
        ->and($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['total_cents'])->toBe(10000)
        ->and($result['items'][1]['total_cents'])->toBe(6000);
});

it('applies percentage discount correctly with cent rounding', function (): void {
    $items = [
        ['description' => 'Consulting', 'quantity' => 1, 'unit_price_cents' => 9999], // $99.99
    ];

    // 15% discount on 9999 cents = 1499.85 => 1500 cents
    $result = $this->calculator->calculate(
        items: $items,
        discountType: DiscountType::Percentage,
        discountValue: 15.0
    );

    expect($result['subtotal_cents'])->toBe(9999)
        ->and($result['discount_cents'])->toBe(1500)
        ->and($result['total_cents'])->toBe(8499);
});

it('applies fixed discount with bounds protection', function (): void {
    $items = [
        ['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => 5000],
    ];

    // Fixed discount larger than subtotal
    $result = $this->calculator->calculate(
        items: $items,
        discountType: DiscountType::Fixed,
        discountValue: 8000
    );

    expect($result['discount_cents'])->toBe(5000)
        ->and($result['taxable_cents'])->toBe(0)
        ->and($result['total_cents'])->toBe(0);
});

it('calculates tax based on discounted taxable amount', function (): void {
    $items = [
        ['description' => 'Hardware', 'quantity' => 1, 'unit_price_cents' => 10000], // 100.00
    ];

    // Subtotal 10000, 10% discount = 1000 => Taxable 9000
    // Tax 13% of 9000 = 1170
    // Total = 9000 + 1170 = 10170
    $result = $this->calculator->calculate(
        items: $items,
        discountType: DiscountType::Percentage,
        discountValue: 10.0,
        taxRate: 13.0
    );

    expect($result['subtotal_cents'])->toBe(10000)
        ->and($result['discount_cents'])->toBe(1000)
        ->and($result['taxable_cents'])->toBe(9000)
        ->and($result['tax_cents'])->toBe(1170)
        ->and($result['total_cents'])->toBe(10170);
});

it('formats money for zero-decimal currencies like JPY vs standard currencies like USD and EUR', function (): void {
    $items = [
        ['description' => 'Software License', 'quantity' => 1, 'unit_price_cents' => 50000],
    ];

    $usd = $this->calculator->calculate($items, currency: Currency::USD);
    $jpy = $this->calculator->calculate($items, currency: Currency::JPY);
    $eur = $this->calculator->calculate($items, currency: Currency::EUR);

    expect($usd['formatted_total'])->toBe('$500.00')
        ->and($eur['formatted_total'])->toBe('€500.00')
        ->and($jpy['formatted_total'])->toBe('¥50,000');
});

it('recalculates an existing invoice model and its totals', function (): void {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 500,
        'tax_rate' => 10.0,
        'currency' => Currency::USD,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => 2,
        'unit_price_cents' => 3000, // 6000
        'total_cents' => 6000,
    ]);

    $recalculated = $this->calculator->recalculateInvoice($invoice);

    // subtotal = 6000, discount = 500, taxable = 5500, tax 10% = 550, total = 6050
    expect($recalculated->subtotal_cents)->toBe(6000)
        ->and($recalculated->discount_cents)->toBe(500)
        ->and($recalculated->tax_cents)->toBe(550)
        ->and($recalculated->total_cents)->toBe(6050);
});
