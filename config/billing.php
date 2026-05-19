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

];
