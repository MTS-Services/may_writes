<?php

namespace App\Jobs;

use App\Mail\WelcomeMail;
use App\Models\Customer;
use App\Services\TrelloService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OnboardCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(public Customer $customer) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Mail::to($this->customer->email)->send(new WelcomeMail($this->customer));

            $this->customer->update([
                'welcome_email_sent_at' => now(),
            ]);

            $result = app(TrelloService::class)->createBoardForCustomer($this->customer);
            $this->customer->update([
                'trello_board_id' => $result['board_id'],
                'trello_board_url' => $result['board_url'],
                'trello_member_id' => $result['member_id'],
                'trello_invited_at' => now(),
            ]);

            Log::info('Customer onboarding completed', ['customer_id' => $this->customer->id]);
        } catch (\Throwable $exception) {
            Log::error('Customer onboarding failed', [
                'customer_id' => $this->customer->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('OnboardCustomerJob permanently failed', [
            'customer_id' => $this->customer->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
