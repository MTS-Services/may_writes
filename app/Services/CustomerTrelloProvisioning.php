<?php

namespace App\Services;

use App\Models\Customer;

class CustomerTrelloProvisioning
{
    public function provisionOnCheckout(): bool
    {
        return (bool) config('billing.trello.provision_on_checkout', true);
    }

    public function isOnboarded(Customer $customer): bool
    {
        return $customer->trello_onboarded_at !== null
            || filled($customer->trello_board_id);
    }

    public function shouldOnboardOnCheckout(?object $subscription): bool
    {
        if ($this->provisionOnCheckout()) {
            return true;
        }

        if ($subscription === null) {
            return true;
        }

        if ($this->subscriptionIsTrialing($subscription)) {
            return false;
        }

        return true;
    }

    public function shouldOnboardOnSubscriptionTransition(
        Customer $customer,
        ?string $previousStatus,
        string $newStatus,
    ): bool {
        if ($this->provisionOnCheckout() || $this->isOnboarded($customer)) {
            return false;
        }

        return $previousStatus === 'trialing' && $newStatus === 'active';
    }

    public function shouldOnboardOnInvoice(Customer $customer, object $invoice): bool
    {
        if ($this->isOnboarded($customer)) {
            return false;
        }

        if ((int) data_get($invoice, 'amount_paid', 0) <= 0) {
            return false;
        }

        if ($this->provisionOnCheckout()) {
            return false;
        }

        $subscriptionId = $this->invoiceSubscriptionId($invoice);

        if ($subscriptionId === null) {
            return false;
        }

        if (
            filled($customer->stripe_subscription_id)
            && $customer->stripe_subscription_id !== $subscriptionId
        ) {
            return false;
        }

        return true;
    }

    private function subscriptionIsTrialing(object $subscription): bool
    {
        $status = (string) data_get($subscription, 'status', '');

        if ($status === 'trialing') {
            return true;
        }

        return data_get($subscription, 'trial_end') !== null;
    }

    private function invoiceSubscriptionId(object $invoice): ?string
    {
        $subscription = data_get($invoice, 'subscription');

        if (filled($subscription)) {
            return (string) $subscription;
        }

        $lineSubscription = data_get($invoice, 'lines.data.0.subscription');

        if (filled($lineSubscription)) {
            return (string) $lineSubscription;
        }

        return null;
    }
}
