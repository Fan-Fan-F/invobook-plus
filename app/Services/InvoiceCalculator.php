<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Models\Invoice;

final class InvoiceCalculator
{
    /**
     * Calculate invoice items, subtotal, discount, tax, and total in integer cents.
     *
     * @param  array<int, array{description: string, quantity: float|int, unit_price_cents: int}>  $items
     * @return array{
     *     items: array<int, array{description: string, quantity: float, unit_price_cents: int, total_cents: int}>,
     *     subtotal_cents: int,
     *     discount_cents: int,
     *     taxable_cents: int,
     *     tax_cents: int,
     *     total_cents: int,
     *     formatted_subtotal: string,
     *     formatted_discount: string,
     *     formatted_tax: string,
     *     formatted_total: string
     * }
     */
    public function calculate(
        array $items,
        DiscountType $discountType = DiscountType::None,
        float $discountValue = 0.0,
        float $taxRate = 0.0,
        Currency $currency = Currency::USD
    ): array {
        $computedItems = [];
        $subtotalCents = 0;

        foreach ($items as $item) {
            $qty = (float) $item['quantity'];
            $unitPrice = (int) $item['unit_price_cents'];
            $itemTotal = (int) round($qty * $unitPrice);

            $computedItems[] = [
                'description' => (string) $item['description'],
                'quantity' => $qty,
                'unit_price_cents' => $unitPrice,
                'total_cents' => $itemTotal,
            ];

            $subtotalCents += $itemTotal;
        }

        $discountCents = match ($discountType) {
            DiscountType::None => 0,
            DiscountType::Percentage => (int) round(($subtotalCents * min(100.0, max(0.0, $discountValue))) / 100.0),
            DiscountType::Fixed => min($subtotalCents, max(0, (int) round($discountValue))),
        };

        $taxableCents = max(0, $subtotalCents - $discountCents);
        $taxCents = (int) round(($taxableCents * max(0.0, $taxRate)) / 100.0);
        $totalCents = $subtotalCents - $discountCents + $taxCents;

        return [
            'items' => $computedItems,
            'subtotal_cents' => $subtotalCents,
            'discount_cents' => $discountCents,
            'taxable_cents' => $taxableCents,
            'tax_cents' => $taxCents,
            'total_cents' => $totalCents,
            'formatted_subtotal' => $currency->format($subtotalCents),
            'formatted_discount' => $currency->format($discountCents),
            'formatted_tax' => $currency->format($taxCents),
            'formatted_total' => $currency->format($totalCents),
        ];
    }

    /**
     * Recalculate an existing invoice instance and optionally persist.
     */
    public function recalculateInvoice(Invoice $invoice, bool $persist = true): Invoice
    {
        $itemsData = $invoice->items->map(fn ($item) => [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price_cents' => $item->unit_price_cents,
        ])->all();

        $calculation = $this->calculate(
            $itemsData,
            $invoice->discount_type,
            $invoice->discount_value,
            $invoice->tax_rate,
            $invoice->currency
        );

        $invoice->subtotal_cents = $calculation['subtotal_cents'];
        $invoice->discount_cents = $calculation['discount_cents'];
        $invoice->tax_cents = $calculation['tax_cents'];
        $invoice->total_cents = $calculation['total_cents'];

        if ($persist) {
            $invoice->save();
        }

        return $invoice;
    }
}
