<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription trial
    |--------------------------------------------------------------------------
    |
    | Applied once per customer email on the first Stripe Checkout subscription.
    | Renewals do not receive another trial (handled by Stripe).
    |
    */

    'trial' => [
        'enabled' => env('SUBSCRIPTION_TRIAL_ENABLED', true),
        'days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trello provisioning
    |--------------------------------------------------------------------------
    |
    | When true (default), create the Trello board, invite, and webhook on
    | checkout.session.completed — including subscriptions with a free trial.
    | When false, defer Trello setup until the first paid invoice or when the
    | subscription moves from trialing to active.
    |
    */

    'trello' => [
        'provision_on_checkout' => env('TRELLO_PROVISION_ON_CHECKOUT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operations alerts
    |--------------------------------------------------------------------------
    |
    | When Trello onboarding permanently fails (queue retries exhausted), a
    | notification is sent to this address if set. Leave empty to log only.
    |
    */

    'alerts' => [
        'onboarding_failure_email' => env('BILLING_ONBOARDING_FAILURE_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Customer-facing copy
    |--------------------------------------------------------------------------
    */

    'support' => [
        'checkout_followup_minutes' => (int) env('BILLING_CHECKOUT_FOLLOWUP_MINUTES', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Future: optional accounts (deferred)
    |--------------------------------------------------------------------------
    |
    | Magic-link post-checkout or Laravel Fortify are not required for billing
    | audit or Trello fulfillment; add only if you need self-serve beyond Stripe
    | Customer Portal.
    |
    */

];
