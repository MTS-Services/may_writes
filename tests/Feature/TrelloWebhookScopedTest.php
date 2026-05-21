<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Jobs\ProcessTrelloTaskJob;
use App\Models\Customer;
use App\Models\TrelloTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('trello createCard on non queue list does not enqueue process job', function () {
    Queue::fake();

    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-a@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_requests',
        'trello_instruction_card_ids' => ['requests_instructions' => 'card_requests_instructions'],
        'trello_welcome_card_id' => 'card_requests_instructions',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'createCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'list' => ['id' => 'list_in_progress'],
                'card' => ['id' => 'card_req', 'name' => 'Blog post', 'desc' => 'Details'],
            ],
        ],
    ])->assertOk();

    expect(TrelloTask::query()->count())->toBe(0);
    Queue::assertNotPushed(ProcessTrelloTaskJob::class);
});

test('trello createCard on queue list for instruction sentinel does not enqueue process job', function () {
    Queue::fake();

    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-b@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_requests',
        'trello_instruction_card_ids' => ['requests_instructions' => 'card_requests_instructions'],
        'trello_welcome_card_id' => 'card_requests_instructions',
    ]);

    $instructionName = (string) config('trello_template.instruction_cards.requests_instructions.name');

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'createCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'list' => ['id' => 'list_requests'],
                'card' => ['id' => 'card_requests_instructions', 'name' => $instructionName, 'desc' => ''],
            ],
        ],
    ])->assertOk();

    expect(TrelloTask::query()->count())->toBe(0);
    Queue::assertNotPushed(ProcessTrelloTaskJob::class);
});

test('trello createCard on queue list for example card does not enqueue process job', function () {
    Queue::fake();

    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-example@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_requests',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'createCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'list' => ['id' => 'list_requests'],
                'card' => ['id' => 'card_example', 'name' => 'EXAMPLE (Blog Post) - Demo', 'desc' => ''],
            ],
        ],
    ])->assertOk();

    Queue::assertNotPushed(ProcessTrelloTaskJob::class);
});

test('trello createCard on queue list for a request card dispatches process job', function () {
    Queue::fake();

    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-c@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_requests',
        'trello_instruction_card_ids' => ['requests_instructions' => 'card_requests_instructions'],
        'trello_welcome_card_id' => 'card_requests_instructions',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'createCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'list' => ['id' => 'list_requests'],
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

test('trello deleteCard for instruction sentinel recreates card and updates stored id', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        return Http::response(['error' => 'unexpected '.$request->url()], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-d@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_requests',
        'trello_instruction_card_ids' => ['requests_instructions' => 'card_requests_instructions'],
        'trello_welcome_card_id' => 'card_requests_instructions',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'deleteCard',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'card' => [
                    'id' => 'card_requests_instructions',
                    'name' => config('trello_template.instruction_cards.requests_instructions.name'),
                ],
            ],
        ],
    ])->assertOk();

    $customer->refresh();

    expect($customer->trello_welcome_card_id)->toBe('card_created')
        ->and($customer->trello_instruction_card_ids['requests_instructions'])->toBe('card_created');

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/cards'));
});

test('trello archiveList for protected list restores via trello api', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        return Http::response(['error' => 'unexpected '.$request->url()], 500);
    });

    Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-archive@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_requests',
        'trello_in_progress_list_id' => 'list_in_progress',
        'trello_draft_review_list_id' => 'list_draft_review',
        'trello_revisions_list_id' => 'list_revisions',
        'trello_delivered_list_id' => 'list_delivered',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'archiveList',
            'data' => [
                'board' => ['id' => 'board_scope'],
                'list' => [
                    'id' => 'list_draft_review',
                    'name' => 'DRAFT REVIEW COLUMN',
                    'closed' => true,
                ],
            ],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_contains($request->url(), '/lists/list_draft_review'));
});

test('trello updateList when protected list is closed triggers restore', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        return Http::response(['error' => 'unexpected '.$request->url()], 500);
    });

    Customer::query()->create([
        'name' => 'Writer',
        'email' => 'writer-updatelist@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_scope',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_requests',
        'trello_in_progress_list_id' => 'list_in_progress',
        'trello_delivered_list_id' => 'list_delivered',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'updateList',
            'data' => [
                'old' => ['closed' => false],
                'list' => [
                    'id' => 'list_requests',
                    'name' => 'REQUESTS (QUEUE) COLUMN',
                    'closed' => true,
                ],
                'board' => ['id' => 'board_scope'],
            ],
        ],
    ])->assertOk();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_contains($request->url(), '/lists/list_requests'));
});
