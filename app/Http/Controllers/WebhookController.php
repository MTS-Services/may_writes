<?php

namespace App\Http\Controllers;

use App\Enums\CustomerStatus;
use App\Enums\TrelloTaskStatus;
use App\Jobs\OnboardCustomerJob;
use App\Jobs\ProcessTrelloTaskJob;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\TrelloTask;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

class WebhookController extends Controller
{
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
            'customer.subscription.deleted' => $this->handleSubscriptionCancelled((object) $event->data->object, $log),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated((object) $event->data->object, $log),
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

        if (data_get($payload, 'action.type') !== 'createCard') {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $boardId = (string) data_get($payload, 'action.data.board.id');
        $cardId = (string) data_get($payload, 'action.data.card.id');

        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if (! $customer || $cardId === '') {
            $log->update(['status' => 'failed', 'error_message' => 'Unknown board or card.', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $trelloTask = TrelloTask::create([
            'customer_id' => $customer->id,
            'trello_card_id' => $cardId,
            'trello_board_id' => $boardId,
            'title' => (string) data_get($payload, 'action.data.card.name'),
            'description' => data_get($payload, 'action.data.card.desc'),
            'raw_payload' => $payload,
            'status' => TrelloTaskStatus::Received,
        ]);

        ProcessTrelloTaskJob::dispatch($trelloTask)->onQueue('default');

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    private function handleCheckoutCompleted(object $session, WebhookLog $log): JsonResponse
    {
        $planId = (int) data_get($session, 'metadata.plan_id');

        $plan = Plan::query()->find($planId);

        $customer = Customer::firstOrCreate(
            ['email' => (string) data_get($session, 'customer_details.email')],
            [
                'name' => (string) data_get($session, 'customer_details.name', 'MayWrites Customer'),
                'stripe_id' => (string) data_get($session, 'customer'),
                'plan_id' => $plan?->id,
                'status' => CustomerStatus::Active,
                'subscribed_at' => now(),
            ],
        );

        $customer->update([
            'stripe_id' => (string) data_get($session, 'customer'),
            'plan_id' => $plan?->id,
        ]);

        OnboardCustomerJob::dispatch($customer)->onQueue('default');

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    private function handleSubscriptionCancelled(object $subscription, WebhookLog $log): JsonResponse
    {
        $customer = Customer::query()->where('stripe_id', (string) data_get($subscription, 'customer'))->first();

        if ($customer) {
            $customer->update([
                'status' => CustomerStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        }

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    private function handleSubscriptionUpdated(object $subscription, WebhookLog $log): JsonResponse
    {
        $priceId = (string) data_get($subscription, 'items.data.0.price.id');
        $plan = Plan::query()->where('stripe_price_id', $priceId)->first();
        $customer = Customer::query()->where('stripe_id', (string) data_get($subscription, 'customer'))->first();

        if ($plan && $customer) {
            $customer->update(['plan_id' => $plan->id]);
        }

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }
}
