<?php

use App\Models\Plan;
use Inertia\Testing\AssertableInertia as Assert;

test('terms page loads with version', function () {
    config(['legal.terms_version' => '2026-05-01']);

    $this->get(route('terms.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/terms')
            ->where('termsVersion', '2026-05-01')
            ->has('content'),
        );
});

test('checkout rejects request without terms acceptance', function () {
    config([
        'cashier.secret' => 'sk_test_not_called',
        'legal.terms_version' => '2026-05-01',
    ]);

    $plan = Plan::query()->create([
        'name' => 'Terms Plan',
        'slug' => 'e2e-terms-required',
        'stripe_price_id' => 'price_terms_placeholder',
        'price' => 9.99,
        'active_requests' => 1,
        'features' => ['One feature'],
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 97,
    ]);

    $this->get(route('home'));

    $this->postJson(route('checkout.create'), [
        'plan_id' => $plan->id,
    ], [
        'X-CSRF-TOKEN' => csrf_token(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['accepted_terms', 'terms_version']);
});

test('checkout rejects request with mismatched terms version', function () {
    config([
        'cashier.secret' => 'sk_test_not_called',
        'legal.terms_version' => '2026-05-01',
    ]);

    $plan = Plan::query()->create([
        'name' => 'Terms Plan',
        'slug' => 'e2e-terms-version',
        'stripe_price_id' => 'price_terms_version_placeholder',
        'price' => 9.99,
        'active_requests' => 1,
        'features' => ['One feature'],
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 96,
    ]);

    $this->get(route('home'));

    $this->postJson(route('checkout.create'), [
        'plan_id' => $plan->id,
        'accepted_terms' => true,
        'terms_version' => '1999-01-01',
    ], [
        'X-CSRF-TOKEN' => csrf_token(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['terms_version']);
});
