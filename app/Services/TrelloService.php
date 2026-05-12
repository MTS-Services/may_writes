<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TrelloService
{
    public string $apiKey;

    public string $apiToken;

    public string $templateBoardId;

    public string $workspaceId;

    public string $baseUrl = 'https://api.trello.com/1';

    public PendingRequest $client;

    public function __construct()
    {
        $this->apiKey = (string) config('services.trello.api_key');
        $this->apiToken = (string) config('services.trello.api_token');
        $this->templateBoardId = (string) config('services.trello.template_board_id');
        $this->workspaceId = (string) config('services.trello.workspace_id');
        $this->client = Http::baseUrl($this->baseUrl)->acceptJson()->timeout(20)->connectTimeout(10);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $method, string $endpoint, array $params = []): array
    {
        $response = $this->client->{$method}($endpoint, array_merge([
            'key' => $this->apiKey,
            'token' => $this->apiToken,
        ], $params));

        if (! $response->successful()) {
            throw new \RuntimeException('Trello request failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * @return array{board_id:string,board_url:string,member_id:?string}
     */
    public function createBoardForCustomer(Customer $customer): array
    {
        $board = $this->request('post', '/boards', [
            'name' => "{$customer->name}'s Writing Board",
            'defaultLists' => false,
            'idBoardSource' => $this->templateBoardId,
            'idOrganization' => $this->workspaceId,
            'prefs_permissionLevel' => 'private',
            'prefs_selfJoin' => false,
        ]);

        $boardId = (string) $board['id'];
        $boardUrl = (string) $board['shortUrl'];

        $member = $this->request('put', "/boards/{$boardId}/members", [
            'email' => $customer->email,
            'type' => 'normal',
        ]);

        $lists = $this->getBoardLists($boardId);
        $firstList = $lists[0] ?? null;

        if ($firstList) {
            $this->request('post', '/cards', [
                'idList' => $firstList['id'],
                'name' => '👋 Welcome to MayWrites — Start Here!',
                'desc' => "Hi {$customer->name}! To submit a writing request, create a new card in this list with:\n\n**Title**: What type of content you need\n**Description**: Details, tone, target audience, keywords, word count\n\nWe'll deliver your draft and move the card to 'In Progress', then 'Done' when complete.\n\nQuestions? Email hello@maywrites.co",
            ]);
        }

        $this->request('post', '/webhooks', [
            'callbackURL' => rtrim((string) config('app.url'), '/').'/webhook/trello',
            'idModel' => $boardId,
            'description' => "MayWrites webhook for {$customer->name}",
        ]);

        return [
            'board_id' => $boardId,
            'board_url' => $boardUrl,
            'member_id' => $member['id'] ?? null,
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function getBoardLists(string $boardId): array
    {
        return $this->request('get', "/boards/{$boardId}/lists");
    }

    /**
     * @return array<string,mixed>
     */
    public function getCardDetails(string $cardId): array
    {
        return $this->request('get', "/cards/{$cardId}");
    }
}
