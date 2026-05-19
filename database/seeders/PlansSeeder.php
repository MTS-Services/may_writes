<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'stripe_price_id' => 'price_starter_placeholder',
                'price' => 499.00,
                'active_requests' => 1,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [
                    'Maximum of 4,000 words per request',
                    'Unlimited total requests',
                    '24-72 hr turnaround',
                    'Unlimited revisions',
                    'Dedicated Trello board',
                    'All content types',
                ],
            ],
        );

        Plan::updateOrCreate(
            ['slug' => 'pro'],
            [
                'name' => 'Pro',
                'stripe_price_id' => 'price_pro_placeholder',
                'price' => 899.00,
                'active_requests' => 2,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    'Maximum 10,000 words per request',
                    'Unlimited total requests',
                    '24-48 hr turnaround',
                    'Unlimited revisions',
                    'Priority queue',
                    'SEO optimization included',
                    'Brand voice guidelines',
                ],
            ],
        );

        Plan::updateOrCreate(
            ['slug' => 'growth'],
            [
                'name' => 'Growth',
                'stripe_price_id' => 'price_growth_placeholder',
                'price' => 1499.00,
                'active_requests' => 4,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'Unlimited words per request',
                    'Unlimited total requests',
                    'Same-day turnaround',
                    'Unlimited revisions',
                    'Dedicated Slack channel',
                    'All content types + strategy',
                ],
            ],
        );
    }
}
