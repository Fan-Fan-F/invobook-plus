<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $invoice_number
 * @property int $client_id
 * @property InvoiceStatus $status
 * @property CarbonInterface $issue_date
 * @property CarbonInterface $due_date
 * @property Currency $currency
 * @property int $subtotal_cents
 * @property DiscountType $discount_type
 * @property float $discount_value
 * @property int $discount_cents
 * @property float $tax_rate
 * @property int $tax_cents
 * @property int $total_cents
 * @property int $paid_cents
 * @property ?string $notes
 * @property ?CarbonInterface $overdue_reminder_sent_at
 * @property ?Client $client
 */
final class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_number',
        'client_id',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'subtotal_cents',
        'discount_type',
        'discount_value',
        'discount_cents',
        'tax_rate',
        'tax_cents',
        'total_cents',
        'paid_cents',
        'notes',
        'overdue_reminder_sent_at',
    ];

    /**
     * Get the client that owns the invoice.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the items for the invoice.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the payments recorded for the invoice.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Calculate outstanding balance in cents.
     */
    public function balanceCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    /**
     * Determine if invoice is overdue.
     */
    public function isOverdue(?CarbonInterface $relativeTo = null): bool
    {
        $date = $relativeTo ?? Carbon::now();

        return ! $this->isFullyPaid() && $this->due_date->lt($date->startOfDay());
    }

    /**
     * Determine if invoice is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->total_cents > 0 && $this->paid_cents >= $this->total_cents;
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'discount_type' => DiscountType::class,
            'currency' => Currency::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'overdue_reminder_sent_at' => 'datetime',
            'subtotal_cents' => 'integer',
            'discount_value' => 'float',
            'discount_cents' => 'integer',
            'tax_rate' => 'float',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
        ];
    }
}
