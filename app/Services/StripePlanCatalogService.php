<?php

namespace App\Services;

use App\Models\Plan;
use InvalidArgumentException;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

/**
 * Keeps Stripe Product/Price catalog in sync with local {@link Plan} rows via the Stripe API
 * (same pattern as programmatic Product + Price creation in the Sound_Cloud_Repost_system project).
 *
 * Cashier webhooks and subscriptions still use normal Stripe Price IDs; nothing must be created by hand in the Dashboard.
 */
class StripePlanCatalogService
{
    /**
     * Ensure the plan has an active monthly recurring Stripe Price whose amount matches {@link Plan::$price}.
     *
     * @throws InvalidArgumentException When the plan amount is not billable.
     * @throws RuntimeException When Stripe secret is missing.
     * @throws ApiErrorException When the Stripe API rejects the request.
     */
    public function ensureActiveRecurringPriceForPlan(Plan $plan): void
    {
        $secret = (string) config('cashier.secret');
        if ($secret === '') {
            throw new RuntimeException('Stripe is not configured.');
        }

        Stripe::setApiKey($secret);

        $currency = strtolower((string) config('cashier.currency', 'usd'));
        $expectedCents = (int) round((float) $plan->price * 100);

        if ($expectedCents < 1) {
            throw new InvalidArgumentException('Plan amount must be greater than zero.');
        }

        $this->ensureStripeProduct($plan);

        if ($this->existingPriceMatches($plan, $expectedCents, $currency)) {
            return;
        }

        $this->deactivateManagedPrice($plan);

        $price = Price::create([
            'product' => (string) $plan->stripe_product_id,
            'currency' => $currency,
            'unit_amount' => $expectedCents,
            'recurring' => [
                'interval' => 'month',
            ],
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
            ],
        ]);

        $plan->forceFill([
            'stripe_price_id' => $price->id,
        ])->saveQuietly();
    }

    /**
     * Create the Stripe Product once per plan if missing.
     *
     * @throws ApiErrorException
     */
    private function ensureStripeProduct(Plan $plan): void
    {
        if (filled($plan->stripe_product_id)) {
            return;
        }

        $product = Product::create([
            'name' => $plan->name,
            'metadata' => [
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
            ],
        ]);

        $plan->forceFill([
            'stripe_product_id' => $product->id,
        ])->saveQuietly();
    }

    /**
     * @throws ApiErrorException
     */
    private function existingPriceMatches(Plan $plan, int $expectedCents, string $currency): bool
    {
        $priceId = (string) $plan->stripe_price_id;

        if ($priceId === '' || str_contains($priceId, 'placeholder')) {
            return false;
        }

        if (! str_starts_with($priceId, 'price_')) {
            return false;
        }

        try {
            $price = Price::retrieve($priceId);
        } catch (ApiErrorException) {
            return false;
        }

        if (! $price->active) {
            return false;
        }

        if ((int) $price->unit_amount !== $expectedCents) {
            return false;
        }

        if (strtolower((string) $price->currency) !== $currency) {
            return false;
        }

        $interval = $price->recurring->interval ?? null;

        return $interval === 'month';
    }

    /**
     * @throws ApiErrorException
     */
    private function deactivateManagedPrice(Plan $plan): void
    {
        $priceId = (string) $plan->stripe_price_id;

        if (! str_starts_with($priceId, 'price_')) {
            return;
        }

        try {
            Price::update($priceId, [
                'active' => false,
            ]);
        } catch (ApiErrorException) {
            // Older placeholder strings or deleted remote prices are ignored.
        }
    }
}
