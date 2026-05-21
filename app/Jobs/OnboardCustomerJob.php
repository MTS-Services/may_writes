<?php

namespace App\Jobs;

use App\Enums\TrelloOnboardingStatus;
use App\Mail\WelcomeMail;
use App\Models\Customer;
use App\Notifications\BillingOnboardingFailedNotification;
use App\Services\TrelloService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class OnboardCustomerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(public Customer $customer) {}

    public function handle(): void
    {
        $this->customer->refresh();

        if ($this->customer->trello_onboarded_at !== null) {
            return;
        }

        try {
            $result = app(TrelloService::class)->onboardCustomer($this->customer);

            $this->customer->update([
                'trello_board_id' => $result['board_id'],
                'trello_board_url' => $result['board_url'],
                'trello_member_id' => $result['member_id'],
                'trello_webhook_id' => $result['webhook_id'],
                'trello_writing_requests_list_id' => $result['writing_requests_list_id'],
                'trello_in_progress_list_id' => $result['in_progress_list_id'],
                'trello_draft_review_list_id' => $result['draft_review_list_id'] ?? null,
                'trello_revisions_list_id' => $result['revisions_list_id'] ?? null,
                'trello_delivered_list_id' => $result['delivered_list_id'] ?? null,
                'trello_completed_list_id' => $result['completed_list_id'],
                'trello_instruction_card_ids' => $result['instruction_card_ids'] ?? null,
                'trello_welcome_card_id' => $result['welcome_card_id'],
                'trello_invited_at' => now(),
                'trello_onboarded_at' => now(),
                'trello_onboarding_status' => TrelloOnboardingStatus::Completed,
                'trello_onboarding_last_error' => null,
            ]);

            if ($this->customer->welcome_email_sent_at === null) {
                Mail::to($this->customer->email)->send(new WelcomeMail($this->customer));

                $this->customer->update([
                    'welcome_email_sent_at' => now(),
                ]);
            }

            Log::info('Customer onboarding completed', [
                'customer_id' => $this->customer->id,
                'reused_board' => $result['reused_board'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Customer onboarding failed', [
                'customer_id' => $this->customer->id,
                'trello_board_id' => $this->customer->trello_board_id,
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

        $this->customer->refresh();

        if ($this->customer->trello_onboarded_at !== null) {
            return;
        }

        $this->customer->update([
            'trello_onboarding_status' => TrelloOnboardingStatus::Failed,
            'trello_onboarding_last_error' => Str::limit($exception->getMessage(), 500, ''),
        ]);

        $to = config('billing.alerts.onboarding_failure_email');

        if (filled($to)) {
            Notification::route('mail', $to)
                ->notify(new BillingOnboardingFailedNotification($this->customer, $exception));
        }
    }
}
