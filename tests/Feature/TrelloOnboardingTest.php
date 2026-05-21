<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Jobs\OnboardCustomerJob;
use App\Mail\WelcomeMail;
use App\Models\Customer;
use App\Models\Plan;
use App\Services\TrelloService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
        'services.trello.template_board_id' => null,
        'services.trello.workspace_id' => 'org_workspace',
        'services.trello.allow_billable_guest' => false,
        'services.trello.board_name_suffix' => 'Writing Board',
        'app.url' => 'https://maywrites.test',
    ]);
});

test('onboard customer job runs trello before welcome mail', function () {
    Mail::fake();

    $customer = Customer::query()->create([
        'name' => 'New User',
        'email' => 'new-user@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $this->mock(TrelloService::class, function ($mock) use ($customer): void {
        $mock->shouldReceive('onboardCustomer')
            ->once()
            ->withArgs(fn (Customer $arg) => $arg->is($customer))
            ->andReturn([
                'board_id' => 'board_new',
                'board_url' => 'https://trello.com/b/board_new',
                'member_id' => 'member_new',
                'webhook_id' => 'hook_new',
                'reused_board' => false,
                'writing_requests_list_id' => 'list_requests',
                'in_progress_list_id' => 'list_in_progress',
                'completed_list_id' => 'list_delivered',
                'draft_review_list_id' => 'list_draft_review',
                'revisions_list_id' => 'list_revisions',
                'delivered_list_id' => 'list_delivered',
                'instruction_card_ids' => ['requests_instructions' => 'card_requests_instructions'],
                'welcome_card_id' => 'card_requests_instructions',
            ]);
    });

    (new OnboardCustomerJob($customer))->handle(app(TrelloService::class));

    $customer->refresh();

    expect($customer->trello_onboarded_at)->not->toBeNull()
        ->and($customer->trello_board_id)->toBe('board_new');

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail) => $mail->hasTo('new-user@example.com'));
});

test('onboard customer job skips when already onboarded', function () {
    Mail::fake();

    $customer = Customer::query()->create([
        'name' => 'Done',
        'email' => 'done@example.com',
        'status' => CustomerStatus::Active,
        'trello_onboarded_at' => now(),
        'trello_board_id' => 'board_done',
    ]);

    $this->mock(TrelloService::class, function ($mock): void {
        $mock->shouldNotReceive('onboardCustomer');
    });

    (new OnboardCustomerJob($customer))->handle(app(TrelloService::class));

    Mail::assertNothingSent();
});

test('onboard customer job resumes without sending welcome mail twice', function () {
    Mail::fake();

    $customer = Customer::query()->create([
        'name' => 'Resume',
        'email' => 'resume@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_partial',
        'trello_board_url' => 'https://trello.com/b/board_partial',
        'welcome_email_sent_at' => now()->subHour(),
    ]);

    $this->mock(TrelloService::class, function ($mock): void {
        $mock->shouldReceive('onboardCustomer')->once()->andReturn([
            'board_id' => 'board_partial',
            'board_url' => 'https://trello.com/b/board_partial',
            'member_id' => 'member_partial',
            'webhook_id' => 'hook_partial',
            'reused_board' => false,
            'writing_requests_list_id' => 'list_requests',
            'in_progress_list_id' => 'list_in_progress',
            'completed_list_id' => 'list_delivered',
            'draft_review_list_id' => 'list_draft_review',
            'revisions_list_id' => 'list_revisions',
            'delivered_list_id' => 'list_delivered',
            'instruction_card_ids' => [],
            'welcome_card_id' => null,
        ]);
    });

    (new OnboardCustomerJob($customer))->handle(app(TrelloService::class));

    Mail::assertNothingSent();
});

test('lookup mode reuses existing board discovered via trello search', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([
                ['id' => 'member_existing', 'email' => 'returning@example.com', 'username' => 'returning'],
            ], 200);
        }

        if (str_contains($url, '/members/member_existing/boards')) {
            return Http::response([
                [
                    'id' => 'board_existing',
                    'shortUrl' => 'https://trello.com/b/board_existing',
                    'name' => "Someone's Writing Board",
                    'closed' => false,
                    'idOrganization' => 'org_workspace',
                ],
            ], 200);
        }

        if (str_contains($url, '/boards/board_existing/members') && $request->method() === 'GET') {
            return Http::response([
                ['id' => 'member_existing', 'email' => 'returning@example.com', 'username' => 'returning'],
            ], 200);
        }

        if (str_contains($url, '/boards/board_existing/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/actions/comments')) {
            return Http::response(['id' => 'action_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Returning',
        'email' => 'returning@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeTrue()
        ->and($result['board_id'])->toBe('board_existing');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/boards') && ! str_contains($request->url(), '/members'));
});

test('lookup mode reuses any open workspace board without writing board suffix', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([
                ['id' => 'member_other', 'email' => 'other@example.com', 'username' => 'otheruser'],
            ], 200);
        }

        if (str_contains($url, '/members/member_other/boards')) {
            return Http::response([
                [
                    'id' => 'board_other',
                    'shortUrl' => 'https://trello.com/b/board_other',
                    'name' => 'Client Project Alpha',
                    'closed' => false,
                    'idOrganization' => 'org_workspace',
                ],
            ], 200);
        }

        if (str_contains($url, '/boards/board_other/members') && $request->method() === 'GET') {
            return Http::response([
                ['id' => 'member_other', 'email' => 'other@example.com', 'username' => 'otheruser'],
            ], 200);
        }

        if (str_contains($url, '/boards/board_other/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/actions/comments')) {
            return Http::response(['id' => 'action_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Other',
        'email' => 'other@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeTrue()
        ->and($result['board_id'])->toBe('board_other');
});

test('lookup mode prefers writing board when member has multiple workspace boards', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([
                ['id' => 'member_multi', 'email' => 'multi@example.com', 'username' => 'multiuser'],
            ], 200);
        }

        if (str_contains($url, '/members/member_multi/boards')) {
            return Http::response([
                [
                    'id' => 'board_generic',
                    'shortUrl' => 'https://trello.com/b/board_generic',
                    'name' => 'Generic Client Board',
                    'closed' => false,
                    'idOrganization' => 'org_workspace',
                ],
                [
                    'id' => 'board_writing',
                    'shortUrl' => 'https://trello.com/b/board_writing',
                    'name' => 'Multi Writing Board',
                    'closed' => false,
                    'idOrganization' => 'org_workspace',
                ],
            ], 200);
        }

        if (str_contains($url, '/boards/board_writing/members') && $request->method() === 'GET') {
            return Http::response([
                ['id' => 'member_multi', 'email' => 'multi@example.com', 'username' => 'multiuser'],
            ], 200);
        }

        if (str_contains($url, '/boards/board_writing/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/actions/comments')) {
            return Http::response(['id' => 'action_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Multi',
        'email' => 'multi@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeTrue()
        ->and($result['board_id'])->toBe('board_writing');
});

test('board name includes plan name when customer has plan', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_pro',
                'shortUrl' => 'https://trello.com/b/board_pro',
            ], 200);
        }

        if ($request->method() === 'PUT' && str_ends_with($url, '/boards/board_pro/members')) {
            return Http::response(['id' => 'member_pro', 'username' => 'prouser'], 200);
        }

        if (str_contains($url, '/boards/board_pro/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_pro/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'board-name-pro',
        'stripe_price_id' => 'price_board_name_pro',
        'price' => 899,
        'active_requests' => 2,
        'features' => ['Feature'],
        'is_featured' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $customer = Customer::query()->create([
        'name' => 'Pro User',
        'email' => 'pro-board@example.com',
        'status' => CustomerStatus::Active,
        'plan_id' => $plan->id,
    ]);

    app(TrelloService::class)->onboardCustomer($customer);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && preg_match('#/boards$#', parse_url($request->url(), PHP_URL_PATH) ?? '')
        && $request['name'] === "Pro User's Pro Writing Board");
});

test('lookup mode creates board and invites without allow billable guest when email not in trello', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_new',
                'shortUrl' => 'https://trello.com/b/board_new',
            ], 200);
        }

        if ($request->method() === 'PUT' && str_ends_with($url, '/boards/board_new/members')) {
            return Http::response(['id' => 'member_new', 'username' => 'newuser'], 200);
        }

        if (str_contains($url, '/boards/board_new/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_new/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Fresh',
        'email' => 'fresh@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeFalse()
        ->and($result['board_id'])->toBe('board_new');

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_ends_with($request->url(), '/boards/board_new/members')
            && ! array_key_exists('allowBillableGuest', $request->data());
    });
});

test('lookup mode still creates board when workspace scan returns model not found', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([
                ['id' => 'member_known', 'email' => 'known@example.com', 'username' => 'knownuser'],
            ], 200);
        }

        if (str_contains($url, '/members/member_known/boards') && ! str_contains($url, 'boardsInvited')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/members/member_known/boardsInvited')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response('model not found', 404);
        }

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_fallback',
                'shortUrl' => 'https://trello.com/b/board_fallback',
            ], 200);
        }

        if ($request->method() === 'PUT' && str_ends_with($url, '/boards/board_fallback/members')) {
            return Http::response(['id' => 'member_known', 'username' => 'knownuser'], 200);
        }

        if (str_contains($url, '/boards/board_fallback/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_fallback/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Known',
        'email' => 'known@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeFalse()
        ->and($result['board_id'])->toBe('board_fallback');
});

test('guest mode always creates new board even when member already has a workspace board', function () {
    config(['services.trello.allow_billable_guest' => true]);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_guest_new',
                'shortUrl' => 'https://trello.com/b/board_guest_new',
            ], 200);
        }

        if (str_contains($url, '/boards/board_guest_new/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if ($request->method() === 'PUT' && str_ends_with($url, '/boards/board_guest_new/members')) {
            return Http::response(['id' => 'member_guest', 'username' => 'guestuser'], 200);
        }

        if (str_contains($url, '/boards/board_guest_new/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Guest Mode',
        'email' => 'guestmode@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeFalse()
        ->and($result['board_id'])->toBe('board_guest_new');

    Http::assertSent(fn ($request) => $request->method() === 'POST' && preg_match('#/boards$#', parse_url($request->url(), PHP_URL_PATH) ?? ''));
});

test('guest mode retries invite with allow billable guest via member id', function () {
    config(['services.trello.allow_billable_guest' => true]);

    $emailInviteAttempts = 0;

    Http::fake(function ($request) use (&$emailInviteAttempts) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_new',
                'shortUrl' => 'https://trello.com/b/board_new',
            ], 200);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_new/members') && ! str_contains($url, '/members/member_')) {
            $emailInviteAttempts++;

            return Http::response(
                'Member not allowed to add a multi-board guest without allowBillableGuest parameter',
                400,
            );
        }

        if (str_contains($url, '/search/members')) {
            return Http::response([
                ['id' => 'member_guest', 'email' => 'guest@example.com', 'username' => 'guestuser'],
            ], 200);
        }

        if (str_contains($url, '/members/member_guest/boardsInvited')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_new/members/member_guest')) {
            return Http::response(['id' => 'member_guest', 'username' => 'guestuser'], 200);
        }

        if (str_contains($url, '/boards/board_new/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_new/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Guest',
        'email' => 'guest@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['member_id'])->toBe('member_guest')
        ->and($emailInviteAttempts)->toBe(1);

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/boards/board_new/members/member_guest')
            && ($request->data()['allowBillableGuest'] ?? null) === 'true';
    });
});

test('lookup mode resume on orphan board switches to existing workspace board when member is there', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([
                ['id' => 'member_resume', 'email' => 'resume-orphan@example.com', 'username' => 'resumeuser'],
            ], 200);
        }

        if (str_contains($url, '/members/member_resume/boards')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response([
                [
                    'id' => 'board_real',
                    'shortUrl' => 'https://trello.com/b/board_real',
                    'name' => 'Resume User Writing Board',
                    'closed' => false,
                ],
                [
                    'id' => 'board_orphan',
                    'shortUrl' => 'https://trello.com/b/board_orphan',
                    'name' => 'Orphan Board',
                    'closed' => false,
                ],
            ], 200);
        }

        if (str_contains($url, '/boards/board_real/members') && $request->method() === 'GET') {
            return Http::response([
                ['id' => 'member_resume', 'email' => 'resume-orphan@example.com', 'username' => 'resumeuser'],
            ], 200);
        }

        if (str_contains($url, '/boards/board_orphan/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_real/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/actions/comments')) {
            return Http::response(['id' => 'action_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Resume Orphan',
        'email' => 'resume-orphan@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_orphan',
        'trello_board_url' => 'https://trello.com/b/board_orphan',
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeTrue()
        ->and($result['board_id'])->toBe('board_real');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && preg_match('#/boards$#', parse_url($request->url(), PHP_URL_PATH) ?? ''));
});

test('lookup mode reinvites to existing board with billable guest when member was removed', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([
                ['id' => 'member_removed', 'email' => 'removed@example.com', 'username' => 'removeduser'],
            ], 200);
        }

        if (str_contains($url, '/members/member_removed/boards') && ! str_contains($url, 'boardsInvited')) {
            return Http::response([
                [
                    'id' => 'board_existing',
                    'shortUrl' => 'https://trello.com/b/board_existing',
                    'name' => 'Removed User Writing Board',
                    'closed' => false,
                    'idOrganization' => 'org_workspace',
                ],
            ], 200);
        }

        if (str_contains($url, '/members/member_removed/boardsInvited')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_existing/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_existing/members') && ! str_contains($url, '/members/member_')) {
            $params = $request->data();

            if (($params['allowBillableGuest'] ?? null) === 'true') {
                return Http::response(['id' => 'member_removed', 'username' => 'removeduser'], 200);
            }

            return Http::response(
                'Member not allowed to add a multi-board guest without allowBillableGuest parameter',
                400,
            );
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_existing/members/member_removed')) {
            return Http::response(['id' => 'member_removed', 'username' => 'removeduser'], 200);
        }

        if (str_contains($url, '/boards/board_existing/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/actions/comments')) {
            return Http::response(['id' => 'action_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Removed',
        'email' => 'removed@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeTrue()
        ->and($result['board_id'])->toBe('board_existing');

    Http::assertSent(function ($request) {
        return $request->method() === 'PUT'
            && str_contains($request->url(), '/boards/board_existing/members')
            && ! str_contains($request->url(), '/members/member_')
            && ($request->data()['allowBillableGuest'] ?? null) === 'true';
    });
});

test('lookup mode throws when billable guest required on new board and no existing board found', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_new',
                'shortUrl' => 'https://trello.com/b/board_new',
            ], 200);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_new/members')) {
            return Http::response(
                'Member not allowed to add a multi-board guest without allowBillableGuest parameter',
                400,
            );
        }

        if (str_contains($url, '/boards/board_new/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        return Http::response(['error' => 'unexpected'], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Blocked Guest',
        'email' => 'blocked@example.com',
        'status' => CustomerStatus::Active,
    ]);

    expect(fn () => app(TrelloService::class)->onboardCustomer($customer))
        ->toThrow(RuntimeException::class, 'TRELLO_ALLOW_BILLABLE_GUEST=true');
});

test('lookup mode reuses customer orphan board with billable reinvite when invite previously failed', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_orphan') && str_contains($url, 'closed')) {
            return Http::response(['closed' => false], 200);
        }

        if (str_contains($url, '/boards/board_orphan/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_orphan/members')) {
            $params = $request->data();

            if (($params['allowBillableGuest'] ?? null) === 'true') {
                return Http::response(['id' => 'member_1', 'username' => 'orphanuser'], 200);
            }

            return Http::response(
                'Member not allowed to add a multi-board guest without allowBillableGuest parameter',
                400,
            );
        }

        if (str_contains($url, '/boards/board_orphan/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/actions/comments')) {
            return Http::response(['id' => 'action_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Orphan',
        'email' => 'orphan@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_orphan',
        'trello_board_url' => 'https://trello.com/b/board_orphan',
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['reused_board'])->toBeTrue()
        ->and($result['board_id'])->toBe('board_orphan');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/boards'));
});

test('trello service resume does not create another board', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_partial') && str_contains($url, '/members') && $request->method() === 'GET') {
            return Http::response([
                ['id' => 'member_1', 'email' => 'partial@example.com', 'username' => 'partial'],
            ], 200);
        }

        if (str_contains($url, '/boards/board_partial') && str_contains($url, 'closed')) {
            return Http::response(['closed' => false], 200);
        }

        if (str_contains($url, '/boards/board_partial/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/actions/comments')) {
            return Http::response(['id' => 'action_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([
                ['id' => 'hook_existing', 'idModel' => 'board_partial', 'callbackURL' => 'https://maywrites.test/webhook/trello'],
            ], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Partial',
        'email' => 'partial@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_partial',
        'trello_board_url' => 'https://trello.com/b/board_partial',
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['board_id'])->toBe('board_partial');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/boards'));
});

test('lookup mode treats member already invited as successful onboarding', function () {
    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([
                ['id' => 'member_pending', 'email' => 'pending@example.com', 'username' => 'pendinguser'],
            ], 200);
        }

        if (str_contains($url, '/members/member_pending/boards') && ! str_contains($url, 'boardsInvited')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/members/member_pending/boardsInvited')) {
            return Http::response([
                ['id' => 'board_orphan'],
            ], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_orphan') && str_contains($url, 'closed')) {
            return Http::response(['closed' => false], 200);
        }

        if (str_contains($url, '/boards/board_orphan/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if (str_contains($url, '/boards/board_orphan/lists')) {
            return Http::response(trelloDefaultKanbanListFixtures(), 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_retry'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Pending',
        'email' => 'pending@example.com',
        'status' => CustomerStatus::Active,
        'trello_board_id' => 'board_orphan',
        'trello_board_url' => 'https://trello.com/b/board_orphan',
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['board_id'])->toBe('board_orphan')
        ->and($result['webhook_id'])->toBe('hook_retry')
        ->and($result['member_id'])->toBe('member_pending');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/cards')
        && ! str_contains($request->url(), '/idLabels')
        && ! str_contains($request->url(), '/actions/comments'));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/actions/comments'));
});

test('lookup mode creates board from config only when template board id is not set', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        $method = $request->method();

        if ($method === 'GET' && str_contains($url, '/boards/board_new/lists')) {
            return Http::response([], 200);
        }

        if ($method === 'GET' && str_contains($url, '/boards/board_new/cards')) {
            return Http::response([], 200);
        }

        if ($method === 'GET' && str_contains($url, '/boards/board_new/labels')) {
            return Http::response([], 200);
        }

        if ($method === 'POST' && str_ends_with(parse_url($url, PHP_URL_PATH) ?: '', '/lists')) {
            $name = (string) data_get($request->data(), 'name', 'list');
            $listKey = array_search($name, config('trello_template.lists'), true);

            return Http::response([
                'id' => $listKey ? 'list_'.$listKey : 'list_'.md5($name),
                'name' => $name,
            ], 200);
        }

        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_new',
                'shortUrl' => 'https://trello.com/b/board_new',
            ], 200);
        }

        if (str_contains($url, '/boards/board_new/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_new/members')) {
            return Http::response(['id' => 'member_1', 'username' => 'configuser'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'Config Only',
        'email' => 'config-only@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['board_id'])->toBe('board_new')
        ->and($result['writing_requests_list_id'])->toBe('list_requests')
        ->and($result['instruction_card_ids'])->toHaveCount(5);

    Http::assertSent(function ($request) {
        if ($request->method() !== 'POST' || ! preg_match('#/boards$#', parse_url($request->url(), PHP_URL_PATH) ?? '')) {
            return false;
        }

        $data = $request->data();

        return ! array_key_exists('idBoardSource', $data)
            && ! array_key_exists('keepFromSource', $data);
    });

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/lists'));
    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/cards') && ! str_contains($request->url(), '/idLabels'));
});

test('lookup mode copies from template board when template board id is set', function () {
    config(['services.trello.template_board_id' => 'template_board']);

    Http::fake(function ($request) {
        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        $url = $request->url();

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_new',
                'shortUrl' => 'https://trello.com/b/board_new',
            ], 200);
        }

        if (str_contains($url, '/boards/board_new/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_new/members')) {
            return Http::response(['id' => 'member_1', 'username' => 'copyuser'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    app(TrelloService::class)->onboardCustomer(Customer::query()->create([
        'name' => 'Copy',
        'email' => 'copy@example.com',
        'status' => CustomerStatus::Active,
    ]));

    Http::assertSent(function ($request) {
        if ($request->method() !== 'POST' || ! preg_match('#/boards$#', parse_url($request->url(), PHP_URL_PATH) ?? '')) {
            return false;
        }

        $data = $request->data();

        return ($data['idBoardSource'] ?? null) === 'template_board'
            && ($data['keepFromSource'] ?? null) === 'cards';
    });
});

test('lookup mode creates a list when board copy has no lists yet', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        $method = $request->method();

        if ($method === 'GET' && str_contains($url, '/boards/board_new/lists')) {
            return Http::response([], 200);
        }

        if ($method === 'GET' && str_contains($url, '/boards/board_new/cards')) {
            return Http::response([], 200);
        }

        if ($method === 'GET' && str_contains($url, '/boards/board_new/labels')) {
            return Http::response([], 200);
        }

        $templateResponse = trelloTemplateStructureHttpResponse($request);

        if ($templateResponse !== null) {
            return $templateResponse;
        }

        if (str_contains($url, '/search/members')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/organizations/org_workspace/boards')) {
            return Http::response([], 200);
        }

        if ($request->method() === 'POST' && preg_match('#/boards$#', parse_url($url, PHP_URL_PATH) ?? '')) {
            return Http::response([
                'id' => 'board_new',
                'shortUrl' => 'https://trello.com/b/board_new',
            ], 200);
        }

        if (str_contains($url, '/boards/board_new/members') && $request->method() === 'GET') {
            return Http::response([], 200);
        }

        if ($request->method() === 'PUT' && str_contains($url, '/boards/board_new/members')) {
            return Http::response(['id' => 'member_1', 'username' => 'newuser'], 200);
        }

        if ($request->method() === 'POST' && str_ends_with(parse_url($url, PHP_URL_PATH) ?? '', '/lists')) {
            $name = (string) data_get($request->data(), 'name', 'list');

            return Http::response([
                'id' => 'list_'.md5($name),
                'name' => $name,
            ], 200);
        }

        if (str_contains($url, '/cards') && $request->method() === 'POST') {
            return Http::response(['id' => 'card_1'], 200);
        }

        if (str_contains($url, '/tokens/test_token/webhooks')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/webhooks') && $request->method() === 'POST') {
            return Http::response(['id' => 'hook_1'], 200);
        }

        return Http::response(['error' => 'unexpected '.$url], 500);
    });

    $customer = Customer::query()->create([
        'name' => 'New',
        'email' => 'new-lists@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $result = app(TrelloService::class)->onboardCustomer($customer);

    expect($result['board_id'])->toBe('board_new');

    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/lists'));
});

test('checkout clears trello offboard fields on re-subscribe', function () {
    Queue::fake();

    config([
        'cashier.secret' => 'sk_test_fake',
        'cashier.webhook.secret' => 'whsec_test_secret',
    ]);

    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'reactivate-plan',
        'stripe_price_id' => 'price_reactivate',
        'price' => 899,
        'active_requests' => 2,
        'features' => ['Feature'],
        'is_featured' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $customer = Customer::query()->create([
        'name' => 'Returning',
        'email' => 'reactivate@example.com',
        'stripe_id' => 'cus_reactivate',
        'status' => CustomerStatus::Cancelled,
        'cancelled_at' => now()->subMonth(),
        'trello_offboarded_at' => now()->subMonth(),
        'trello_board_id' => null,
    ]);

    Mockery::mock('alias:Stripe\Subscription')
        ->shouldReceive('retrieve')
        ->andReturn((object) ['id' => 'sub_reactivate', 'trial_end' => null]);

    $event = (object) [
        'id' => 'evt_reactivate',
        'type' => 'checkout.session.completed',
        'data' => (object) [
            'object' => (object) [
                'id' => 'cs_reactivate',
                'customer' => 'cus_reactivate',
                'subscription' => 'sub_reactivate',
                'metadata' => (object) ['plan_id' => (string) $plan->id],
                'customer_details' => (object) [
                    'email' => 'reactivate@example.com',
                    'name' => 'Returning',
                ],
            ],
        ],
    ];

    Mockery::mock('alias:Stripe\Webhook')
        ->shouldReceive('constructEvent')
        ->andReturn($event);

    $payload = json_encode([
        'id' => 'evt_reactivate',
        'type' => 'checkout.session.completed',
        'data' => ['object' => json_decode(json_encode($event->data->object), true)],
    ]);

    $this->call(
        'POST',
        route('webhook.stripe'),
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => 'test_signature',
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    )->assertOk();

    $customer->refresh();

    expect($customer->trello_offboarded_at)->toBeNull()
        ->and($customer->status)->toBe(CustomerStatus::Active)
        ->and($customer->cancelled_at)->toBeNull();

    Queue::assertPushed(OnboardCustomerJob::class);
});
