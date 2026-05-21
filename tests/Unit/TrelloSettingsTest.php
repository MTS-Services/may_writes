<?php

declare(strict_types=1);

use App\Models\TrelloSetting;
use App\Services\TrelloSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
