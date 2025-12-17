<?php

use App\Models\AccountTransaction;
use App\Models\Charge;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\PatientCheckin;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\PatientAccountService;

beforeEach(function () {
    $this->accountService = app(PatientAccountService::class);
});

it('creates account with auto-generated number', function () {
    $patient = Patient::factory()->create();

    $account = $this->accountService->getOrCreateAccount($patient);

    expect($account->account_number)->toStartWith('ACC'.now()->format('Y'));
    expect($account->balance)->toBe('0.00');
    expect($account->credit_limit)->toBe('0.00');
});

it('deposits funds into patient account', function () {
    $patient = Patient::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $user = User::factory()->create();

    $transaction = $this->accountService->deposit(
        patient: $patient,
        amount: 500.00,
        paymentMethodId: $paymentMethod->id,
        processedBy: $user,
    );

    expect($transaction->type)->toBe(AccountTransaction::TYPE_DEPOSIT);
    expect($transaction->amount)->toBe('500.00');
    expect($transaction->balance_after)->toBe('500.00');

    $patient->refresh();
    expect($patient->account->balance)->toBe('500.00');
});

it('applies account balance to charge', function () {
    $patient = Patient::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $user = User::factory()->create();
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);

    // Deposit funds first
    $this->accountService->deposit($patient, 500.00, $paymentMethod->id, $user);

    // Create charge (observer will auto-apply)
    $charge = Charge::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 200,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $charge->refresh();
    $patient->refresh();

    expect($charge->paid_amount)->toBe('200.00');
    expect($charge->status)->toBe('paid');
    expect($patient->account->balance)->toBe('300.00');
});

it('credit limit allows charges without auto-deduction', function () {
    $patient = Patient::factory()->create();
    $user = User::factory()->create();
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);

    // Set credit limit (no deposit) - this allows services but does NOT auto-pay
    $this->accountService->setCreditLimit($patient, 1000.00, $user, 'VIP customer');

    // Create charge - should NOT auto-deduct from credit
    $charge = Charge::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 300,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $charge->refresh();
    $patient->refresh();

    // Charge remains unpaid - credit doesn't auto-deduct
    expect($charge->paid_amount)->toBe('0.00');
    expect($charge->status)->toBe('pending');
    expect($patient->account->balance)->toBe('0.00'); // Balance unchanged
    expect($patient->account->amount_owed)->toBe(0.0); // No owing yet

    // But patient CAN receive services (credit privilege)
    expect($patient->account->canReceiveServices(300))->toBeTrue();
    expect($patient->account->remaining_credit)->toBe(1000.0);
});

it('only deducts from deposit not credit limit', function () {
    $patient = Patient::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $user = User::factory()->create();
    $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);

    // Deposit 200 and set credit limit 300
    $this->accountService->deposit($patient, 200.00, $paymentMethod->id, $user);
    $this->accountService->setCreditLimit($patient, 300.00, $user, 'Approved');

    $patient->refresh();
    expect($patient->account->deposit_balance)->toBe(200.0);
    expect($patient->account->credit_limit)->toBe('300.00');

    // Create charge for 400
    $charge = Charge::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'amount' => 400,
        'paid_amount' => 0,
        'status' => 'pending',
    ]);

    $charge->refresh();
    $patient->refresh();

    // Only deposit (200) is auto-deducted, NOT credit
    expect($charge->paid_amount)->toBe('200.00');
    expect($charge->status)->toBe('pending'); // Still pending, 200 remaining
    expect($patient->account->balance)->toBe('0.00'); // Deposit used up
    expect($patient->account->deposit_balance)->toBe(0.0);

    // Patient can still receive services due to credit privilege
    expect($patient->account->canReceiveServices(200))->toBeTrue();
});

it('sets credit limit with authorization', function () {
    $patient = Patient::factory()->create();
    $admin = User::factory()->create();

    $account = $this->accountService->setCreditLimit($patient, 5000.00, $admin, 'Corporate account');

    expect($account->credit_limit)->toBe('5000.00');
    expect($account->credit_authorized_by)->toBe($admin->id);
    expect($account->credit_reason)->toBe('Corporate account');
    expect($account->hasCreditPrivilege())->toBeTrue();
});

it('processes payment to reduce owing balance', function () {
    $patient = Patient::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $user = User::factory()->create();

    // Create account in debt
    PatientAccount::factory()->create([
        'patient_id' => $patient->id,
        'balance' => -500,
        'credit_limit' => 1000,
    ]);

    $transaction = $this->accountService->processPayment(
        patient: $patient,
        amount: 300.00,
        paymentMethodId: $paymentMethod->id,
        processedBy: $user,
    );

    expect($transaction->type)->toBe(AccountTransaction::TYPE_PAYMENT);
    expect($transaction->balance_after)->toBe('-200.00');

    $patient->refresh();
    expect($patient->account->balance)->toBe('-200.00');
    expect($patient->account->amount_owed)->toBe(200.0);
});

it('returns account summary', function () {
    $patient = Patient::factory()->create();
    $admin = User::factory()->create();

    PatientAccount::factory()->create([
        'patient_id' => $patient->id,
        'balance' => 500,
        'credit_limit' => 1000,
        'credit_authorized_by' => $admin->id,
        'credit_reason' => 'VIP',
    ]);

    $summary = $this->accountService->getAccountSummary($patient);

    expect($summary['has_account'])->toBeTrue();
    expect($summary['balance'])->toBe(500.0);
    expect($summary['credit_limit'])->toBe(1000.0);
    expect($summary['amount_owed'])->toBe(0.0);
    expect($summary['has_credit_privilege'])->toBeTrue();
});

it('tracks transaction history', function () {
    $patient = Patient::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $user = User::factory()->create();

    $this->accountService->deposit($patient, 100.00, $paymentMethod->id, $user);
    $this->accountService->deposit($patient, 200.00, $paymentMethod->id, $user);

    $history = $this->accountService->getTransactionHistory($patient);

    expect($history)->toHaveCount(2);
    expect($history[0]['amount'])->toBe('200.00'); // Most recent first
    expect($history[1]['amount'])->toBe('100.00');
});

it('makes credit adjustment to patient account', function () {
    $patient = Patient::factory()->create();
    $user = User::factory()->create();

    PatientAccount::factory()->create([
        'patient_id' => $patient->id,
        'balance' => 100,
        'credit_limit' => 0,
    ]);

    $transaction = $this->accountService->makeAdjustment(
        patient: $patient,
        amount: 50.00,
        processedBy: $user,
        reason: 'Goodwill credit for service issue',
    );

    expect($transaction->type)->toBe(AccountTransaction::TYPE_ADJUSTMENT);
    expect($transaction->amount)->toBe('50.00');
    expect($transaction->balance_after)->toBe('150.00');
    expect($transaction->notes)->toBe('Goodwill credit for service issue');

    $patient->refresh();
    expect($patient->account->balance)->toBe('150.00');
});

it('makes debit adjustment to patient account', function () {
    $patient = Patient::factory()->create();
    $user = User::factory()->create();

    PatientAccount::factory()->create([
        'patient_id' => $patient->id,
        'balance' => 200,
        'credit_limit' => 0,
    ]);

    $transaction = $this->accountService->makeAdjustment(
        patient: $patient,
        amount: -75.00,
        processedBy: $user,
        reason: 'Correction for billing error',
    );

    expect($transaction->type)->toBe(AccountTransaction::TYPE_ADJUSTMENT);
    expect($transaction->amount)->toBe('-75.00');
    expect($transaction->balance_after)->toBe('125.00');

    $patient->refresh();
    expect($patient->account->balance)->toBe('125.00');
});

it('sets unlimited credit limit', function () {
    $patient = Patient::factory()->create();
    $admin = User::factory()->create();

    $account = $this->accountService->setCreditLimit($patient, 999999999, $admin, 'VIP unlimited credit');

    expect($account->credit_limit)->toBe('999999999.00');
    expect($account->hasCreditPrivilege())->toBeTrue();
    expect($account->remaining_credit)->toBe(999999999.0);
    expect($account->canReceiveServices(1000000))->toBeTrue();
});
