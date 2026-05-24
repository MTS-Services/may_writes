<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Jobs\ProcessTrelloTaskJob;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('updateCard description change on queue updates task without dispatching process job', function () {
    Queue::fake();

    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(fn ($request) => trelloTemplateStructureHttpResponse($request)
        ?? Http::response(['id' => 'ok']));

    $customer = Customer::query()->create([
        'name' => 'Updater',
        'email' => 'updater@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_upd',
        'trello_writing_requests_list_id' => 'list_requests',
    ]);

    $task = TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_upd',
        'trello_board_id' => 'board_upd',
        'title' => 'Post',
        'description' => 'original text',
        'content_fingerprint' => TrelloTask::descriptionFingerprint('original text'),
    ]);

    TrelloTaskVersion::query()->create([
        'trello_task_id' => $task->id,
        'version_number' => 1,
        'title' => 'Post',
        'description' => 'original text',
        'content_fingerprint' => $task->content_fingerprint,
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'updateCard',
            'data' => [
                'board' => ['id' => 'board_upd'],
                'card' => ['id' => 'card_upd', 'name' => 'Post', 'desc' => 'updated text here', 'idList' => 'list_requests'],
                'list' => ['id' => 'list_requests'],
                'old' => ['desc' => 'original text'],
            ],
        ],
    ])->assertOk();

    $task->refresh();
    expect($task->description)->toBe('updated text here')
        ->and(TrelloTaskVersion::query()->where('trello_task_id', $task->id)->count())->toBe(1);
    Queue::assertNotPushed(ProcessTrelloTaskJob::class);
});

test('updateCard with same description fingerprint does not create version', function () {
    Queue::fake();

    $customer = Customer::query()->create([
        'name' => 'Updater',
        'email' => 'same@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_same',
        'trello_writing_requests_list_id' => 'list_requests',
    ]);

    $fingerprint = TrelloTask::descriptionFingerprint('same desc');

    TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_same',
        'trello_board_id' => 'board_same',
        'title' => 'Post',
        'description' => 'same desc',
        'content_fingerprint' => $fingerprint,
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'updateCard',
            'data' => [
                'board' => ['id' => 'board_same'],
                'card' => ['id' => 'card_same', 'name' => 'Post', 'desc' => 'same desc', 'idList' => 'list_requests'],
                'list' => ['id' => 'list_requests'],
                'old' => ['desc' => 'same desc'],
            ],
        ],
    ])->assertOk();

    Queue::assertNotPushed(ProcessTrelloTaskJob::class);
});
