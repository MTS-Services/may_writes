<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Jobs\OnboardCustomerJob;
use App\Models\Customer;
use App\Models\Plan;
use App\Services\StripePlanCatalogService;
use App\Services\SubscriptionTrialService;
use Illuminate\Support\Facades\Queue;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook as StripeWebhook;

beforeEach(function () {
    config([
        'cashier.secret' => 'sk_test_fake',
        'cashier.webhook.secret' => 'whsec_test_secret',
        'billing.trial.enabled' => true,
        'billing.trial.days' => 7,
    ]);
});

test('plans index includes trial configuration', function () {
    Plan::query()->create([
        'name' => 'Trial Plan',
        'slug' => 'trial-plan-api',
        'stripe_price_id' => 'price_trial_api',
        'price' => 499,
        'active_requests' => 1,
        'features' => ['Feature'],
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson(route('plans.index'));

    $response->assertOk();
    expect($response->json('0.trial'))->toMatchArray([
        'enabled' => true,
        'days' => 7,
    ]);
});

test('checkout session includes trial period when customer is eligible', function () {
    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'trial-checkout-eligible',
        'stripe_price_id' => 'price_trial_checkout',
        'price' => 899,
        'active_requests' => 2,
        'features' => ['Feature'],
        'is_featured' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this->mock(StripePlanCatalogService::class, function ($mock): void {
        $mock->shouldReceive('ensureActiveRecurringPriceForPlan')->andReturnNull();
    });

    $sessionPayload = null;

    Mockery::mock('alias:'.StripeCheckoutSession::class)
        ->shouldReceive('create')
        ->once()
        ->withArgs(function (array $params) use (&$sessionPayload): bool {
            $sessionPayload = $params;

            return ($params['subscription_data']['trial_period_days'] ?? null) === 7;
        })
        ->andReturn((object) [
            'url' => 'https://checkout.stripe.test/session',
            'id' => 'cs_test_trial',
        ]);

    $this->get(route('home'));

    $this->postJson(route('checkout.create'), [
        'plan_id' => $plan->id,
    ], [
        'X-CSRF-TOKEN' => csrf_token(),
    ])->assertOk()->assertJsonPath('checkout_url', 'https://checkout.stripe.test/session');

    expect($sessionPayload)->not->toBeNull()
        ->and($sessionPayload['subscription_data']['trial_period_days'])->toBe(7);
});

test('subscription trial service omits trial when email already used a trial', function () {
    Customer::query()->create([
        'name' => 'Returning',
        'email' => 'returning@example.com',
        'status' => CustomerStatus::Active,
        'trial_used_at' => now()->subMonth(),
    ]);

    $service = app(SubscriptionTrialService::class);

    $data = $service->applyTrialToSubscriptionData(
        ['metadata' => ['plan_id' => '1']],
        'returning@example.com',
    );

    expect($data)->not->toHaveKey('trial_period_days');
});

test('subscription trial service omits trial when disabled in config', function () {
    config(['billing.trial.enabled' => false]);

    $service = app(SubscriptionTrialService::class);

    $data = $service->applyTrialToSubscriptionData(['metadata' => ['plan_id' => '1']]);

    expect($data)->not->toHaveKey('trial_period_days');
});

test('subscription trial service marks ineligible after trial used', function () {
    $service = app(SubscriptionTrialService::class);

    expect($service->isEligibleForTrial('new@example.com'))->toBeTrue();

    Customer::query()->create([
        'name' => 'Used',
        'email' => 'used@example.com',
        'status' => CustomerStatus::Active,
        'trial_used_at' => now(),
    ]);

    expect($service->isEligibleForTrial('used@example.com'))->toBeFalse();
});

test('stripe checkout completed webhook syncs trial fields on customer', function () {
    Queue::fake();

    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'webhook-trial-plan',
        'stripe_price_id' => 'price_webhook_trial',
        'price' => 899,
        'active_requests' => 2,
        'features' => ['Feature'],
        'is_featured' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Mockery::mock('alias:'.StripeSubscription::class)
        ->shouldReceive('retrieve')
        ->once()
        ->with('sub_trial_test')
        ->andReturn((object) [
            'id' => 'sub_trial_test',
            'trial_end' => now()->addDays(7)->timestamp,
        ]);

    $sessionObject = (object) [
        'id' => 'cs_webhook_trial',
        'customer' => 'cus_test_123',
        'subscription' => 'sub_trial_test',
        'metadata' => (object) [
            'plan_id' => (string) $plan->id,
        ],
        'customer_details' => (object) [
            'email' => 'trial-sync@example.com',
            'name' => 'Trial User',
        ],
    ];

    $event = (object) [
        'id' => 'evt_trial_test_1',
        'type' => 'checkout.session.completed',
        'data' => (object) [
            'object' => $sessionObject,
        ],
    ];

    Mockery::mock('alias:'.StripeWebhook::class)
        ->shouldReceive('constructEvent')
        ->once()
        ->andReturn($event);

    $payload = json_encode([
        'id' => 'evt_trial_test_1',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_webhook_trial',
                'customer' => 'cus_test_123',
                'subscription' => 'sub_trial_test',
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                ],
                'customer_details' => [
                    'email' => 'trial-sync@example.com',
                    'name' => 'Trial User',
                ],
            ],
        ],
    ]);

    $this->call(
        'POST',
        route('webhook.stripe'),
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => 'test_signature',
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    )->assertOk();

    $customer = Customer::query()->where('email', 'trial-sync@example.com')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->trial_used_at)->not->toBeNull()
        ->and($customer->trial_ends_at)->not->toBeNull();

    Queue::assertPushed(OnboardCustomerJob::class);
});
