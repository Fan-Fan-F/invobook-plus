<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class AgingReportService
{
    /**
     * Generate Accounts Receivable (AR) Aging Report.
     *
     * @return array{
     *     as_of_date: string,
     *     clients: list<array{
     *         client_id: int,
     *         client_name: string,
     *         currency: string,
     *         current_cents: int,
     *         days_1_30_cents: int,
     *         days_31_60_cents: int,
     *         days_61_90_cents: int,
     *         days_over_90_cents: int,
     *         total_outstanding_cents: int,
     *         invoice_count: int,
     *         formatted_total: string
     *     }>,
     *     summary: array{
     *         total_invoices: int,
     *         current_cents: int,
     *         days_1_30_cents: int,
     *         days_31_60_cents: int,
     *         days_61_90_cents: int,
     *         days_over_90_cents: int,
     *         grand_total_cents: int,
     *         formatted_grand_total: string
     *     }
     * }
     */
    public function generate(?CarbonInterface $asOfDate = null): array
    {
        $date = ($asOfDate ?? Carbon::today())->startOfDay();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices */
        $invoices = Invoice::query()
            ->with('client')
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])
            ->whereColumn('paid_cents', '<', 'total_cents')
            ->orderBy('due_date')
            ->get();

        $clientGroups = [];
        $totalInvoices = 0;
        $currentCents = 0;
        $days1to30Cents = 0;
        $days31to60Cents = 0;
        $days61to90Cents = 0;
        $daysOver90Cents = 0;
        $grandTotalCents = 0;

        foreach ($invoices as $invoice) {
            $clientId = $invoice->client_id;
            $clientName = $invoice->client !== null ? $invoice->client->name : 'Unknown Client';
            $currency = $invoice->currency;
            $outstanding = $invoice->balanceCents();

            if (! isset($clientGroups[$clientId])) {
                $clientGroups[$clientId] = [
                    'client_id' => $clientId,
                    'client_name' => $clientName,
                    'currency' => $currency->value,
                    'current_cents' => 0,
                    'days_1_30_cents' => 0,
                    'days_31_60_cents' => 0,
                    'days_61_90_cents' => 0,
                    'days_over_90_cents' => 0,
                    'total_outstanding_cents' => 0,
                    'invoice_count' => 0,
                    'formatted_total' => '',
                ];
            }

            $clientGroups[$clientId]['invoice_count']++;
            $clientGroups[$clientId]['total_outstanding_cents'] += $outstanding;

            $totalInvoices++;
            $grandTotalCents += $outstanding;

            if ($invoice->due_date->gte($date)) {
                $clientGroups[$clientId]['current_cents'] += $outstanding;
                $currentCents += $outstanding;
            } else {
                $daysOverdue = (int) $invoice->due_date->diffInDays($date);

                if ($daysOverdue <= 30) {
                    $clientGroups[$clientId]['days_1_30_cents'] += $outstanding;
                    $days1to30Cents += $outstanding;
                } elseif ($daysOverdue <= 60) {
                    $clientGroups[$clientId]['days_31_60_cents'] += $outstanding;
                    $days31to60Cents += $outstanding;
                } elseif ($daysOverdue <= 90) {
                    $clientGroups[$clientId]['days_61_90_cents'] += $outstanding;
                    $days61to90Cents += $outstanding;
                } else {
                    $clientGroups[$clientId]['days_over_90_cents'] += $outstanding;
                    $daysOver90Cents += $outstanding;
                }
            }
        }

        foreach ($clientGroups as &$group) {
            $currEnum = Currency::from($group['currency']);
            $group['formatted_total'] = $currEnum->format($group['total_outstanding_cents']);
        }
        unset($group);

        $summary = [
            'total_invoices' => $totalInvoices,
            'current_cents' => $currentCents,
            'days_1_30_cents' => $days1to30Cents,
            'days_31_60_cents' => $days31to60Cents,
            'days_61_90_cents' => $days61to90Cents,
            'days_over_90_cents' => $daysOver90Cents,
            'grand_total_cents' => $grandTotalCents,
            'formatted_grand_total' => Currency::USD->format($grandTotalCents),
        ];

        return [
            'as_of_date' => $date->format('Y-m-d'),
            'clients' => array_values($clientGroups),
            'summary' => $summary,
        ];
    }
}
