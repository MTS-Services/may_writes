<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\TrelloSetting;
use App\Services\TrelloTemplateBoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('ensureTemplateBoardStructure recreates missing list instead of failing sync', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
        'services.trello.workspace_id' => 'org_workspace',
    ]);

    TrelloSetting::query()->create([
        'template_board_id' => null,
        'background_id' => null,
    ]);

    $staleListPuts = 0;

    Http::fake(function ($request) use (&$staleListPuts) {
        $url = $request->url();
        $method = $request->method();

        if ($method === 'GET' && preg_match('#/boards/board_sync$#', parse_url($url, PHP_URL_PATH) ?: '')) {
            return Http::response(['closed' => false], 200);
        }

        if ($method === 'GET' && str_contains($url, '/boards/board_sync/lists')) {
            return Http::response([
                [
                    'id' => 'list_stale',
                    'name' => 'REQUESTS (QUEUE) COLUMN',
                    'closed' => false,
                ],
                ...array_values(array_filter(
                    trelloTemplateListFixtures(),
                    static fn (array $list): bool => ($list['id'] ?? '') !== 'list_requests',
                )),
            ], 200);
        }

        if ($method === 'GET' && str_contains($url, '/boards/board_sync/cards')) {
            return Http::response(array_merge(
                trelloTemplateInstructionCardFixtures(),
                [trelloTemplateWelcomeCardFixture()],
            ), 200);
        }

        if ($method === 'GET' && str_contains($url, '/boards/board_sync/labels')) {
            return Http::response([], 200);
        }

        if ($method === 'PUT' && str_contains($url, '/lists/list_stale')) {
            $staleListPuts++;

            return Http::response('The requested resource was not found.', 404);
        }

        if ($method === 'PUT' && str_contains($url, '/lists/')) {
            return Http::response(['id' => 'list_ok'], 200);
        }

        if ($method === 'POST' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/lists')) {
            $name = (string) data_get($request->data(), 'name', '');
            $listKey = array_search($name, config('trello_template.lists'), true);

            return Http::response([
                'id' => $listKey ? 'list_'.$listKey : 'list_new',
                'name' => $name,
            ], 200);
        }

        if ($method === 'POST' && str_contains($url, '/idLabels')) {
            return Http::response([], 200);
        }

        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return Http::response($templateResponse, 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Sync Test',
        'email' => 'sync-test@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_sync',
        'trello_board_url' => 'https://trello.com/b/board_sync',
        'trello_onboarded_at' => now(),
        'trello_writing_requests_list_id' => 'list_stale',
    ]);

    $layout = app(TrelloTemplateBoardService::class)->ensureTemplateBoardStructure($customer->fresh());

    expect($layout->listIds['requests'])->toBe('list_requests')
        ->and($staleListPuts)->toBeGreaterThan(0);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/lists')
        && ($request->data()['name'] ?? '') === 'REQUESTS (QUEUE) COLUMN');
});
