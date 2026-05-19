<?php

namespace App\Services;

use App\Models\Customer;

class CustomerTrelloOffboarding
{
    public function wasOnboarded(Customer $customer): bool
    {
        return $customer->trello_onboarded_at !== null
            || filled($customer->trello_board_id);
    }

    public function isOffboarded(Customer $customer): bool
    {
        return $customer->trello_offboarded_at !== null;
    }

    public function shouldOffboardOnSubscriptionUpdated(Customer $customer, object $subscription): bool
    {
        if (! $this->wasOnboarded($customer) || $this->isOffboarded($customer)) {
            return false;
        }

        if ((bool) data_get($subscription, 'cancel_at_period_end', false)) {
            return false;
        }

        $status = (string) data_get($subscription, 'status', '');

        return in_array($status, ['canceled', 'unpaid'], true);
    }

    public function shouldOffboardOnSubscriptionDeleted(Customer $customer): bool
    {
        return $this->wasOnboarded($customer) && ! $this->isOffboarded($customer);
    }
}
