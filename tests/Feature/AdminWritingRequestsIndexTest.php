<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\WritingWorkflowStatus;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use App\Models\User;

test('admin writing requests index lists tasks with versions ordered latest first', function () {
    $user = User::factory()->create();

    $customer = Customer::query()->create([
        'name' => 'Index Client',
        'email' => 'index@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $task = TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_index',
        'trello_board_id' => 'board_index',
        'title' => 'Index task',
        'description' => 'desc',
        'workflow_status' => WritingWorkflowStatus::Initialized,
    ]);

    TrelloTaskVersion::query()->create([
        'trello_task_id' => $task->id,
        'version_number' => 1,
        'title' => 'Index task',
        'description' => 'first',
        'pipeline_status' => TrelloTaskPipelineStatus::Summarized,
    ]);

    $v2 = TrelloTaskVersion::query()->create([
        'trello_task_id' => $task->id,
        'version_number' => 2,
        'title' => 'Index task',
        'description' => 'second',
        'pipeline_status' => TrelloTaskPipelineStatus::Summarized,
    ]);

    $task->update(['latest_version_id' => $v2->id]);

    $response = $this->actingAs($user)->get(route('admin.writing-requests'));

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('admin/writing-requests/index')
            ->has('tasks', 1)
            ->where('tasks.0.id', $task->id)
            ->where('tasks.0.versions.0.version_number', 2)
            ->where('tasks.0.versions.0.is_latest', true)
            ->where('tasks.0.versions.1.version_number', 1)
            ->where('tasks.0.versions.1.is_latest', false)
            ->has('workflowStatuses', 6),
    );
});
