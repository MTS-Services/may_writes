<?php

namespace App\Services;

use App\Models\TrelloSetting;
use Illuminate\Support\Facades\Cache;

class TrelloSettings
{
    private const string CACHE_KEY = 'trello_settings';

    public function record(): TrelloSetting
    {
        return TrelloSetting::current();
    }

    /**
     * @return array{template_board_id: ?string, background_id: ?string}
     */
    private function cachedValues(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, function (): array {
            $record = TrelloSetting::current();

            return [
                'template_board_id' => $record->template_board_id,
                'background_id' => $record->background_id,
            ];
        });
    }

    public function templateBoardId(): ?string
    {
        $fromDatabase = $this->cachedValues()['template_board_id'];

        if (filled($fromDatabase)) {
            return (string) $fromDatabase;
        }

        $fromEnv = (string) config('services.trello.template_board_id');

        return filled($fromEnv) ? $fromEnv : null;
    }

    public function backgroundId(): ?string
    {
        $fromDatabase = $this->cachedValues()['background_id'];

        if (filled($fromDatabase)) {
            return (string) $fromDatabase;
        }

        $fromEnv = (string) config('trello_template.background_id');

        return filled($fromEnv) ? $fromEnv : null;
    }

    /**
     * @param  array{template_board_id?: ?string, background_id?: ?string}  $attributes
     */
    public function update(array $attributes): TrelloSetting
    {
        $record = TrelloSetting::current();

        $record->update([
            'template_board_id' => filled($attributes['template_board_id'] ?? null)
                ? (string) $attributes['template_board_id']
                : null,
            'background_id' => filled($attributes['background_id'] ?? null)
                ? (string) $attributes['background_id']
                : null,
        ]);

        $this->clearCache();

        return $record->fresh();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{template_board_id: ?string, background_id: ?string}
     */
    public function toAdminArray(): array
    {
        return $this->cachedValues();
    }
}
