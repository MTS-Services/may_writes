<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\TrelloTask;
use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrelloWebhookActionHandler
{
    public function __construct(
        private TrelloService $trelloService,
        private TrelloTemplateBoardService $templateBoard,
        private TrelloWritingRequestService $writingRequests,
        private WorkflowStatusSyncService $workflowSync,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, WebhookLog $log): JsonResponse
    {
        $actionType = (string) data_get($payload, 'action.type', 'unknown');

        return match ($actionType) {
            'createCard' => $this->handleCreateCard($payload, $log),
            'updateCard' => $this->handleUpdateCard($payload, $log),
            'addLabelToCard' => $this->handleAddLabelToCard($payload, $log),
            'deleteCard' => $this->handleDeleteCard($payload, $log),
            'archiveList' => $this->handleProtectedListArchiveOrDelete($payload, $log),
            'deleteList' => $this->handleProtectedListArchiveOrDelete($payload, $log),
            'updateList' => $this->handleUpdateList($payload, $log),
            default => $this->markProcessedAndIgnore($log),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleUpdateList(array $payload, WebhookLog $log): JsonResponse
    {
        $listClosed = (bool) data_get($payload, 'action.data.list.closed', false);
        $wasClosed = (bool) data_get($payload, 'action.data.old.closed', false);

        if ($listClosed && ! $wasClosed) {
            return $this->handleProtectedListArchiveOrDelete($payload, $log);
        }

        return $this->markProcessedAndIgnore($log);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleUpdateCard(array $payload, WebhookLog $log): JsonResponse
    {
        $boardId = (string) data_get($payload, 'action.data.board.id');
        $cardId = (string) data_get($payload, 'action.data.card.id');
        $cardName = (string) data_get($payload, 'action.data.card.name', '');

        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if ($customer === null || $cardId === '') {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        if ($this->shouldIgnoreCard($customer, $cardId, $cardName)) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $listAfterId = (string) (data_get($payload, 'action.data.listAfter.id')
            ?? data_get($payload, 'action.data.list.id')
            ?? '');
        $listBeforeId = (string) (data_get($payload, 'action.data.listBefore.id') ?? '');
        $oldListId = data_get($payload, 'action.data.old.idList');
        $listMoved = $oldListId !== null
            || (filled($listAfterId) && filled($listBeforeId) && $listAfterId !== $listBeforeId);

        if ($listMoved && filled($listAfterId)) {
            $task = TrelloTask::query()->where('trello_card_id', $cardId)->first();

            if ($task !== null) {
                $this->workflowSync->syncFromTrelloList($task, $listAfterId);
            }

            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'processed']);
        }

        $oldDesc = data_get($payload, 'action.data.old.desc');
        $descChanged = array_key_exists('desc', (array) data_get($payload, 'action.data.old', []));

        if (! $descChanged) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $queueListId = $customer->trello_writing_requests_list_id;
        $currentListId = filled($listAfterId) ? $listAfterId : (string) data_get($payload, 'action.data.card.idList', '');

        if ($queueListId === null || $currentListId !== $queueListId) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $newDescription = (string) data_get($payload, 'action.data.card.desc', '');
        $task = TrelloTask::query()->where('trello_card_id', $cardId)->first();

        if ($task === null) {
            $this->writingRequests->trackTaskFromWebhook(
                $customer,
                $cardId,
                $boardId,
                $currentListId,
                $cardName,
                $newDescription,
            );

            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'processed']);
        }

        if (! $this->writingRequests->shouldProcessDescriptionUpdate($task, $customer, $currentListId, $newDescription)) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $this->writingRequests->trackTaskFromWebhook(
            $customer,
            $cardId,
            $boardId,
            $currentListId,
            $cardName,
            $newDescription,
        );

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleAddLabelToCard(array $payload, WebhookLog $log): JsonResponse
    {
        $boardId = (string) data_get($payload, 'action.data.board.id');
        $cardId = (string) data_get($payload, 'action.data.card.id');
        $cardName = (string) data_get($payload, 'action.data.card.name', '');
        $labelName = (string) data_get($payload, 'action.data.label.name', '');
        $listId = (string) (data_get($payload, 'action.data.list.id')
            ?? data_get($payload, 'action.data.card.idList')
            ?? '');

        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if ($customer === null || $cardId === '' || $labelName === '') {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $expectedLabel = (string) config('trello_template.request_completed_label_name', 'Request Completed');

        if (strcasecmp($labelName, $expectedLabel) !== 0) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        if ($this->shouldIgnoreCard($customer, $cardId, $cardName)) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $queueListId = $customer->trello_writing_requests_list_id;

        if ($queueListId === null || ($listId !== '' && $listId !== $queueListId)) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $description = (string) data_get($payload, 'action.data.card.desc', '');

        $version = $this->writingRequests->processRequestCompletedLabel(
            $customer,
            $cardId,
            $boardId,
            $listId !== '' ? $listId : (string) $queueListId,
            $cardName,
            $description,
            $payload,
        );

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json([
            'status' => $version === null ? 'ignored' : 'processed',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleProtectedListArchiveOrDelete(array $payload, WebhookLog $log): JsonResponse
    {
        $actionType = (string) data_get($payload, 'action.type', '');
        $boardId = (string) data_get($payload, 'action.data.board.id');
        $listId = (string) data_get($payload, 'action.data.list.id');
        $listName = data_get($payload, 'action.data.list.name');

        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if ($customer === null || $listId === '') {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        if (! $this->templateBoard->isProtectedList($customer, $listId, is_string($listName) ? $listName : null)) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        try {
            $this->trelloService->restoreProtectedList(
                $customer->fresh(),
                $listId,
                $actionType,
                is_string($listName) ? $listName : null,
            );
        } catch (\Throwable $exception) {
            Log::warning('Trello protected list restore failed', [
                'customer_id' => $customer->id,
                'action_type' => $actionType,
                'error' => $exception->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($exception->getMessage(), 500, ''),
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'processed']);
        }

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCreateCard(array $payload, WebhookLog $log): JsonResponse
    {
        $boardId = (string) data_get($payload, 'action.data.board.id');
        $cardId = (string) data_get($payload, 'action.data.card.id');
        $listId = (string) data_get($payload, 'action.data.list.id');
        $cardName = (string) data_get($payload, 'action.data.card.name', '');

        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if (! $customer || $cardId === '') {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Unknown board or card.',
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'ignored']);
        }

        $queueListId = $this->trelloService->resolveAndPersistWritingRequestsList($customer);
        $customer->refresh();

        if ($queueListId === null || $listId !== $queueListId) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        if ($this->shouldIgnoreCard($customer, $cardId, $cardName)) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $this->writingRequests->trackTaskFromWebhook(
            $customer,
            $cardId,
            $boardId,
            $listId,
            $cardName,
            data_get($payload, 'action.data.card.desc'),
        );

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleDeleteCard(array $payload, WebhookLog $log): JsonResponse
    {
        $boardId = (string) data_get($payload, 'action.data.board.id');
        $cardId = (string) data_get($payload, 'action.data.card.id');
        $cardName = (string) data_get($payload, 'action.data.card.name', '');

        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if ($customer === null || $cardId === '') {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        if ($this->templateBoard->isWelcomeCard($customer, $cardId, $cardName)) {
            try {
                $this->templateBoard->recreateWelcomeCard($customer->fresh());
            } catch (\Throwable $exception) {
                Log::warning('Trello welcome card recreate failed', [
                    'customer_id' => $customer->id,
                    'error' => $exception->getMessage(),
                ]);

                $log->update([
                    'status' => 'failed',
                    'error_message' => Str::limit($exception->getMessage(), 500, ''),
                    'processed_at' => now(),
                ]);

                return response()->json(['status' => 'processed']);
            }

            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'processed']);
        }

        $slug = $this->templateBoard->instructionSlugForCard($customer, $cardId, $cardName);

        if ($slug === null) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        try {
            $this->trelloService->recreateInstructionCard($customer->fresh(), $slug);
        } catch (\Throwable $exception) {
            Log::warning('Trello instruction card recreate failed', [
                'customer_id' => $customer->id,
                'board_id' => $boardId,
                'slug' => $slug,
                'error' => $exception->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($exception->getMessage(), 500, ''),
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'processed']);
        }

        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'processed']);
    }

    private function shouldIgnoreCard(Customer $customer, string $cardId, string $cardName): bool
    {
        if ($this->templateBoard->isWelcomeCard($customer, $cardId, $cardName)) {
            return true;
        }

        if ($this->templateBoard->instructionSlugForCard($customer, $cardId, $cardName) !== null) {
            return true;
        }

        if ($this->templateBoard->isExampleCardName($cardName)) {
            return true;
        }

        return false;
    }

    private function markProcessedAndIgnore(WebhookLog $log): JsonResponse
    {
        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'ignored']);
    }
}
