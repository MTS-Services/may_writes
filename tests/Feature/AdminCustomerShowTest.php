<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\TrelloTaskVersionTrigger;
use App\Enums\WritingWorkflowStatus;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use App\Models\User;

test('admin customer show paginates tasks with version summaries', function () {
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

    $v1 = TrelloTaskVersion::query()->create([
        'trello_task_id' => $task->id,
        'version_number' => 1,
        'trigger' => TrelloTaskVersionTrigger::Created,
        'title' => $task->title,
        'description' => $task->description,
        'pipeline_status' => TrelloTaskPipelineStatus::Summarized,
        'document_path' => 'clients/show-client/card_show/v1_brief.docx',
        'document_filename' => 'v1_brief.docx',
    ]);

    $v2 = TrelloTaskVersion::query()->create([
        'trello_task_id' => $task->id,
        'version_number' => 2,
        'trigger' => TrelloTaskVersionTrigger::Updated,
        'title' => $task->title,
        'description' => 'Updated description',
        'pipeline_status' => TrelloTaskPipelineStatus::Summarized,
        'document_path' => 'clients/show-client/card_show/v2_brief.docx',
        'document_filename' => 'v2_brief.docx',
    ]);

    $task->update(['latest_version_id' => $v2->id]);

    $response = $this->actingAs($user)->get(route('admin.customers.show', $customer));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/customers/show')
            ->has('tasks.data', 1)
            ->where('tasks.data.0.workflow_status', 'initialized')
            ->where('tasks.data.0.workflow_label', 'Initialized')
            ->where('tasks.data.0.pipeline_status', 'summarized')
            ->where('tasks.data.0.versions_count', 2)
            ->has('tasks.data.0.versions', 2)
            ->where('tasks.data.0.versions.0.version_number', 2)
            ->where('tasks.data.0.versions.0.is_latest', true)
            ->where('tasks.data.0.versions.1.version_number', 1)
            ->where('customer.status', 'active')
            ->where('tasks.total', 1));
});

test('admin customer show paginates tasks across pages', function () {
    $user = User::factory()->create();

    $customer = Customer::query()->create([
        'name' => 'Paged Client',
        'email' => 'paged@example.com',
        'status' => CustomerStatus::Active,
    ]);

    foreach (range(1, 12) as $index) {
        TrelloTask::query()->create([
            'customer_id' => $customer->id,
            'trello_card_id' => "card_{$index}",
            'trello_board_id' => 'board_paged',
            'title' => "Task {$index}",
            'description' => 'desc',
            'workflow_status' => WritingWorkflowStatus::Initialized,
        ]);
    }

    $this->actingAs($user)
        ->get(route('admin.customers.show', $customer))
        ->assertInertia(fn ($page) => $page
            ->where('tasks.total', 12)
            ->where('tasks.per_page', 10)
            ->where('tasks.last_page', 2)
            ->has('tasks.data', 10));

    $this->actingAs($user)
        ->get(route('admin.customers.show', ['customer' => $customer, 'page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->where('tasks.current_page', 2)
            ->has('tasks.data', 2));
});
