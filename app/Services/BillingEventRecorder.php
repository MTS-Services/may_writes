<?php

namespace App\Services;

use App\Models\BillingEvent;
use App\Models\Customer;
use App\Models\WebhookLog;
use Illuminate\Support\Carbon;

class BillingEventRecorder
{
    public function recordFromWebhook(WebhookLog $log, ?Customer $customer): void
    {
        if (! filled($log->stripe_event_id)) {
            return;
        }

        $payload = $log->payload ?? [];
        $object = data_get($payload, 'data.object', []);
        $eventCreated = (int) ($payload['created'] ?? time());

        $stripeCustomerId = $customer?->stripe_id
            ?? $this->stripeCustomerIdFromObject($object);

        [$amountCents, $currency] = $this->extractAmountAndCurrency($log->event_type, $object);

        $metadata = $this->buildMetadata($log->event_type, $object);

        BillingEvent::query()->firstOrCreate(
            ['stripe_event_id' => $log->stripe_event_id],
            [
                'customer_id' => $customer?->id,
                'stripe_customer_id' => $stripeCustomerId,
                'event_type' => $log->event_type,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'metadata' => $metadata,
                'occurred_at' => Carbon::createFromTimestamp($eventCreated),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function stripeCustomerIdFromObject(mixed $object): ?string
    {
        if (! is_array($object)) {
            return null;
        }

        $customer = data_get($object, 'customer');

        return filled($customer) ? (string) $customer : null;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array{0: ?int, 1: ?string}
     */
    private function extractAmountAndCurrency(string $eventType, mixed $object): array
    {
        if (! is_array($object)) {
            return [null, null];
        }

        return match ($eventType) {
            'checkout.session.completed' => [
                $this->nullablePositiveInt(data_get($object, 'amount_total')),
                $this->nullableCurrency(data_get($object, 'currency')),
            ],
            'invoice.paid' => [
                $this->nullablePositiveInt(data_get($object, 'amount_paid')),
                $this->nullableCurrency(data_get($object, 'currency')),
            ],
            default => [null, null],
        };
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function buildMetadata(string $eventType, mixed $object): array
    {
        if (! is_array($object)) {
            return [];
        }

        return match ($eventType) {
            'checkout.session.completed' => array_filter([
                'checkout_session_id' => data_get($object, 'id'),
                'subscription' => data_get($object, 'subscription'),
                'mode' => data_get($object, 'mode'),
                'metadata' => data_get($object, 'metadata'),
            ], fn ($v) => $v !== null && $v !== ''),
            'invoice.paid' => array_filter([
                'invoice_id' => data_get($object, 'id'),
                'subscription' => data_get($object, 'subscription'),
                'billing_reason' => data_get($object, 'billing_reason'),
            ], fn ($v) => $v !== null && $v !== ''),
            'customer.subscription.updated', 'customer.subscription.deleted' => array_filter([
                'subscription_id' => data_get($object, 'id'),
                'status' => data_get($object, 'status'),
                'cancel_at_period_end' => data_get($object, 'cancel_at_period_end'),
            ], fn ($v) => $v !== null && $v !== ''),
            default => [],
        };
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function nullableCurrency(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return strtoupper((string) $value);
    }
}
