<?php

namespace App\Services;

use App\Enums\TrelloTaskStatus;
use App\Jobs\ProcessTrelloTaskJob;
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
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload, WebhookLog $log): JsonResponse
    {
        $actionType = (string) data_get($payload, 'action.type', 'unknown');

        return match ($actionType) {
            'createCard' => $this->handleCreateCard($payload, $log),
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

        if ($this->templateBoard->instructionSlugForCard($customer, $cardId, $cardName) !== null) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        if ($this->templateBoard->isExampleCardName($cardName)) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $trelloTask = TrelloTask::create([
            'customer_id' => $customer->id,
            'trello_card_id' => $cardId,
            'trello_board_id' => $boardId,
            'title' => $cardName,
            'description' => data_get($payload, 'action.data.card.desc'),
            'raw_payload' => $payload,
            'status' => TrelloTaskStatus::Received,
        ]);

        ProcessTrelloTaskJob::dispatch($trelloTask)->onQueue('default');

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

    private function markProcessedAndIgnore(WebhookLog $log): JsonResponse
    {
        $log->update(['status' => 'processed', 'processed_at' => now()]);

        return response()->json(['status' => 'ignored']);
    }
}
