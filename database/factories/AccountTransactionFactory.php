<?php

namespace Database\Factories;

use App\Models\AccountTransaction;
use App\Models\PatientAccount;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountTransaction>
 */
class AccountTransactionFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 1000);

        return [
            'patient_account_id' => PatientAccount::factory(),
            'type' => AccountTransaction::TYPE_DEPOSIT,
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'description' => 'Deposit',
            'processed_by' => User::factory(),
            'transacted_at' => now(),
        ];
    }

    public function deposit(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountTransaction::TYPE_DEPOSIT,
            'amount' => $amount,
            'description' => 'Deposit',
        ]);
    }

    public function chargeDeduction(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountTransaction::TYPE_CHARGE_DEDUCTION,
            'amount' => -abs($amount),
            'description' => 'Charge deduction',
        ]);
    }

    public function payment(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => AccountTransaction::TYPE_PAYMENT,
            'amount' => $amount,
            'payment_method_id' => PaymentMethod::factory(),
            'description' => 'Payment received',
        ]);
    }
}
