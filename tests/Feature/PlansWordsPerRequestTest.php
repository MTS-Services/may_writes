<?php

declare(strict_types=1);

use App\Models\Plan;
use Database\Seeders\PlansSeeder;

test('plans seeder sets words per request limits', function () {
    $this->seed(PlansSeeder::class);

    expect(Plan::query()->where('slug', 'starter')->value('words_per_request'))->toBe(4000)
        ->and(Plan::query()->where('slug', 'pro')->value('words_per_request'))->toBe(10000)
        ->and(Plan::query()->where('slug', 'growth')->value('words_per_request'))->toBeNull();
});
