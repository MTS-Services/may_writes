<?php

namespace App\Services;

use App\Models\Customer;

class SubscriptionTrialService
{
    /**
     * @return array{enabled: bool, days: int}
     */
    public function configForFrontend(): array
    {
        $days = $this->trialDays();

        return [
            'enabled' => $this->isEnabled(),
            'days' => $days,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('billing.trial.enabled', true) && $this->trialDays() > 0;
    }

    public function trialDays(): int
    {
        return max(0, (int) config('billing.trial.days', 7));
    }

    public function isEligibleForTrial(?string $email = null, ?string $stripeCustomerId = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($email !== null && $email !== '') {
            $normalizedEmail = strtolower(trim($email));

            if (Customer::query()
                ->where('email', $normalizedEmail)
                ->whereNotNull('trial_used_at')
                ->exists()) {
                return false;
            }
        }

        if ($stripeCustomerId !== null && $stripeCustomerId !== '') {
            $customer = Customer::query()
                ->where('stripe_id', $stripeCustomerId)
                ->first();

            if ($customer?->trial_used_at !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $subscriptionData
     * @return array<string, mixed>
     */
    public function applyTrialToSubscriptionData(array $subscriptionData, ?string $email = null, ?string $stripeCustomerId = null): array
    {
        if (! $this->isEligibleForTrial($email, $stripeCustomerId)) {
            return $subscriptionData;
        }

        $subscriptionData['trial_period_days'] = $this->trialDays();

        return $subscriptionData;
    }
}
