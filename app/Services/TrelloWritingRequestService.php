<?php

namespace App\Services;

use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\TrelloTaskVersionTrigger;
use App\Enums\WritingWorkflowStatus;
use App\Jobs\ProcessTrelloTaskJob;
use App\Mail\NewWritingRequestMail;
use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use Illuminate\Support\Facades\Mail;

class TrelloWritingRequestService
{
    public function __construct(
        private CardContentExtractor $contentExtractor,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function trackTaskFromWebhook(
        Customer $customer,
        string $cardId,
        string $boardId,
        string $listId,
        string $title,
        ?string $description,
    ): TrelloTask {
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
            'content_fingerprint' => $fingerprint,
        ]);

        return $task;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function processRequestCompletedLabel(
        Customer $customer,
        string $cardId,
        string $boardId,
        string $listId,
        string $title,
        ?string $description,
        array $payload,
    ): ?TrelloTaskVersion {
        if ($this->hasActiveSubmission($cardId)) {
            return null;
        }

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
            'content_fingerprint' => $fingerprint,
        ]);

        $nextVersion = (int) $task->versions()->max('version_number') + 1;
        $aggregated = $this->safeAggregatedContent($cardId, $title, $description);
        $submittedAt = now();

        $version = $task->versions()->create([
            'version_number' => $nextVersion,
            'trigger' => TrelloTaskVersionTrigger::RequestCompleted,
            'title' => $title,
            'description' => $description,
            'aggregated_content' => $aggregated,
            'content_fingerprint' => $fingerprint,
            'pipeline_status' => TrelloTaskPipelineStatus::Queued,
            'submitted_at' => $submittedAt,
            'raw_payload' => $payload,
        ]);

        $task->update([
            'latest_version_id' => $version->id,
            'content_fingerprint' => $fingerprint,
        ]);

        ProcessTrelloTaskJob::dispatch($version->id)->onQueue('default');

        $this->notifyAdminOfNewRequest($customer, $task, $version);

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

    private function hasActiveSubmission(string $cardId): bool
    {
        return TrelloTaskVersion::query()
            ->where('trigger', TrelloTaskVersionTrigger::RequestCompleted)
            ->whereHas('task', fn ($query) => $query->where('trello_card_id', $cardId))
            ->whereIn('pipeline_status', [
                TrelloTaskPipelineStatus::Queued,
                TrelloTaskPipelineStatus::Processing,
                TrelloTaskPipelineStatus::Summarized,
            ])
            ->exists();
    }

    private function notifyAdminOfNewRequest(Customer $customer, TrelloTask $task, TrelloTaskVersion $version): void
    {
        $email = config('billing.alerts.new_request_email');

        if (! filled($email)) {
            return;
        }

        Mail::to($email)->send(new NewWritingRequestMail($customer, $task, $version));
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
