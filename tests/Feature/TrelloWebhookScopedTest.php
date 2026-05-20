<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Jobs\ProcessTrelloTaskJob;
use App\Models\Customer;
use App\Models\TrelloTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('trello createCard on non writing requests list does not enqueue process job', function () {
    Queue::fake();

    $customer = Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-a@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_writing',
        'trello_welcome_card_id' => 'card_welcome',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'createCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'list' => ['id' => 'list_other'],
                'card' => ['id' => 'card_req', 'name' => 'Blog post', 'desc' => 'Details'],
            ],
        ],
    ])->assertOk();

    expect(TrelloTask::query()->count())->toBe(0);
    Queue::assertNotPushed(ProcessTrelloTaskJob::class);
});

test('trello createCard on writing list for welcome sentinel does not enqueue process job', function () {
    Queue::fake();

    Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-b@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_writing',
        'trello_welcome_card_id' => 'card_welcome',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'createCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'list' => ['id' => 'list_writing'],
                'card' => ['id' => 'card_welcome', 'name' => 'Welcome', 'desc' => ''],
            ],
        ],
    ])->assertOk();

    expect(TrelloTask::query()->count())->toBe(0);
    Queue::assertNotPushed(ProcessTrelloTaskJob::class);
});

test('trello createCard on writing list for a request card dispatches process job', function () {
    Queue::fake();

    $customer = Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-c@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_writing',
        'trello_welcome_card_id' => 'card_welcome',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'createCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'list' => ['id' => 'list_writing'],
                'card' => ['id' => 'card_req', 'name' => 'Case study', 'desc' => 'B2B'],
            ],
        ],
    ])->assertOk();

    $task = TrelloTask::query()->first();
    expect($task)->not->toBeNull()
        ->and($task->customer_id)->toBe($customer->id)
        ->and($task->trello_card_id)->toBe('card_req');

    Queue::assertPushed(ProcessTrelloTaskJob::class);
});

test('trello deleteCard for welcome sentinel recreates card and updates stored id', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $url = $request->url();

        if ($request->method() === 'POST' && str_contains($url, '/cards')) {
            return Http::response(['id' => 'card_welcome_new'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-d@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_writing',
        'trello_welcome_card_id' => 'card_welcome_old',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'deleteCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'card' => ['id' => 'card_welcome_old'],
            ],
        ],
    ])->assertOk();

    $customer->refresh();

    expect($customer->trello_welcome_card_id)->toBe('card_welcome_new');

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/cards'));
});
