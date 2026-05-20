<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\TrelloOnboardingStatus;
use App\Jobs\OffboardCustomerJob;
use App\Jobs\OnboardCustomerJob;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\WebhookLog;
use App\Services\BillingEventRecorder;
use App\Services\CustomerTrelloOffboarding;
use App\Services\CustomerTrelloProvisioning;
use App\Services\TrelloService;
use App\Services\TrelloWebhookActionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;

class WebhookController extends Controller
{
    public function __construct(
        private CustomerTrelloProvisioning $trelloProvisioning,
        private CustomerTrelloOffboarding $trelloOffboarding,
        private BillingEventRecorder $billingEventRecorder,
        private TrelloService $trelloService,
        private TrelloWebhookActionHandler $trelloWebhookActionHandler,
    ) {}

    public function handleStripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, (string) config('cashier.webhook.secret'));
        } catch (\Throwable $exception) {
            Log::warning('Stripe webhook signature failed', ['error' => $exception->getMessage()]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        if (WebhookLog::query()->where('stripe_event_id', $event->id)->exists()) {
            return response()->json(['status' => 'already_processed']);
        }

        $log = WebhookLog::create([
            'source' => 'stripe',
            'event_type' => $event->type,
            'stripe_event_id' => $event->id,
            'payload' => json_decode($payload, true),
            'status' => 'received',
        ]);

        return match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted((object) $event->data->object, $log),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted((object) $event->data->object, $log),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated((object) $event->data->object, $log, $event),
            'invoice.paid' => $this->handleInvoicePaid((object) $event->data->object, $log),
            default => tap(response()->json(['status' => 'ignored']), fn () => $log->update([
                'status' => 'processed',
                'processed_at' => now(),
            ])),
        };
    }

    public function handleTrello(Request $request): JsonResponse
    {
        if ($request->isMethod('head')) {
            return response()->json([], 200);
        }

        $payload = $request->all();

        $log = WebhookLog::create([
            'source' => 'trello',
            'event_type' => data_get($payload, 'action.type', 'unknown'),
            'payload' => $payload,
            'status' => 'received',
        ]);

        Log::info('Trello webhook received', [
            'webhook_log_id' => $log->id,
            'action_type' => data_get($payload, 'action.type'),
            'board_id' => data_get($payload, 'action.data.board.id'),
            'card_id' => data_get($payload, 'action.data.card.id'),
        ]);

        return $this->trelloWebhookActionHandler->handle($payload, $log);
    }

    private function handleCheckoutCompleted(object $session, WebhookLog $log): JsonResponse
    {
        $planId = (int) data_get($session, 'metadata.plan_id');

        $plan = Plan::query()->find($planId);

        $subscriptionId = data_get($session, 'subscription');
        $subscription = $this->retrieveStripeSubscription($subscriptionId);

        $email = strtolower(trim((string) data_get($session, 'customer_details.email')));
        $incomingName = trim((string) data_get($session, 'customer_details.name', ''));

        $customer = Customer::firstOrCreate(
            ['email' => $email],
            [
                'name' => filled($incomingName) ? $incomingName : 'MayWrites Customer',
                'stripe_id' => (string) data_get($session, 'customer'),
                'plan_id' => $plan?->id,
                'status' => CustomerStatus::Active,
                'subscribed_at' => now(),
            ],
        );

        $trialAttributes = $this->trialAttributesFromSubscription($subscription);

        $reactivationAttributes = [];

        if ($customer->trello_offboarded_at !== null) {
            $reactivationAttributes = [
                'trello_offboarded_at' => null,
                'status' => CustomerStatus::Active,
                'cancelled_at' => null,
                'cancel_at_period_end' => false,
                'access_ends_at' => null,
            ];
        }

        $customer->update(array_merge([
            'name' => filled($incomingName) ? $incomingName : $customer->name,
            'stripe_id' => (string) data_get($session, 'customer'),
            'stripe_subscription_id' => filled($subscriptionId) ? (string) $subscriptionId : null,
            'plan_id' => $plan?->id,
        ], $trialAttributes, $reactivationAttributes));

        if ($this->trelloProvisioning->shouldOnboardOnCheckout($subscription) && $customer->trello_onboarded_at === null) {
            $customer->update([
                'trello_onboarding_status' => TrelloOnboardingStatus::Pending,
                'trello_onboarding_last_error' => null,
            ]);
            OnboardCustomerJob::dispatch($customer)->onQueue('default');
        }

        $customer->refresh();

        if (filled($customer->trello_board_id) && $customer->trello_onboarded_at !== null) {
            $this->trelloService->syncBoardDisplayName($customer);
        }

        $this->billingEventRecorder->recordFromWebhook($log, $customer);

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    private function handleInvoicePaid(object $invoice, WebhookLog $log): JsonResponse
    {
        $customer = Customer::query()
            ->where('stripe_id', (string) data_get($invoice, 'customer'))
            ->first();

        if ($customer && $this->trelloProvisioning->shouldOnboardOnInvoice($customer, $invoice) && $customer->trello_onboarded_at === null) {
            $customer->update([
                'trello_onboarding_status' => TrelloOnboardingStatus::Pending,
                'trello_onboarding_last_error' => null,
            ]);
            OnboardCustomerJob::dispatch($customer)->onQueue('default');
        }

        $this->billingEventRecorder->recordFromWebhook($log, $customer);

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    private function handleSubscriptionDeleted(object $subscription, WebhookLog $log): JsonResponse
    {
        $customer = Customer::query()->where('stripe_id', (string) data_get($subscription, 'customer'))->first();

        if ($customer) {
            $customer->update([
                'status' => CustomerStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_at_period_end' => false,
                'access_ends_at' => null,
            ]);

            if ($this->trelloOffboarding->shouldOffboardOnSubscriptionDeleted($customer)) {
                OffboardCustomerJob::dispatch($customer)->onQueue('default');
            }
        }

        $this->billingEventRecorder->recordFromWebhook($log, $customer);

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    private function handleSubscriptionUpdated(object $subscription, WebhookLog $log, object $event): JsonResponse
    {
        $priceId = (string) data_get($subscription, 'items.data.0.price.id');
        $plan = Plan::query()->where('stripe_price_id', $priceId)->first();
        $customer = Customer::query()->where('stripe_id', (string) data_get($subscription, 'customer'))->first();

        if ($customer) {
            $previousPlanId = $customer->plan_id;

            $updates = array_merge(
                $this->subscriptionSyncAttributes($subscription),
                $this->trialEndsAtFromSubscription($subscription),
            );

            if ($plan) {
                $updates['plan_id'] = $plan->id;
            }

            $customer->update($updates);
            $customer->refresh();

            if (
                filled($customer->trello_board_id)
                && $customer->trello_onboarded_at !== null
                && $plan !== null
                && (int) $previousPlanId !== (int) $customer->plan_id
            ) {
                $this->trelloService->syncBoardDisplayName($customer);
            }

            $previousStatus = data_get($event, 'data.previous_attributes.status');

            if (
                is_string($previousStatus)
                && $this->trelloProvisioning->shouldOnboardOnSubscriptionTransition(
                    $customer,
                    $previousStatus,
                    (string) data_get($subscription, 'status', ''),
                )
                && $customer->trello_onboarded_at === null
            ) {
                $customer->update([
                    'trello_onboarding_status' => TrelloOnboardingStatus::Pending,
                    'trello_onboarding_last_error' => null,
                ]);
                OnboardCustomerJob::dispatch($customer)->onQueue('default');
            }

            if ($this->trelloOffboarding->shouldOffboardOnSubscriptionUpdated($customer, $subscription)) {
                OffboardCustomerJob::dispatch($customer)->onQueue('default');
            }
        }

        $this->billingEventRecorder->recordFromWebhook($log, $customer);

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionSyncAttributes(object $subscription): array
    {
        $cancelAtPeriodEnd = (bool) data_get($subscription, 'cancel_at_period_end', false);

        $attributes = [
            'stripe_subscription_id' => (string) data_get($subscription, 'id'),
            'cancel_at_period_end' => $cancelAtPeriodEnd,
        ];

        $periodEnd = data_get($subscription, 'current_period_end');

        if ($cancelAtPeriodEnd && $periodEnd !== null) {
            $attributes['access_ends_at'] = Carbon::createFromTimestamp((int) $periodEnd);
        } elseif (! $cancelAtPeriodEnd) {
            $attributes['access_ends_at'] = null;
        }

        return $attributes;
    }

    /**
     * @return array{trial_ends_at: ?Carbon, trial_used_at: ?Carbon}
     */
    private function trialAttributesFromSubscription(?object $subscription): array
    {
        if ($subscription === null) {
            return [
                'trial_ends_at' => null,
                'trial_used_at' => null,
            ];
        }

        $trialEnd = $subscription->trial_end ?? null;

        if ($trialEnd === null) {
            return [
                'trial_ends_at' => null,
                'trial_used_at' => null,
            ];
        }

        return [
            'trial_ends_at' => Carbon::createFromTimestamp((int) $trialEnd),
            'trial_used_at' => now(),
        ];
    }

    /**
     * @return array{trial_ends_at: ?Carbon}
     */
    private function trialEndsAtFromSubscription(object $subscription): array
    {
        $trialEnd = data_get($subscription, 'trial_end');

        if ($trialEnd === null) {
            return ['trial_ends_at' => null];
        }

        return [
            'trial_ends_at' => Carbon::createFromTimestamp((int) $trialEnd),
        ];
    }

    private function retrieveStripeSubscription(mixed $subscriptionId): ?object
    {
        if (! filled($subscriptionId)) {
            return null;
        }

        try {
            Stripe::setApiKey((string) config('cashier.secret'));

            return Subscription::retrieve((string) $subscriptionId);
        } catch (\Throwable $exception) {
            Log::warning('Unable to retrieve Stripe subscription', [
                'subscription_id' => $subscriptionId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
