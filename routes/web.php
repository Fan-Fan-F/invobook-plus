<?php

declare(strict_types=1);

use App\Actions\RecordPaymentAction;
use App\Actions\TransitionInvoiceStatusAction;
use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Services\AgingReportService;
use App\Services\InvoiceCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('/', fn (): View => view('welcome'));

Route::prefix('api')->name('api.')->group(function (): void {
    // List invoices
    Route::get('/invoices', function (Request $request): JsonResponse {
        $query = Invoice::query()->with(['client', 'items', 'payments'])->latest();

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }

        return response()->json($query->paginate(20));
    })->name('invoices.index');

    // Create invoice
    Route::post('/invoices', function (Request $request, InvoiceCalculator $calculator): JsonResponse {
        /** @var array{
         *     client_id: int,
         *     invoice_number?: string,
         *     issue_date: string,
         *     due_date: string,
         *     currency?: string,
         *     discount_type?: string,
         *     discount_value?: float,
         *     tax_rate?: float,
         *     notes?: string,
         *     items: list<array{description: string, quantity: float, unit_price_cents: int}>
         * } $validated */
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'invoice_number' => ['nullable', 'string', 'unique:invoices,invoice_number'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price_cents' => ['required', 'integer', 'min:0'],
        ]);

        $currency = Currency::tryFrom($validated['currency'] ?? '') ?? Currency::USD;
        $discountType = DiscountType::tryFrom($validated['discount_type'] ?? '') ?? DiscountType::None;
        $discountValue = (float) ($validated['discount_value'] ?? 0.0);
        $taxRate = (float) ($validated['tax_rate'] ?? 0.0);

        $calculation = $calculator->calculate(
            $validated['items'],
            $discountType,
            $discountValue,
            $taxRate,
            $currency
        );

        $invoice = DB::transaction(function () use ($validated, $currency, $discountType, $discountValue, $taxRate, $calculation): Invoice {
            $invoiceNumber = $validated['invoice_number'] ?? 'INV-'.Carbon::now()->format('Ymd').'-'.mb_strtoupper(bin2hex(random_bytes(2)));

            /** @var Invoice $inv */
            $inv = Invoice::query()->create([
                'client_id' => $validated['client_id'],
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceStatus::Draft,
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'currency' => $currency,
                'subtotal_cents' => $calculation['subtotal_cents'],
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_cents' => $calculation['discount_cents'],
                'tax_rate' => $taxRate,
                'tax_cents' => $calculation['tax_cents'],
                'total_cents' => $calculation['total_cents'],
                'paid_cents' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($calculation['items'] as $item) {
                $inv->items()->create($item);
            }

            return $inv;
        });

        $invoice->load(['client', 'items']);

        return response()->json($invoice, 201);
    })->name('invoices.store');

    // Show single invoice
    Route::get('/invoices/{invoice}', function (Invoice $invoice): JsonResponse {
        $invoice->load(['client', 'items', 'payments']);

        return response()->json([
            'invoice' => $invoice,
            'balance_cents' => $invoice->balanceCents(),
            'formatted_balance' => $invoice->currency->format($invoice->balanceCents()),
            'is_overdue' => $invoice->isOverdue(),
            'is_fully_paid' => $invoice->isFullyPaid(),
        ]);
    })->name('invoices.show');

    // Send invoice
    Route::post('/invoices/{invoice}/send', function (Invoice $invoice, TransitionInvoiceStatusAction $action): JsonResponse {
        $action->execute($invoice, InvoiceStatus::Sent);

        return response()->json([
            'message' => 'Invoice sent successfully.',
            'invoice' => $invoice->fresh(['client']),
        ]);
    })->name('invoices.send');

    // Record payment
    Route::post('/invoices/{invoice}/payments', function (Request $request, Invoice $invoice, RecordPaymentAction $action): JsonResponse {
        /** @var array{
         *     amount_cents: int,
         *     payment_method?: string,
         *     payment_date?: string,
         *     reference?: string,
         *     notes?: string
         * } $validated */
        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payment_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $paymentMethod = PaymentMethod::tryFrom($validated['payment_method'] ?? '') ?? PaymentMethod::BankTransfer;
        $paymentDate = isset($validated['payment_date']) ? Carbon::parse($validated['payment_date']) : null;

        $payment = $action->execute(
            $invoice,
            $validated['amount_cents'],
            $paymentMethod,
            $paymentDate,
            $validated['reference'] ?? null,
            $validated['notes'] ?? null
        );

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'payment' => $payment,
            'invoice' => $invoice->fresh(['payments', 'client']),
        ], 201);
    })->name('invoices.payments.store');

    // Aging report
    Route::get('/reports/aging', function (Request $request, AgingReportService $service): JsonResponse {
        $asOfDate = $request->has('as_of_date')
            ? Carbon::parse((string) $request->query('as_of_date'))
            : null;

        return response()->json($service->generate($asOfDate));
    })->name('reports.aging');
});
