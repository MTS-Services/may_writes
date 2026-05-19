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

];
