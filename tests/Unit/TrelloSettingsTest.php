<?php

declare(strict_types=1);

use App\Models\TrelloSetting;
use App\Services\TrelloSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('template board id from database overrides env fallback', function () {
    config(['services.trello.template_board_id' => 'env_template']);

    TrelloSetting::query()->create([
        'template_board_id' => 'db_template',
        'background_id' => null,
    ]);

    app(TrelloSettings::class)->clearCache();

    expect(app(TrelloSettings::class)->templateBoardId())->toBe('db_template');
});

test('background id falls back to env when database value is empty', function () {
    config(['trello_template.background_id' => 'env_background']);

    TrelloSetting::query()->create([
        'template_board_id' => null,
        'background_id' => null,
    ]);

    app(TrelloSettings::class)->clearCache();

    expect(app(TrelloSettings::class)->backgroundId())->toBe('env_background');
});

test('record returns a TrelloSetting model instance', function () {
    TrelloSetting::query()->create([
        'template_board_id' => 'board_1',
        'background_id' => null,
    ]);

    app(TrelloSettings::class)->clearCache();

    expect(app(TrelloSettings::class)->record())->toBeInstanceOf(TrelloSetting::class);
});

test('cached values survive cache round trip without incomplete class', function () {
    TrelloSetting::query()->create([
        'template_board_id' => 'board_cached',
        'background_id' => 'bg_cached',
    ]);

    $service = app(TrelloSettings::class);
    $service->clearCache();

    expect($service->templateBoardId())->toBe('board_cached')
        ->and($service->backgroundId())->toBe('bg_cached');

    $cached = Cache::get('trello_settings');

    expect($cached)->toBeArray()
        ->and($cached)->not->toBeInstanceOf(TrelloSetting::class);
});

test('update persists values and clears cache', function () {
    TrelloSetting::query()->create([
        'template_board_id' => null,
        'background_id' => null,
    ]);

    $service = app(TrelloSettings::class);
    $service->clearCache();

    $service->update([
        'template_board_id' => 'board_saved',
        'background_id' => 'bg_saved',
    ]);

    $record = TrelloSetting::query()->first();

    expect($record?->template_board_id)->toBe('board_saved')
        ->and($record?->background_id)->toBe('bg_saved')
        ->and($service->templateBoardId())->toBe('board_saved');
});
