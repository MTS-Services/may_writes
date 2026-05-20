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
            default => $this->markProcessedAndIgnore($log),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleCreateCard(array $payload, WebhookLog $log): JsonResponse
    {
        $boardId = (string) data_get($payload, 'action.data.board.id');
        $cardId = (string) data_get($payload, 'action.data.card.id');
        $listId = (string) data_get($payload, 'action.data.list.id');

        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if (! $customer || $cardId === '') {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Unknown board or card.',
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'ignored']);
        }

        $writingListId = $this->trelloService->resolveAndPersistWritingRequestsList($customer);
        $customer->refresh();

        if ($writingListId === null || $listId !== $writingListId) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        if (filled($customer->trello_welcome_card_id) && $cardId === $customer->trello_welcome_card_id) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        $trelloTask = TrelloTask::create([
            'customer_id' => $customer->id,
            'trello_card_id' => $cardId,
            'trello_board_id' => $boardId,
            'title' => (string) data_get($payload, 'action.data.card.name'),
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

        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if ($customer === null || $cardId === '') {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        if (! filled($customer->trello_welcome_card_id) || $cardId !== $customer->trello_welcome_card_id) {
            $log->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['status' => 'ignored']);
        }

        try {
            $newCardId = $this->trelloService->recreateWelcomeSentinel($customer->fresh());
            $customer->update(['trello_welcome_card_id' => $newCardId]);
        } catch (\Throwable $exception) {
            Log::warning('Trello welcome sentinel recreate failed', [
                'customer_id' => $customer->id,
                'board_id' => $boardId,
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
