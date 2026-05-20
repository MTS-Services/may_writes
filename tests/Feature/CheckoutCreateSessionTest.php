<?php

use App\Models\Plan;
use Inertia\Testing\AssertableInertia as Assert;

test('checkout success page loads', function () {
    config(['billing.support.checkout_followup_minutes' => 20]);

    $this->get(route('checkout.success', ['session_id' => 'cs_test_fake']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/checkout-success')
            ->where('checkoutFollowupMinutes', 20),
        );
});

test('checkout cancel page loads', function () {
    $this->get(route('checkout.cancel'))
        ->assertOk();
});

test('checkout returns service unavailable when stripe secret is missing', function () {
    config(['cashier.secret' => null]);

    $plan = Plan::query()->create([
        'name' => 'E2E Plan',
        'slug' => 'e2e-no-stripe',
        'stripe_price_id' => 'price_e2e_no_stripe_placeholder',
        'price' => 9.99,
        'active_requests' => 1,
        'features' => ['One feature'],
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 99,
    ]);

    $this->get(route('home'));

    $this->postJson(route('checkout.create'), [
        'plan_id' => $plan->id,
    ], [
        'X-CSRF-TOKEN' => csrf_token(),
    ])
        ->assertStatus(503)
        ->assertJsonFragment([
            'message' => 'Payments are not configured.',
        ]);
});

test('checkout returns unprocessable when plan price is zero', function () {
    config(['cashier.secret' => 'sk_test_not_called']);

    $plan = Plan::query()->create([
        'name' => 'Zero Plan',
        'slug' => 'e2e-zero-price',
        'stripe_price_id' => 'price_e2e_zero_placeholder',
        'price' => 0,
        'active_requests' => 1,
        'features' => ['One feature'],
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 98,
    ]);

    $this->get(route('home'));

    $this->postJson(route('checkout.create'), [
        'plan_id' => $plan->id,
    ], [
        'X-CSRF-TOKEN' => csrf_token(),
    ])
        ->assertUnprocessable()
        ->assertJsonFragment([
            'message' => 'Plan amount must be greater than zero.',
        ]);
});

test('plans index exposes checkout availability without exposing stripe price ids', function () {
    config(['cashier.secret' => 'sk_test_configured']);

    Plan::query()->create([
        'name' => 'E2E Plan',
        'slug' => 'e2e-stripe-flag',
        'stripe_price_id' => 'price_live_abc123',
        'price' => 9.99,
        'active_requests' => 1,
        'features' => ['One feature'],
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson(route('plans.index'));

    $response->assertOk();
    $data = $response->json();
    expect($data)->toHaveCount(1)
        ->and($data[0])->toHaveKey('checkout_available')
        ->and($data[0]['checkout_available'])->toBeTrue()
        ->and($data[0])->not->toHaveKey('stripe_price_id');
});

test('plans index marks checkout unavailable when stripe secret is missing', function () {
    config(['cashier.secret' => null]);

    Plan::query()->create([
        'name' => 'Plan A',
        'slug' => 'e2e-unavailable-checkout',
        'stripe_price_id' => 'price_real_looking_id',
        'price' => 4.99,
        'active_requests' => 1,
        'features' => ['One feature'],
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson(route('plans.index'));

    $response->assertOk();
    expect($response->json('0.checkout_available'))->toBeFalse();
});
