<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class CheckoutController extends Controller
{
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

        if (str_contains($plan->stripe_price_id, 'placeholder')) {
            return response()->json(['message' => 'Stripe price ID is not configured for this plan.'], 422);
        }

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
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['checkout_url' => $session->url]);
    }

    public function success(Request $request): Response
    {
        return Inertia::render('public/checkout-success', [
            'sessionId' => $request->string('session_id')->toString(),
        ]);
    }

    public function cancel(): Response
    {
        return Inertia::render('public/checkout-cancel');
    }

    public function getPlans(): JsonResponse
    {
        return response()->json(
            Plan::active()->get([
                'id',
                'name',
                'slug',
                'price',
                'active_requests',
                'features',
                'is_featured',
                'is_active',
                'sort_order',
            ]),
        );
    }
}
