<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Jobs\OffboardCustomerJob;
use App\Jobs\OnboardCustomerJob;
use App\Models\Customer;
use App\Models\Plan;
use App\Services\TrelloService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook as StripeWebhook;
use Tests\TestCase;

beforeEach(function () {
    config([
        'cashier.secret' => 'sk_test_fake',
        'cashier.webhook.secret' => 'whsec_test_secret',
        'billing.trello.provision_on_checkout' => true,
    ]);
});

/**
 * @param  array<string, mixed>  $object
 * @param  array<string, mixed>|null  $previousAttributes
 */
function postStripeWebhook(TestCase $test, string $eventId, string $type, array $object, ?array $previousAttributes = null): TestResponse
{
    $data = ['object' => $object];

    if ($previousAttributes !== null) {
        $data['previous_attributes'] = $previousAttributes;
    }

    $event = (object) [
        'id' => $eventId,
        'type' => $type,
        'data' => (object) $data,
    ];

    Mockery::mock('alias:'.StripeWebhook::class)
        ->shouldReceive('constructEvent')
        ->once()
        ->andReturn($event);

    $payload = json_encode([
        'id' => $eventId,
        'type' => $type,
        'data' => $data,
    ]);

    return $test->call(
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
    );
}

test('checkout with provision on checkout true dispatches onboard job for trialing subscription', function () {
    Queue::fake();

    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'trello-lifecycle-trial',
        'stripe_price_id' => 'price_trello_lifecycle',
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
        ->with('sub_trialing')
        ->andReturn((object) [
            'id' => 'sub_trialing',
            'status' => 'trialing',
            'trial_end' => now()->addDays(7)->timestamp,
        ]);

    postStripeWebhook($this, 'evt_checkout_trial_1', 'checkout.session.completed', [
        'id' => 'cs_trello_trial',
        'customer' => 'cus_trello_1',
        'subscription' => 'sub_trialing',
        'metadata' => [
            'plan_id' => (string) $plan->id,
        ],
        'customer_details' => [
            'email' => 'trello-trial@example.com',
            'name' => 'Trial User',
        ],
    ])->assertOk();

    Queue::assertPushed(OnboardCustomerJob::class);
});

test('checkout with provision on checkout false skips onboard for trialing subscription', function () {
    Queue::fake();
    config(['billing.trello.provision_on_checkout' => false]);

    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'trello-defer-trial',
        'stripe_price_id' => 'price_trello_defer',
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
        ->with('sub_defer_trial')
        ->andReturn((object) [
            'id' => 'sub_defer_trial',
            'status' => 'trialing',
            'trial_end' => now()->addDays(7)->timestamp,
        ]);

    postStripeWebhook($this, 'evt_checkout_defer_1', 'checkout.session.completed', [
        'id' => 'cs_defer_trial',
        'customer' => 'cus_defer_1',
        'subscription' => 'sub_defer_trial',
        'metadata' => [
            'plan_id' => (string) $plan->id,
        ],
        'customer_details' => [
            'email' => 'defer-trial@example.com',
            'name' => 'Defer User',
        ],
    ])->assertOk();

    Queue::assertNotPushed(OnboardCustomerJob::class);

    $customer = Customer::query()->where('email', 'defer-trial@example.com')->first();
    expect($customer?->stripe_subscription_id)->toBe('sub_defer_trial');
});

test('invoice paid dispatches onboard when deferred and not yet onboarded', function () {
    Queue::fake();
    config(['billing.trello.provision_on_checkout' => false]);

    $customer = Customer::query()->create([
        'name' => 'Paid Later',
        'email' => 'paid-later@example.com',
        'stripe_id' => 'cus_paid_later',
        'stripe_subscription_id' => 'sub_paid_later',
        'status' => CustomerStatus::Active,
        'trial_ends_at' => now()->subDay(),
        'trial_used_at' => now()->subWeek(),
    ]);

    postStripeWebhook($this, 'evt_invoice_first_1', 'invoice.paid', [
        'id' => 'in_first_paid',
        'customer' => 'cus_paid_later',
        'subscription' => 'sub_paid_later',
        'amount_paid' => 899,
        'billing_reason' => 'subscription_cycle',
    ])->assertOk();

    Queue::assertPushed(OnboardCustomerJob::class, fn (OnboardCustomerJob $job) => $job->customer->is($customer));
});

test('invoice paid renewal does not dispatch onboard when already onboarded', function () {
    Queue::fake();
    config(['billing.trello.provision_on_checkout' => false]);

    Customer::query()->create([
        'name' => 'Renewed',
        'email' => 'renewed@example.com',
        'stripe_id' => 'cus_renewed',
        'stripe_subscription_id' => 'sub_renewed',
        'status' => CustomerStatus::Active,
        'trello_onboarded_at' => now()->subMonth(),
        'trello_board_id' => 'board_renewed',
    ]);

    postStripeWebhook($this, 'evt_invoice_renew_1', 'invoice.paid', [
        'id' => 'in_renewal',
        'customer' => 'cus_renewed',
        'subscription' => 'sub_renewed',
        'amount_paid' => 899,
        'billing_reason' => 'subscription_cycle',
    ])->assertOk();

    Queue::assertNotPushed(OnboardCustomerJob::class);
});

test('subscription updated cancel at period end does not dispatch offboard', function () {
    Queue::fake();

    Customer::query()->create([
        'name' => 'Grace',
        'email' => 'grace@example.com',
        'stripe_id' => 'cus_grace',
        'stripe_subscription_id' => 'sub_grace',
        'status' => CustomerStatus::Active,
        'trello_onboarded_at' => now()->subMonth(),
        'trello_board_id' => 'board_grace',
        'trello_member_id' => 'member_grace',
    ]);

    postStripeWebhook($this, 'evt_sub_grace_1', 'customer.subscription.updated', [
        'id' => 'sub_grace',
        'customer' => 'cus_grace',
        'status' => 'active',
        'cancel_at_period_end' => true,
        'current_period_end' => now()->addDays(20)->timestamp,
        'items' => [
            'data' => [
                ['price' => ['id' => 'price_unused']],
            ],
        ],
    ])->assertOk();

    Queue::assertNotPushed(OffboardCustomerJob::class);

    $customer = Customer::query()->where('email', 'grace@example.com')->first();
    expect($customer?->cancel_at_period_end)->toBeTrue()
        ->and($customer?->access_ends_at)->not->toBeNull()
        ->and($customer?->status)->toBe(CustomerStatus::Active);
});

test('subscription updated immediate cancel dispatches offboard', function () {
    Queue::fake();

    $customer = Customer::query()->create([
        'name' => 'Cancel Now',
        'email' => 'cancel-now@example.com',
        'stripe_id' => 'cus_cancel_now',
        'stripe_subscription_id' => 'sub_cancel_now',
        'status' => CustomerStatus::Active,
        'trello_onboarded_at' => now()->subMonth(),
        'trello_board_id' => 'board_cancel_now',
        'trello_member_id' => 'member_cancel_now',
    ]);

    postStripeWebhook($this, 'evt_sub_cancel_now_1', 'customer.subscription.updated', [
        'id' => 'sub_cancel_now',
        'customer' => 'cus_cancel_now',
        'status' => 'canceled',
        'cancel_at_period_end' => false,
        'items' => [
            'data' => [
                ['price' => ['id' => 'price_unused']],
            ],
        ],
    ])->assertOk();

    Queue::assertPushed(OffboardCustomerJob::class, fn (OffboardCustomerJob $job) => $job->customer->is($customer));
});

test('subscription deleted dispatches offboard after period end cancel', function () {
    Queue::fake();

    $customer = Customer::query()->create([
        'name' => 'Period End',
        'email' => 'period-end@example.com',
        'stripe_id' => 'cus_period_end',
        'stripe_subscription_id' => 'sub_period_end',
        'status' => CustomerStatus::Active,
        'cancel_at_period_end' => true,
        'access_ends_at' => now()->subMinute(),
        'trello_onboarded_at' => now()->subMonths(2),
        'trello_board_id' => 'board_period_end',
        'trello_member_id' => 'member_period_end',
    ]);

    postStripeWebhook($this, 'evt_sub_deleted_1', 'customer.subscription.deleted', [
        'id' => 'sub_period_end',
        'customer' => 'cus_period_end',
        'status' => 'canceled',
        'cancel_at_period_end' => false,
    ])->assertOk();

    Queue::assertPushed(OffboardCustomerJob::class, fn (OffboardCustomerJob $job) => $job->customer->is($customer));

    $customer->refresh();
    expect($customer->status)->toBe(CustomerStatus::Cancelled)
        ->and($customer->cancelled_at)->not->toBeNull();
});

test('subscription updated trialing to active dispatches onboard when deferred', function () {
    Queue::fake();
    config(['billing.trello.provision_on_checkout' => false]);

    $customer = Customer::query()->create([
        'name' => 'Convert',
        'email' => 'convert@example.com',
        'stripe_id' => 'cus_convert',
        'stripe_subscription_id' => 'sub_convert',
        'status' => CustomerStatus::Active,
        'trial_ends_at' => now(),
        'trial_used_at' => now()->subWeek(),
    ]);

    postStripeWebhook(
        $this,
        'evt_sub_convert_1',
        'customer.subscription.updated',
        [
            'id' => 'sub_convert',
            'customer' => 'cus_convert',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'trial_end' => null,
            'items' => [
                'data' => [
                    ['price' => ['id' => 'price_unused']],
                ],
            ],
        ],
        ['status' => 'trialing'],
    )->assertOk();

    Queue::assertPushed(OnboardCustomerJob::class, fn (OnboardCustomerJob $job) => $job->customer->is($customer));
});

test('subscription updated active renewal does not dispatch onboard or offboard', function () {
    Queue::fake();

    Customer::query()->create([
        'name' => 'Renewal',
        'email' => 'renewal@example.com',
        'stripe_id' => 'cus_renewal',
        'stripe_subscription_id' => 'sub_renewal',
        'status' => CustomerStatus::Active,
        'trello_onboarded_at' => now()->subYear(),
        'trello_board_id' => 'board_renewal',
        'trello_member_id' => 'member_renewal',
    ]);

    postStripeWebhook(
        $this,
        'evt_sub_renewal_1',
        'customer.subscription.updated',
        [
            'id' => 'sub_renewal',
            'customer' => 'cus_renewal',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'current_period_end' => now()->addMonth()->timestamp,
            'items' => [
                'data' => [
                    ['price' => ['id' => 'price_unused']],
                ],
            ],
        ],
        ['current_period_end' => now()->timestamp],
    )->assertOk();

    Queue::assertNotPushed(OnboardCustomerJob::class);
    Queue::assertNotPushed(OffboardCustomerJob::class);
});

test('offboard job removes member by email when stored member id matches board id', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/boards/board_bad/members') && $request->method() === 'GET') {
            return Http::response([
                [
                    'id' => 'member_pending',
                    'email' => 'pending@example.com',
                    'username' => 'pendinguser',
                ],
            ], 200);
        }

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'DELETE' && str_contains($url, '/boards/board_bad/members/member_pending')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks/hook_bad')) {
            return Http::response([], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Pending',
        'email' => 'pending@example.com',
        'status' => CustomerStatus::Cancelled,
        'trello_onboarded_at' => now()->subHour(),
        'trello_board_id' => 'board_bad',
        'trello_board_url' => 'https://trello.com/b/board_bad',
        'trello_member_id' => 'board_bad',
        'trello_webhook_id' => 'hook_bad',
    ]);

    (new OffboardCustomerJob($customer))->handle(app(TrelloService::class));

    $customer->refresh();

    expect($customer->trello_offboarded_at)->not->toBeNull()
        ->and($customer->trello_board_id)->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/boards/board_bad/members/member_pending'));
});

test('offboard job completes when trello membership already removed', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/boards/board_done/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_done/memberships')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'DELETE' && str_contains($url, '/boards/board_done/members/member_done')) {
            return Http::response('membership not found', 404);
        }

        if (str_contains($url, '/webhooks/hook_done')) {
            return Http::response([], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Done',
        'email' => 'done-offboard@example.com',
        'status' => CustomerStatus::Cancelled,
        'trello_onboarded_at' => now()->subHour(),
        'trello_board_id' => 'board_done',
        'trello_member_id' => 'member_done',
        'trello_webhook_id' => 'hook_done',
    ]);

    (new OffboardCustomerJob($customer))->handle(app(TrelloService::class));

    expect($customer->fresh()->trello_offboarded_at)->not->toBeNull();
});

test('offboard job is idempotent when customer already offboarded', function () {
    $customer = Customer::query()->create([
        'name' => 'Done',
        'email' => 'offboarded@example.com',
        'status' => CustomerStatus::Cancelled,
        'trello_offboarded_at' => now(),
        'trello_board_id' => null,
    ]);

    $this->mock(TrelloService::class, function ($mock): void {
        $mock->shouldNotReceive('removeMemberFromBoardByEmail');
        $mock->shouldNotReceive('deleteBoardWebhook');
    });

    (new OffboardCustomerJob($customer))->handle(app(TrelloService::class));

    expect($customer->fresh()->trello_offboarded_at)->not->toBeNull();
});
