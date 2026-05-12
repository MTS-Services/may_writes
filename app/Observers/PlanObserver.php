<?php

namespace App\Observers;

use App\Models\Plan;
use App\Services\StripePlanCatalogService;

class PlanObserver
{
    public function __construct(
        private readonly StripePlanCatalogService $stripePlanCatalog,
    ) {}

    /**
     * When an admin updates plan fields from the dashboard, push the new amount to Stripe
     * so the next checkout uses a matching Price (Stripe Prices are immutable for amount).
     */
    public function updated(Plan $plan): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (! filled(config('cashier.secret')) || ! $plan->is_active) {
            return;
        }

        if (! $plan->wasChanged(['price', 'name', 'slug'])) {
            return;
        }

        try {
            $this->stripePlanCatalog->ensureActiveRecurringPriceForPlan($plan->fresh());
        } catch (\Throwable) {
            // Avoid blocking admin saves; checkout will attempt sync again.
        }
    }
}
