<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Services\TrelloTemplateBoardService;
use Illuminate\Support\Facades\Http;

test('ensure template structure stores welcome card separate from instruction card', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Welcome Client',
        'email' => 'welcome-client@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_welcome',
        'trello_onboarded_at' => now(),
    ]);

    $layout = app(TrelloTemplateBoardService::class)->ensureTemplateBoardStructure($customer);

    $customer->refresh();

    expect($layout->welcomeCardId)->toBe('card_welcome')
        ->and($layout->instructionCardIds['requests_instructions'])->toBe('card_requests_instructions')
        ->and($customer->trello_welcome_card_id)->toBe('card_welcome')
        ->and($customer->trello_welcome_card_id)->not->toBe($customer->trello_instruction_card_ids['requests_instructions']);
});
