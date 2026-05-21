<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\WritingWorkflowStatus;
use App\Models\Customer;
use App\Models\TrelloTask;

test('updateCard list move maps workflow status from template lists', function () {
    $customer = Customer::query()->create([
        'name' => 'Mover',
        'email' => 'mover@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_move',
        'trello_writing_requests_list_id' => 'list_requests',
        'trello_in_progress_list_id' => 'list_in_progress',
        'trello_draft_review_list_id' => 'list_draft_review',
        'trello_revisions_list_id' => 'list_revisions',
        'trello_delivered_list_id' => 'list_delivered',
    ]);

    $task = TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_move',
        'trello_board_id' => 'board_move',
        'title' => 'Move me',
        'workflow_status' => WritingWorkflowStatus::Initialized,
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'updateCard',
            'data' => [
                'board' => ['id' => 'board_move'],
                'card' => ['id' => 'card_move', 'name' => 'Move me', 'idList' => 'list_in_progress'],
                'listBefore' => ['id' => 'list_requests'],
                'listAfter' => ['id' => 'list_in_progress'],
                'old' => ['idList' => 'list_requests'],
            ],
        ],
    ])->assertOk();

    $task->refresh();

    expect($task->workflow_status)->toBe(WritingWorkflowStatus::InProgress)
        ->and($task->trello_list_id)->toBe('list_in_progress');
});

test('updateCard list move to unknown list sets other status', function () {
    $customer = Customer::query()->create([
        'name' => 'Mover',
        'email' => 'unknown@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_unknown',
        'trello_writing_requests_list_id' => 'list_requests',
    ]);

    $task = TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_unknown',
        'trello_board_id' => 'board_unknown',
        'title' => 'Custom',
        'workflow_status' => WritingWorkflowStatus::Initialized,
    ]);

    $this->postJson(route('webhook.trello'), [
        'action' => [
            'type' => 'updateCard',
            'data' => [
                'board' => ['id' => 'board_unknown'],
                'card' => ['id' => 'card_unknown', 'name' => 'Custom', 'idList' => 'list_custom'],
                'listBefore' => ['id' => 'list_requests'],
                'listAfter' => ['id' => 'list_custom'],
                'old' => ['idList' => 'list_requests'],
            ],
        ],
    ])->assertOk();

    $task->refresh();

    expect($task->workflow_status)->toBe(WritingWorkflowStatus::Other);
});
