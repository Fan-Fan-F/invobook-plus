<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $amount_cents
 * @property CarbonInterface $payment_date
 * @property PaymentMethod $payment_method
 * @property ?string $reference
 * @property ?string $notes
 * @property ?Invoice $invoice
 */
final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'amount_cents',
        'payment_date',
        'payment_method',
        'reference',
        'notes',
    ];

    /**
     * Get the invoice that this payment belongs to.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'payment_date' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }
}
