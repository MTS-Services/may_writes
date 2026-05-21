<?php

namespace App\Console\Commands;

use App\Services\CardContentExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportTrelloCardFixtureCommand extends Command
{
    protected $signature = 'trello:export-card-fixture {cardId : Trello card ID}';

    protected $description = 'Export aggregated card content for Claude CLI prompt testing';

    public function handle(CardContentExtractor $extractor): int
    {
        $cardId = (string) $this->argument('cardId');

        try {
            $content = $extractor->extract($cardId);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $payload = [
            'card_id' => $cardId,
            'title' => $content->title,
            'description' => $content->description,
            'aggregated_content' => $content->aggregatedForAi,
            'description_word_count' => str_word_count($content->description),
        ];

        $directory = storage_path('fixtures');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'card-'.$cardId.'.json';
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Fixture written to {$path}");

        return self::SUCCESS;
    }
}
