<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('env:validate')]
#[Description('Validate required MayWrites environment configuration')]
class ValidateEnvironment extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $checks = [
            'STRIPE_KEY' => env('STRIPE_KEY'),
            'STRIPE_SECRET' => env('STRIPE_SECRET'),
            'STRIPE_WEBHOOK_SECRET' => env('STRIPE_WEBHOOK_SECRET'),
            'ANTHROPIC_API_KEY' => env('ANTHROPIC_API_KEY'),
            'TRELLO_API_KEY' => env('TRELLO_API_KEY'),
            'TRELLO_API_TOKEN' => env('TRELLO_API_TOKEN'),
            'TRELLO_TEMPLATE_BOARD_ID' => env('TRELLO_TEMPLATE_BOARD_ID'),
            'APP_URL' => env('APP_URL'),
            'DB_CONNECTION' => env('DB_CONNECTION'),
            'DB_DATABASE' => env('DB_DATABASE'),
            'QUEUE_CONNECTION' => env('QUEUE_CONNECTION'),
            'MAIL_HOST' => env('MAIL_HOST'),
            'MAIL_USERNAME' => env('MAIL_USERNAME'),
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS'),
        ];

        $failed = false;

        foreach ($checks as $key => $value) {
            $invalid = blank($value) || str_contains((string) $value, 'placeholder');
            if ($key === 'APP_URL' && str_contains((string) $value, 'localhost')) {
                $invalid = true;
            }

            if ($invalid) {
                $this->error("✗ {$key} is missing or invalid");
                $failed = true;
            } else {
                $this->info("✓ {$key} is set");
            }
        }

        $placeholderPlans = Plan::query()->where('stripe_price_id', 'like', '%placeholder%')->count();
        if ($placeholderPlans > 0) {
            $this->error("✗ {$placeholderPlans} plan(s) still use placeholder Stripe price IDs.");
            $failed = true;
        } else {
            $this->info('✓ Plans have real Stripe price IDs');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
