<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\TrelloService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OffboardCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(public Customer $customer) {}

    public function handle(TrelloService $trelloService): void
    {
        $this->customer->refresh();

        if ($this->customer->trello_offboarded_at !== null) {
            return;
        }

        if (! filled($this->customer->trello_board_id)) {
            return;
        }

        try {
            if (filled($this->customer->trello_member_id)) {
                $trelloService->removeMemberFromBoard(
                    (string) $this->customer->trello_board_id,
                    (string) $this->customer->trello_member_id,
                );
            }

            if (filled($this->customer->trello_webhook_id)) {
                $trelloService->deleteBoardWebhook((string) $this->customer->trello_webhook_id);
            }

            $this->customer->update([
                'trello_board_id' => null,
                'trello_board_url' => null,
                'trello_member_id' => null,
                'trello_webhook_id' => null,
                'trello_invited_at' => null,
                'trello_offboarded_at' => now(),
            ]);

            Log::info('Customer offboarding completed', ['customer_id' => $this->customer->id]);
        } catch (\Throwable $exception) {
            Log::error('Customer offboarding failed', [
                'customer_id' => $this->customer->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('OffboardCustomerJob permanently failed', [
            'customer_id' => $this->customer->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
