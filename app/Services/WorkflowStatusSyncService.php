<?php

namespace App\Services;

use App\Enums\WritingWorkflowStatus;
use App\Models\Customer;
use App\Models\TrelloTask;
use Illuminate\Support\Facades\Cache;

class WorkflowStatusSyncService
{
    public function __construct(private TrelloService $trello) {}

    public function listIdToStatus(Customer $customer, string $listId): WritingWorkflowStatus
    {
        $map = [
            $customer->trello_writing_requests_list_id => 'requests',
            $customer->trello_in_progress_list_id => 'in_progress',
            $customer->trello_draft_review_list_id => 'draft_review',
            $customer->trello_revisions_list_id => 'revisions',
            $customer->trello_delivered_list_id => 'delivered',
            $customer->trello_completed_list_id => 'delivered',
        ];

        $listKey = $map[$listId] ?? null;

        if ($listKey === null) {
            return WritingWorkflowStatus::Other;
        }

        $statusValue = config("trello_template.workflow_status_by_list_key.{$listKey}");

        return WritingWorkflowStatus::tryFrom((string) $statusValue) ?? WritingWorkflowStatus::Other;
    }

    public function statusToListId(Customer $customer, WritingWorkflowStatus $status): ?string
    {
        if ($status === WritingWorkflowStatus::Other) {
            return null;
        }

        foreach ($this->workflowStatusByListKeyConfig() as $listKey => $statusValue) {
            if ($statusValue === $status->value) {
                return match ($listKey) {
                    'requests' => $customer->trello_writing_requests_list_id,
                    'in_progress' => $customer->trello_in_progress_list_id,
                    'draft_review' => $customer->trello_draft_review_list_id,
                    'revisions' => $customer->trello_revisions_list_id,
                    'delivered' => $customer->trello_delivered_list_id ?? $customer->trello_completed_list_id,
                    default => null,
                };
            }
        }

        return null;
    }

    public function syncFromTrelloList(TrelloTask $task, string $listId): void
    {
        if ($this->isWebhookSuppressed($task->trello_card_id)) {
            return;
        }

        $task->update([
            'workflow_status' => $this->listIdToStatus($task->customer, $listId),
            'trello_list_id' => $listId,
        ]);
    }

    public function syncFromAdmin(TrelloTask $task, WritingWorkflowStatus $status): void
    {
        $listId = $this->statusToListId($task->customer, $status);

        if (filled($listId)) {
            $this->trello->putCard($task->trello_card_id, ['idList' => $listId]);
            Cache::put($this->suppressCacheKey($task->trello_card_id), true, now()->addSeconds(15));
        }

        $task->update([
            'workflow_status' => $status,
            'trello_list_id' => $listId ?? $task->trello_list_id,
        ]);
    }

    public function isWebhookSuppressed(string $cardId): bool
    {
        return (bool) Cache::get($this->suppressCacheKey($cardId), false);
    }

    private function suppressCacheKey(string $cardId): string
    {
        return "trello:suppress_webhook:{$cardId}";
    }

    /**
     * @return array<string, string>
     */
    private function workflowStatusByListKeyConfig(): array
    {
        $config = config('trello_template.workflow_status_by_list_key');

        return is_array($config) ? $config : [];
    }
}
