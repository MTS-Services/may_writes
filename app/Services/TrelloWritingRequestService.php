<?php

namespace App\Services;

use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\TrelloTaskVersionTrigger;
use App\Enums\WritingWorkflowStatus;
use App\Jobs\ProcessTrelloTaskJob;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;

class TrelloWritingRequestService
{
    public function __construct(
        private CardContentExtractor $contentExtractor,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createTaskFromWebhook(
        Customer $customer,
        string $cardId,
        string $boardId,
        string $listId,
        string $title,
        ?string $description,
        array $payload,
        TrelloTaskVersionTrigger $trigger = TrelloTaskVersionTrigger::Created,
    ): TrelloTaskVersion {
        $description = trim((string) $description);
        $fingerprint = TrelloTask::descriptionFingerprint($description);

        $task = TrelloTask::query()->firstOrCreate(
            ['trello_card_id' => $cardId],
            [
                'customer_id' => $customer->id,
                'trello_board_id' => $boardId,
                'trello_list_id' => $listId,
                'title' => $title,
                'description' => $description,
                'workflow_status' => WritingWorkflowStatus::Initialized,
                'content_fingerprint' => $fingerprint,
            ],
        );

        $task->update([
            'title' => $title,
            'description' => $description,
            'trello_list_id' => $listId,
            'workflow_status' => WritingWorkflowStatus::Initialized,
        ]);

        $nextVersion = (int) $task->versions()->max('version_number') + 1;

        $aggregated = $this->safeAggregatedContent($cardId, $title, $description);

        $version = $task->versions()->create([
            'version_number' => $nextVersion,
            'trigger' => $trigger,
            'title' => $title,
            'description' => $description,
            'aggregated_content' => $aggregated,
            'content_fingerprint' => $fingerprint,
            'pipeline_status' => TrelloTaskPipelineStatus::Queued,
            'raw_payload' => $payload,
        ]);

        $task->update([
            'latest_version_id' => $version->id,
            'content_fingerprint' => $fingerprint,
        ]);

        ProcessTrelloTaskJob::dispatch($version->id)->onQueue('default');

        return $version;
    }

    public function shouldProcessDescriptionUpdate(
        TrelloTask $task,
        Customer $customer,
        string $listId,
        ?string $description,
    ): bool {
        if ($listId !== $customer->trello_writing_requests_list_id) {
            return false;
        }

        $fingerprint = TrelloTask::descriptionFingerprint($description);

        return $fingerprint !== $task->content_fingerprint;
    }

    private function safeAggregatedContent(string $cardId, string $title, string $description): string
    {
        try {
            return $this->contentExtractor->extract($cardId)->aggregatedForAi;
        } catch (\Throwable) {
            $sections = ["Title: {$title}"];

            if ($description !== '') {
                $sections[] = "Description:\n{$description}";
            }

            return implode("\n\n", $sections);
        }
    }
}
