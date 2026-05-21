<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\WritingWorkflowStatus;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('admin can patch workflow status and move card on trello', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(fn ($request) => trelloTemplateStructureHttpResponse($request)
        ?? Http::response(['id' => 'ok']));

    $user = User::factory()->create();
    $customer = Customer::query()->create([
        'name' => 'Admin Client',
        'email' => 'admin-client@example.com',
        'status' => CustomerStatus::Active,
        'trello_in_progress_list_id' => 'list_in_progress',
    ]);

    $task = TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_admin',
        'trello_board_id' => 'board_admin',
        'title' => 'Admin task',
        'workflow_status' => WritingWorkflowStatus::Initialized,
    ]);

    $this->actingAs($user)
        ->patch(route('admin.writing-requests.workflow-status', $task), [
            'workflow_status' => WritingWorkflowStatus::InProgress->value,
        ])
        ->assertRedirect();

    $task->refresh();

    expect($task->workflow_status)->toBe(WritingWorkflowStatus::InProgress)
        ->and(Cache::get('trello:suppress_webhook:card_admin'))->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_contains($request->url(), '/cards/card_admin')
        && ($request->data()['idList'] ?? '') === 'list_in_progress');
});
