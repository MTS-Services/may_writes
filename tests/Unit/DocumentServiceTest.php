<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\WritingWorkflowStatus;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Storage;

test('generated docx escapes ampersands so document xml is valid', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $customer = Customer::query()->create([
        'name' => 'Ampersand & Co',
        'email' => 'ampersand@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $task = TrelloTask::query()->create([
        'customer_id' => $customer->id,
        'trello_card_id' => 'card_amp',
        'trello_board_id' => 'board_amp',
        'title' => 'Vision & Mission',
        'description' => 'Goals < 100 & partners > 5',
        'workflow_status' => WritingWorkflowStatus::Initialized,
    ]);

    $version = TrelloTaskVersion::query()->create([
        'trello_task_id' => $task->id,
        'version_number' => 2,
        'title' => $task->title,
        'description' => $task->description,
        'pipeline_status' => TrelloTaskPipelineStatus::Summarized,
    ]);

    $brief = [
        'title' => 'Vision & Mission Statement',
        'description_summary' => 'Summary for A & B partners.',
        'content_type' => 'Corporate/Brand Content - Vision & Mission Statements',
        'goal_objective' => 'Reach founders & investors.',
        'target_audience' => 'Travelers',
        'tone_style' => 'Professional',
        'length_words' => '150-300',
        'cta_recommendations' => 'Book & explore.',
        'references_examples' => 'Expedia & Booking.com',
        'additional_requirements' => '',
        'writer_notes' => '',
    ];

    $doc = app(DocumentService::class)->generateVersionDocument($task->load('customer'), $version, $brief);

    $zip = new ZipArchive;
    expect($zip->open($doc['absolute_path']))->toBeTrue();

    $documentXml = $zip->getFromName('word/document.xml');
    $zip->close();

    expect($documentXml)->not->toBeFalse();

    libxml_use_internal_errors(true);
    expect(simplexml_load_string($documentXml))->not->toBeFalse();

    expect($documentXml)
        ->toContain('Vision &amp; Mission')
        ->toContain('Goals &lt; 100 &amp; partners &gt; 5')
        ->not->toMatch('/&(?!amp;|lt;|gt;|quot;|apos;|#)/');
});
