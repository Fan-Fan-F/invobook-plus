<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceOverdueMail;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

final class CheckOverdueInvoicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:check-overdue {--dry-run : Only list overdue invoices without updating or sending emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan invoices, mark overdue records, and send reminder emails to clients';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();
        $isDryRun = (bool) $this->option('dry-run');

        /** @var \Illuminate\Database\Eloquent\Collection<int, Invoice> $overdueInvoices */
        $overdueInvoices = Invoice::query()
            ->with('client')
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue])
            ->where('due_date', '<', $today)
            ->whereColumn('paid_cents', '<', 'total_cents')
            ->get();

        if ($overdueInvoices->isEmpty()) {
            $this->info('No overdue invoices found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d overdue invoice(s).', $overdueInvoices->count()));

        $updatedCount = 0;
        $remindedCount = 0;

        foreach ($overdueInvoices as $invoice) {
            $this->line(sprintf(
                '- Invoice #%s: Client [%s], Due [%s], Outstanding [%s]',
                $invoice->invoice_number,
                $invoice->client !== null ? $invoice->client->name : 'Unknown',
                $invoice->due_date->format('Y-m-d'),
                $invoice->currency->format($invoice->balanceCents())
            ));

            if ($isDryRun) {
                continue;
            }

            if ($invoice->status !== InvoiceStatus::Overdue) {
                $invoice->status = InvoiceStatus::Overdue;
                $invoice->save();
                $updatedCount++;
            }

            if ($invoice->client !== null) {
                Mail::to($invoice->client->email)->send(new InvoiceOverdueMail($invoice));
                $invoice->overdue_reminder_sent_at = Carbon::now();
                $invoice->save();
                $remindedCount++;
            }
        }

        if (! $isDryRun) {
            $this->info(sprintf(
                'Completed: %d invoice(s) marked overdue, %d reminder email(s) sent.',
                $updatedCount,
                $remindedCount
            ));
        } else {
            $this->comment('Dry-run mode: no changes were committed.');
        }

        return self::SUCCESS;
    }
}
