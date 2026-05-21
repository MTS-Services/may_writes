<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\WritingWorkflowStatus;
use App\Jobs\ProcessTrelloTaskJob;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use App\Services\CardContentExtractor;
use App\Services\ClaudeService;
use App\Services\DocumentService;
use App\Services\RequestWordLimitService;
use App\Services\TrelloService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('process job truncates description and updates trello card', function () {
    Storage::fake('local');

    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
        'services.anthropic.api_key' => 'test_anthropic',
        'filesystems.default' => 'local',
    ]);

    Http::fake([
        'https://api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'text',
                'text' => json_encode([
                    'title' => 'Brief',
                    'description_summary' => 'Summary',
                    'content_type' => 'Blog',
                    'goal_objective' => 'Leads',
                    'target_audience' => 'Founders',
                    'tone_style' => 'Professional',
                    'length_words' => '500',
                    'cta_recommendations' => '',
                    'references_examples' => '',
                    'additional_requirements' => '',
                    'writer_notes' => '',
                ]),
            ]],
        ]),
        'https://api.trello.com/*' => function ($request) {
            $templateResponse = trelloTemplateStructureHttpResponse($request);

            if ($templateResponse !== null) {
                return Http::response($templateResponse);
            }

            return Http::response(['id' => 'ok']);
        },
    ]);

    $plan = Plan::query()->create([
        'name' => 'Starter',
        'slug' => 'starter-job-test',
        'stripe_price_id' => 'price_test',
        'price' => 10,
        'active_requests' => 1,
        'words_per_request' => 3,
        'features' => [],
        'is_featured' => false,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $customer = Customer::query()->create([
        'name' => 'Job Client',
        'email' => 'job@example.com',
        'status' => CustomerStatus::Active,
        'plan_id' => $plan->id,
    ]);

    $task = TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_job',
        'trello_board_id' => 'board_job',
        'title' => 'Long request',
        'description' => 'one two three four five',
        'workflow_status' => WritingWorkflowStatus::Initialized,
    ]);

    $version = TrelloTaskVersion::query()->create([
        'trello_task_id' => $task->id,
        'version_number' => 1,
        'title' => $task->title,
        'description' => $task->description,
        'pipeline_status' => TrelloTaskPipelineStatus::Queued,
    ]);

    (new ProcessTrelloTaskJob($version->id))->handle(
        app(RequestWordLimitService::class),
        app(CardContentExtractor::class),
        app(ClaudeService::class),
        app(DocumentService::class),
        app(TrelloService::class),
    );

    $version->refresh();

    expect($version->was_truncated)->toBeTrue()
        ->and($version->word_count_original)->toBe(5)
        ->and($version->word_count_processed)->toBe(3)
        ->and($version->pipeline_status)->toBe(TrelloTaskPipelineStatus::Summarized)
        ->and($version->document_path)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_contains($request->url(), '/cards/card_job')
        && ($request->data()['desc'] ?? '') === 'one two three');
});
