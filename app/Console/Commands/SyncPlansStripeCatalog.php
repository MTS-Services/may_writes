<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Services\StripePlanCatalogService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('plans:sync-stripe-catalog')]
#[Description('Create Stripe Product/Price objects for every active plan from the database (API-only, no Dashboard).')]
class SyncPlansStripeCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(StripePlanCatalogService $catalog): int
    {
        if (! filled(config('cashier.secret'))) {
            $this->error('Set STRIPE_SECRET in your environment (Cashier secret).');

            return self::FAILURE;
        }

        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($plans->isEmpty()) {
            $this->warn('No active plans found.');

            return self::SUCCESS;
        }

        foreach ($plans as $plan) {
            try {
                $catalog->ensureActiveRecurringPriceForPlan($plan);
                $this->info(sprintf(
                    '%s → product %s, price %s',
                    $plan->slug,
                    $plan->fresh()->stripe_product_id,
                    $plan->fresh()->stripe_price_id,
                ));
            } catch (\Throwable $exception) {
                $this->error(sprintf('%s: %s', $plan->slug, $exception->getMessage()));

                return self::FAILURE;
            }
        }

        $this->info('All active plans are synced with Stripe.');

        return self::SUCCESS;
    }
}
