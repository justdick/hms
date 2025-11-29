<?php

namespace Database\Factories;

use App\Models\Charge;
use App\Models\Patient;
use App\Models\PaymentAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentAuditLog>
 */
class PaymentAuditLogFactory extends Factory
{
    protected $model = PaymentAuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'charge_id' => Charge::factory(),
            'patient_id' => Patient::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                PaymentAuditLog::ACTION_PAYMENT,
                PaymentAuditLog::ACTION_VOID,
                PaymentAuditLog::ACTION_REFUND,
                PaymentAuditLog::ACTION_RECEIPT_PRINTED,
            ]),
            'old_values' => null,
            'new_values' => null,
            'reason' => fake()->optional()->sentence(),
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function payment(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => PaymentAuditLog::ACTION_PAYMENT,
            'old_values' => ['status' => 'pending', 'paid_amount' => 0],
            'new_values' => ['status' => 'paid', 'paid_amount' => fake()->randomFloat(2, 10, 500)],
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => PaymentAuditLog::ACTION_VOID,
            'old_values' => ['status' => 'paid'],
            'new_values' => ['status' => 'voided'],
            'reason' => fake()->sentence(),
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => PaymentAuditLog::ACTION_REFUND,
            'old_values' => ['status' => 'paid', 'paid_amount' => fake()->randomFloat(2, 10, 500)],
            'new_values' => ['status' => 'refunded', 'refund_amount' => fake()->randomFloat(2, 10, 500)],
            'reason' => fake()->sentence(),
        ]);
    }

    public function receiptPrinted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => PaymentAuditLog::ACTION_RECEIPT_PRINTED,
            'new_values' => [
                'receipt_number' => 'RCP-'.now()->format('Ymd').'-'.str_pad(fake()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
                'printed_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function override(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => PaymentAuditLog::ACTION_OVERRIDE,
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'owing'],
            'reason' => fake()->sentence(),
        ]);
    }

    public function creditTagAdded(): static
    {
        return $this->state(fn (array $attributes) => [
            'charge_id' => null,
            'action' => PaymentAuditLog::ACTION_CREDIT_TAG_ADDED,
            'new_values' => ['is_credit_eligible' => true],
            'reason' => fake()->sentence(),
        ]);
    }

    public function creditTagRemoved(): static
    {
        return $this->state(fn (array $attributes) => [
            'charge_id' => null,
            'action' => PaymentAuditLog::ACTION_CREDIT_TAG_REMOVED,
            'new_values' => ['is_credit_eligible' => false],
            'reason' => fake()->sentence(),
        ]);
    }
}
