<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\TrelloTaskVersionTrigger;
use App\Jobs\ProcessTrelloTaskJob;
use App\Mail\NewWritingRequestMail;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('addLabelToCard with Request Completed dispatches process job and notifies admin', function () {
    Queue::fake();
    Mail::fake();

    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
        'billing.alerts.new_request_email' => 'ops@maywrites.test',
        'trello_template.request_completed_label_name' => 'Request Completed',
    ]);

    Http::fake(fn ($request) => trelloTemplateStructureHttpResponse($request)
        ?? Http::response(['id' => 'ok']));

    $customer = Customer::query()->create([
        'name' => 'Label Client',
        'email' => 'label-client@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_label',
        'trello_writing_requests_list_id' => 'list_requests',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'addLabelToCard',
            'data' => [
                'board' => ['id' => 'board_label'],
                'list' => ['id' => 'list_requests'],
                'card' => [
                    'id' => 'card_label_req',
                    'name' => 'Newsletter draft',
                    'desc' => 'Tone: friendly. Audience: SaaS founders.',
                    'idList' => 'list_requests',
                ],
                'label' => ['id' => 'label_1', 'name' => 'Request Completed'],
            ],
        ],
    ])->assertOk()->assertJson(['status' => 'processed']);

    $task = TrelloTask::query()->where('trello_card_id', 'card_label_req')->first();
    expect($task)->not->toBeNull();

    $version = TrelloTaskVersion::query()->where('trello_task_id', $task->id)->first();
    expect($version)->not->toBeNull()
        ->and($version->trigger)->toBe(TrelloTaskVersionTrigger::RequestCompleted)
        ->and($version->submitted_at)->not->toBeNull();

    Queue::assertPushed(ProcessTrelloTaskJob::class);
    Mail::assertSent(NewWritingRequestMail::class, fn (NewWritingRequestMail $mail) => $mail->hasTo('ops@maywrites.test'));
});

test('addLabelToCard ignores labels other than Request Completed', function () {
    Queue::fake();

    Customer::query()->create([
        'name' => 'Label Client',
        'email' => 'label-other@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_label_other',
        'trello_writing_requests_list_id' => 'list_requests',
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'addLabelToCard',
            'data' => [
                'board' => ['id' => 'board_label_other'],
                'list' => ['id' => 'list_requests'],
                'card' => ['id' => 'card_other', 'name' => 'Draft', 'desc' => 'Body'],
                'label' => ['id' => 'label_2', 'name' => 'IN PROGRESS'],
            ],
        ],
    ])->assertOk()->assertJson(['status' => 'ignored']);

    Queue::assertNotPushed(ProcessTrelloTaskJob::class);
    expect(TrelloTask::query()->count())->toBe(0);
});
