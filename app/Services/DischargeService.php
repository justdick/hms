<?php

namespace App\Services;

use App\Models\BillingConfiguration;
use App\Models\PatientAdmission;
use App\Models\User;

/**
 * Encapsulates the policy-aware discharge workflow.
 *
 * The hospital can configure how an outstanding balance is handled when a
 * patient is discharged. This service resolves the configured policy and
 * applies the rules consistently (controller and UI query the same source
 * of truth).
 */
class DischargeService
{
    public const POLICY_ALLOW = 'allow';

    public const POLICY_WARN = 'warn';

    public const POLICY_BLOCK = 'block';

    public const VALID_POLICIES = [
        self::POLICY_ALLOW,
        self::POLICY_WARN,
        self::POLICY_BLOCK,
    ];

    public const OVERRIDE_PERMISSION = 'billing.override-discharge-block';

    /**
     * Resolve the currently configured discharge policy.
     */
    public function getPolicy(): string
    {
        $policy = (string) BillingConfiguration::getValue('admission.discharge_policy', self::POLICY_WARN);

        return in_array($policy, self::VALID_POLICIES, true) ? $policy : self::POLICY_WARN;
    }

    /**
     * Describe the state of a discharge for the given admission and user.
     *
     * Controllers use this to decide whether to allow the discharge, and the
     * UI uses it to adapt the discharge modal (hide acknowledgement, show
     * warning, show override button, etc.).
     *
     * @return array{
     *     policy: string,
     *     outstanding_balance: float,
     *     has_balance: bool,
     *     blocked: bool,
     *     requires_acknowledgement: bool,
     *     can_override: bool,
     * }
     */
    public function evaluate(PatientAdmission $admission, User $user): array
    {
        $policy = $this->getPolicy();
        $balance = (float) $admission->getOutstandingBalance();
        $hasBalance = $balance > 0;

        $canOverride = $user->can(self::OVERRIDE_PERMISSION);

        $blocked = $policy === self::POLICY_BLOCK
            && $hasBalance
            && ! $canOverride;

        $requiresAck = $hasBalance && (
            $policy === self::POLICY_WARN
            || ($policy === self::POLICY_BLOCK && $canOverride)
        );

        return [
            'policy' => $policy,
            'outstanding_balance' => round($balance, 2),
            'has_balance' => $hasBalance,
            'blocked' => $blocked,
            'requires_acknowledgement' => $requiresAck,
            'can_override' => $canOverride,
        ];
    }

    /**
     * Discharge the admission honoring the configured policy.
     *
     * @throws \RuntimeException When the policy blocks the discharge.
     * @throws \InvalidArgumentException When acknowledgement data is missing.
     */
    public function discharge(
        PatientAdmission $admission,
        User $user,
        ?string $notes = null,
        ?string $ackReason = null,
        ?string $ackNote = null,
    ): void {
        $state = $this->evaluate($admission, $user);

        if ($state['blocked']) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot discharge patient with outstanding balance of GHS %s. Please collect payment at billing before discharge.',
                    number_format($state['outstanding_balance'], 2)
                )
            );
        }

        if ($state['requires_acknowledgement'] && empty($ackReason)) {
            throw new \InvalidArgumentException(
                'A reason must be provided when discharging a patient with an outstanding balance.'
            );
        }

        $admission->markAsDischarged(
            dischargedBy: $user,
            notes: $notes,
            outstandingBalance: $state['has_balance'] ? $state['outstanding_balance'] : null,
            ackReason: $state['has_balance'] ? $ackReason : null,
            ackNote: $state['has_balance'] ? $ackNote : null,
        );
    }
}
