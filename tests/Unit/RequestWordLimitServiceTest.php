<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Services\RequestWordLimitService;

test('counts words in description only style text', function () {
    $service = new RequestWordLimitService;

    expect($service->countWords('one two three'))->toBe(3)
        ->and($service->countWords("  one   two\nthree  "))->toBe(3);
});

test('truncates description to plan limit', function () {
    $service = new RequestWordLimitService;
    $text = implode(' ', range(1, 10));

    $result = $service->applyLimit($text, 4);

    expect($result->wasTruncated)->toBeTrue()
        ->and($result->originalCount)->toBe(10)
        ->and($result->processedCount)->toBe(4)
        ->and($result->text)->toBe('1 2 3 4');
});

test('unlimited plan does not truncate', function () {
    $service = new RequestWordLimitService;
    $plan = new Plan(['words_per_request' => null]);

    $result = $service->applyLimit('word one word two', $service->limitForPlan($plan));

    expect($result->wasTruncated)->toBeFalse();
});

test('plan limits match seeder values', function () {
    $service = new RequestWordLimitService;

    expect($service->limitForPlan(new Plan(['words_per_request' => 4000])))->toBe(4000)
        ->and($service->limitForPlan(new Plan(['words_per_request' => 10000])))->toBe(10000)
        ->and($service->limitForPlan(new Plan(['words_per_request' => null])))->toBeNull();
});
