<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\StripePlanCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly StripePlanCatalogService $stripePlanCatalog,
    ) {}

    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer'],
        ]);

        $plan = Plan::query()
            ->where('id', $validated['plan_id'])
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'Selected plan is not available.'], 422);
        }

        if (! filled(config('cashier.secret'))) {
            return response()->json(['message' => 'Payments are not configured.'], 503);
        }

        try {
            $this->stripePlanCatalog->ensureActiveRecurringPriceForPlan($plan);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        $plan->refresh();

        try {
            Stripe::setApiKey((string) config('cashier.secret'));

            $session = Session::create([
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ]],
                'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancel'),
                'allow_promotion_codes' => true,
                'billing_address_collection' => 'required',
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                    'plan_slug' => $plan->slug,
                ],
                'subscription_data' => [
                    'metadata' => [
                        'plan_id' => (string) $plan->id,
                    ],
                ],
            ]);
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe Checkout session failed', [
                'plan_id' => $plan->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['checkout_url' => $session->url]);
    }

    public function success(): Response
    {
        return Inertia::render('public/checkout-success');
    }

    public function cancel(): Response
    {
        return Inertia::render('public/checkout-cancel');
    }

    public function getPlans(): JsonResponse
    {
        $plans = Plan::active()->get();

        return response()->json(
            $plans->map(fn (Plan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (string) $plan->price,
                'active_requests' => $plan->active_requests,
                'features' => $plan->features,
                'is_featured' => $plan->is_featured,
                'is_active' => $plan->is_active,
                'sort_order' => $plan->sort_order,
                'checkout_available' => filled(config('cashier.secret')) && (float) $plan->price > 0,
            ])->values(),
        );
    }
}
