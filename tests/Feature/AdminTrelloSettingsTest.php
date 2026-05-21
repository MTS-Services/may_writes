<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\TrelloSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('guests cannot access admin trello settings', function () {
    $this->get(route('admin.trello.edit'))->assertRedirect(route('login'));
});

test('admin can view trello settings page', function () {
    TrelloSetting::query()->create([
        'template_board_id' => 'board_db',
        'background_id' => 'bg_db',
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.trello.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/trello-settings')
            ->where('settings.template_board_id', 'board_db')
            ->where('settings.background_id', 'bg_db'));
});

test('admin can save trello settings', function () {
    TrelloSetting::query()->create([
        'template_board_id' => null,
        'background_id' => null,
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->patch(route('admin.trello.update'), [
            'template_board_id' => '',
            'background_id' => 'new_bg',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $record = TrelloSetting::query()->first();

    expect($record?->template_board_id)->toBeNull()
        ->and($record?->background_id)->toBe('new_bg');
});

test('admin cannot save invalid template board id', function () {
    config([
        'services.trello.api_key' => 'test_key',
        'services.trello.api_token' => 'test_token',
    ]);

    Http::fake([
        'https://api.trello.com/1/boards/missing_board*' => Http::response(['error' => 'not found'], 404),
    ]);

    TrelloSetting::query()->create([
        'template_board_id' => null,
        'background_id' => null,
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->from(route('admin.trello.edit'))
        ->patch(route('admin.trello.update'), [
            'template_board_id' => 'missing_board',
            'background_id' => '',
        ])
        ->assertRedirect(route('admin.trello.edit'))
        ->assertSessionHasErrors('template_board_id');

    expect(TrelloSetting::query()->first()?->template_board_id)->toBeNull();
});
