<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Stripe\Price;
use Stripe\Stripe;

#[Signature('stripe:sync-prices')]
#[Description('Sync Stripe price IDs to local plans')]
class SyncStripePrices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Stripe::setApiKey((string) config('cashier.secret'));

        $prices = Price::all(['active' => true, 'limit' => 100]);

        $rows = collect($prices->data)->map(function (Price $price): array {
            return [
                'id' => $price->id,
                'nickname' => $price->nickname ?? '-',
                'amount' => $price->unit_amount ? '$'.number_format($price->unit_amount / 100, 2) : '-',
                'interval' => $price->recurring?->interval ?? '-',
            ];
        });

        if ($rows->isEmpty()) {
            $this->warn('No active Stripe prices found.');
        } else {
            $this->table(['ID', 'Nickname', 'Amount', 'Interval'], $rows->values()->all());
        }

        $plans = Plan::query()
            ->whereIn('slug', ['starter', 'pro', 'growth'])
            ->orderBy('sort_order')
            ->get();

        foreach ($plans as $plan) {
            $priceId = $this->ask("Enter the Stripe Price ID for {$plan->name}", $plan->stripe_price_id);

            if (! is_string($priceId) || $priceId === '') {
                $this->warn("Skipped {$plan->name}; no value provided.");

                continue;
            }

            $plan->update(['stripe_price_id' => trim($priceId)]);
        }

        $this->info('Plans updated successfully.');

        return self::SUCCESS;
    }
}
