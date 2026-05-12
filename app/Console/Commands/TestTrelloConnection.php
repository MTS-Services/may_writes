<?php

namespace App\Console\Commands;

use App\Services\TrelloService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('trello:test')]
#[Description('Test Trello API credentials and template board access')]
class TestTrelloConnection extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $service = app(TrelloService::class);

        $profile = Http::baseUrl('https://api.trello.com/1')
            ->acceptJson()
            ->get('/members/me', [
                'key' => $service->apiKey,
                'token' => $service->apiToken,
            ])->throw()->json();

        $this->table(['Name', 'Username', 'Email'], [[
            $profile['fullName'] ?? '-',
            $profile['username'] ?? '-',
            $profile['email'] ?? '-',
        ]]);

        $board = Http::baseUrl('https://api.trello.com/1')
            ->acceptJson()
            ->get("/boards/{$service->templateBoardId}", [
                'key' => $service->apiKey,
                'token' => $service->apiToken,
            ])->throw()->json();

        $lists = $service->getBoardLists($service->templateBoardId);
        $this->info('Template board: '.($board['name'] ?? 'N/A'));
        $this->line('Lists: '.collect($lists)->pluck('name')->implode(', '));

        $this->info('Trello connection verified.');

        return self::SUCCESS;
    }
}
