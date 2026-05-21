<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\WritingWorkflowStatus;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use App\Models\User;

test('admin customer show includes workflow and pipeline status for tasks', function () {
    $user = User::factory()->create();

    $customer = Customer::query()->create([
        'name' => 'Show Client',
        'email' => 'show@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $task = TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_show',
        'trello_board_id' => 'board_show',
        'title' => 'Air booking brief',
        'description' => 'Write vision and mission',
        'workflow_status' => WritingWorkflowStatus::Initialized,
    ]);

    $version = TrelloTaskVersion::query()->create([
        'trello_task_id' => $task->id,
        'version_number' => 1,
        'title' => $task->title,
        'description' => $task->description,
        'pipeline_status' => TrelloTaskPipelineStatus::Summarized,
        'document_path' => 'clients/show-client/card_show/v1_brief.docx',
        'document_filename' => 'v1_brief.docx',
    ]);

    $task->update(['latest_version_id' => $version->id]);

    $response = $this->actingAs($user)->get(route('admin.customers.show', $customer));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/customers/show')
            ->has('tasks', 1)
            ->where('tasks.0.workflow_status', 'initialized')
            ->where('tasks.0.workflow_label', 'Initialized')
            ->where('tasks.0.pipeline_status', 'summarized')
            ->where('tasks.0.has_document', true)
            ->where('customer.status', 'active'));
});
