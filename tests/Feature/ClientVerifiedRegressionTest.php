<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Jobs\OnboardCustomerJob;
use App\Mail\WelcomeMail;
use App\Models\Customer;
use App\Services\TrelloService;
use App\Services\TrelloTemplateBoardService;
use Illuminate\Support\Facades\Mail;

test('welcome card is distinct from requests instruction sentinel in config', function () {
    expect(config('trello_template.welcome_card.list_key'))->toBe('requests')
        ->and(config('trello_template.instruction_cards.requests_instructions.list_key'))->toBe('requests')
        ->and(config('trello_template.welcome_card.name'))->not->toBe(
            config('trello_template.instruction_cards.requests_instructions.name'),
        );
});

test('template board service identifies example card names by prefix', function () {
    $service = app(TrelloTemplateBoardService::class);

    expect($service->isExampleCardName('EXAMPLE (Blog Post) - Sleep'))->toBeTrue()
        ->and($service->isExampleCardName('My blog post'))->toBeFalse();
});

test('onboard customer job sends welcome mail once', function () {
    Mail::fake();

    $customer = Customer::query()->create([
        'name' => 'Regression User',
        'email' => 'regression@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $this->mock(TrelloService::class, function ($mock) use ($customer): void {
        $mock->shouldReceive('onboardCustomer')
            ->once()
            ->withArgs(fn (Customer $arg) => $arg->is($customer))
            ->andReturn([
                'board_id' => 'board_regression',
                'board_url' => 'https://trello.com/b/board_regression',
                'member_id' => 'member_regression',
                'webhook_id' => 'hook_regression',
                'reused_board' => false,
                'writing_requests_list_id' => 'list_requests',
                'in_progress_list_id' => 'list_in_progress',
                'completed_list_id' => 'list_delivered',
                'draft_review_list_id' => 'list_draft_review',
                'revisions_list_id' => 'list_revisions',
                'delivered_list_id' => 'list_delivered',
                'instruction_card_ids' => ['requests_instructions' => 'card_requests_instructions'],
                'welcome_card_id' => 'card_welcome',
            ]);
    });

    (new OnboardCustomerJob($customer))->handle(app(TrelloService::class));

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->hasTo('regression@example.com'));
});
