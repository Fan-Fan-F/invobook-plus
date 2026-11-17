<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\DiscountType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Invoice>
     */
    protected $model = Invoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = Carbon::now()->subDays(fake()->numberBetween(1, 30));
        $dueDate = (clone $issueDate)->addDays(30);

        return [
            'invoice_number' => 'INV-'.fake()->unique()->numerify('####-####'),
            'client_id' => Client::factory(),
            'status' => InvoiceStatus::Draft,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'currency' => Currency::USD,
            'subtotal_cents' => 10000,
            'discount_type' => DiscountType::None,
            'discount_value' => 0,
            'discount_cents' => 0,
            'tax_rate' => 0,
            'tax_cents' => 0,
            'total_cents' => 10000,
            'paid_cents' => 0,
            'notes' => fake()->sentence(),
            'overdue_reminder_sent_at' => null,
        ];
    }

    /**
     * State for sent status.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Sent,
        ]);
    }

    /**
     * State for overdue invoice.
     */
    public function overdue(int $daysOverdue = 10): static
    {
        $issue = Carbon::now()->subDays($daysOverdue + 30);
        $due = Carbon::now()->subDays($daysOverdue);

        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Overdue,
            'issue_date' => $issue,
            'due_date' => $due,
        ]);
    }

    /**
     * State for paid invoice.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'paid_cents' => $attributes['total_cents'] ?? 10000,
        ]);
    }
}
