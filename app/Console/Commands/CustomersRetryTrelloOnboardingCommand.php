<?php

namespace App\Console\Commands;

use App\Enums\TrelloOnboardingStatus;
use App\Jobs\OnboardCustomerJob;
use App\Models\Customer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('customers:retry-trello-onboarding {identifier : Customer database ID or billing email}')]
#[Description('Re-dispatch Trello onboarding for a customer who has not completed trello_onboarded_at')]
class CustomersRetryTrelloOnboardingCommand extends Command
{
    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');

        $customer = ctype_digit($identifier)
            ? Customer::query()->find((int) $identifier)
            : Customer::query()->where('email', strtolower(trim($identifier)))->first();

        if (! $customer) {
            $this->error('Customer not found.');

            return self::FAILURE;
        }

        if ($customer->trello_onboarded_at !== null) {
            $this->error('Customer already has trello_onboarded_at set; refusing to duplicate onboarding.');

            return self::FAILURE;
        }

        $customer->update([
            'trello_onboarding_status' => TrelloOnboardingStatus::Pending,
            'trello_onboarding_last_error' => null,
        ]);

        OnboardCustomerJob::dispatch($customer)->onQueue('default');

        $this->info("Queued OnboardCustomerJob for customer #{$customer->id} ({$customer->email}).");

        return self::SUCCESS;
    }
}
