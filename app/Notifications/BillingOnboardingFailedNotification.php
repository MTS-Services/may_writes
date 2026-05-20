<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingOnboardingFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Customer $customer,
        public \Throwable $exception,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('MayWrites: Trello onboarding failed after all retries')
            ->greeting('Action required')
            ->line('A customer paid but Trello onboarding did not complete after all queue retries.')
            ->line('Customer ID: '.$this->customer->id)
            ->line('Email: '.$this->customer->email)
            ->line('Stripe customer: '.($this->customer->stripe_id ?? 'n/a'))
            ->line('Error: '.$this->exception->getMessage())
            ->line('Retry with: php artisan customers:retry-trello-onboarding '.$this->customer->email);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
        ];
    }
}
