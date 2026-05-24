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
                'words_per_request' => 4000,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [
                    'Ideal for individuals, creators, and small businesses that need consistent short-form content like LinkedIn posts, emails, captions, and simple blog articles.',
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
                'words_per_request' => 10000,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    'Perfect for growing brands and professionals that need consistent, high-quality content with stronger storytelling, strategy, and faster turnaround.',
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
                'words_per_request' => null,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'Built for brands with higher content demands that need long-form content, ongoing campaigns, advanced strategy, and priority delivery.',
                    'Unlimited words per request',
                    'Unlimited total requests',
                    'Priority turnaround',
                    'Unlimited revisions',
                    'Dedicated Slack channel (on request)',
                    'All content types + strategy',
                ],
            ],
        );
    }
}
