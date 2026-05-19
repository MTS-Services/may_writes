<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrelloService
{
    public string $apiKey;

    public string $apiToken;

    public string $templateBoardId;

    public string $workspaceId;

    public bool $allowBillableGuest;

    public string $boardNameSuffix;

    public string $baseUrl = 'https://api.trello.com/1';

    public PendingRequest $client;

    public function __construct()
    {
        $this->apiKey = (string) config('services.trello.api_key');
        $this->apiToken = (string) config('services.trello.api_token');
        $this->templateBoardId = (string) config('services.trello.template_board_id');
        $this->workspaceId = (string) config('services.trello.workspace_id');
        $this->allowBillableGuest = (bool) config('services.trello.allow_billable_guest', false);
        $this->boardNameSuffix = (string) config('services.trello.board_name_suffix', 'Writing Board');
        $this->client = Http::baseUrl($this->baseUrl)->acceptJson()->timeout(20)->connectTimeout(10);
    }

    /**
     * @return array{board_id: string, board_url: string, member_id: ?string, webhook_id: ?string, reused_board: bool}
     */
    public function onboardCustomer(Customer $customer): array
    {
        $isResume = filled($customer->trello_board_id);
        $reusedBoard = false;
        $boardId = null;
        $boardUrl = null;

        if ($isResume) {
            $boardId = (string) $customer->trello_board_id;
            $boardUrl = (string) ($customer->trello_board_url ?? $this->getBoardShortUrl($boardId));
        } elseif ($this->allowBillableGuest) {
            $created = $this->createBoard($customer);
            $boardId = $created['board_id'];
            $boardUrl = $created['board_url'];

            $customer->update([
                'trello_board_id' => $boardId,
                'trello_board_url' => $boardUrl,
            ]);
        } else {
            $existing = $this->findExistingBoardForEmail($customer->email, $customer->id);

            if ($existing !== null) {
                $boardId = $existing['board_id'];
                $boardUrl = $existing['board_url'];
                $reusedBoard = true;
            } else {
                $created = $this->createBoard($customer);
                $boardId = $created['board_id'];
                $boardUrl = $created['board_url'];

                $customer->update([
                    'trello_board_id' => $boardId,
                    'trello_board_url' => $boardUrl,
                ]);
            }
        }

        $member = $this->inviteMemberToBoard($boardId, $customer->email);

        if ($reusedBoard) {
            $cardId = $this->postWelcomeCard($boardId, $customer, true);
            $username = $member['username'] ?? null;
            $mention = filled($username) ? '@'.$username.' ' : '';
            $this->notifyMemberOnBoard(
                $cardId,
                "{$mention}Welcome back to MayWrites! Your writing board is ready — add new requests as cards here.",
            );
        } elseif (! $isResume) {
            $this->postWelcomeCard($boardId, $customer, false);
        }

        $webhookId = $this->ensureBoardWebhook(
            $boardId,
            "MayWrites webhook for {$customer->name}",
            $customer->trello_webhook_id,
        );

        return [
            'board_id' => $boardId,
            'board_url' => $boardUrl,
            'member_id' => $member['id'] ?? null,
            'webhook_id' => $webhookId,
            'reused_board' => $reusedBoard,
        ];
    }

    /**
     * @return array{board_id: string, board_url: string, member_id: ?string, webhook_id: ?string}
     *
     * @deprecated Use onboardCustomer() instead.
     */
    public function createBoardForCustomer(Customer $customer): array
    {
        $result = $this->onboardCustomer($customer);

        return [
            'board_id' => $result['board_id'],
            'board_url' => $result['board_url'],
            'member_id' => $result['member_id'],
            'webhook_id' => $result['webhook_id'],
        ];
    }

    /**
     * @return array{board_id: string, board_url: string}|null
     */
    public function findExistingBoardForEmail(string $email, ?int $excludeCustomerId = null): ?array
    {
        $fromDatabase = $this->findExistingBoardFromDatabase($email, $excludeCustomerId);

        if ($fromDatabase !== null) {
            return $fromDatabase;
        }

        return $this->findExistingBoardFromTrello($email);
    }

    /**
     * @return array{id: ?string, username: ?string}
     */
    public function inviteMemberToBoard(string $boardId, string $email): array
    {
        $existingMember = $this->findBoardMemberByEmail($boardId, $email);

        if ($existingMember !== null) {
            return $existingMember;
        }

        $inviteParams = [
            'email' => $email,
            'type' => 'normal',
        ];

        if ($this->allowBillableGuest) {
            $inviteParams['allowBillableGuest'] = 'true';
        }

        try {
            $member = $this->request('put', "/boards/{$boardId}/members", $inviteParams);

            return [
                'id' => isset($member['id']) ? (string) $member['id'] : null,
                'username' => isset($member['username']) ? (string) $member['username'] : null,
            ];
        } catch (\RuntimeException $exception) {
            if (! $this->isBillableGuestError($exception)) {
                throw $exception;
            }

            if (! $this->allowBillableGuest) {
                throw new \RuntimeException(
                    'This email is already a multi-board guest in the Trello Workspace. '
                    .'Set TRELLO_ALLOW_BILLABLE_GUEST=true to create a new paid guest board, '
                    .'or remove the member from other Workspace boards first. '
                    .$exception->getMessage(),
                );
            }
        }

        $searched = $this->searchMemberByEmail($email);

        if ($searched === null || ! filled($searched['id'])) {
            throw new \RuntimeException("Unable to invite Trello member for email: {$email}");
        }

        $memberParams = ['type' => 'normal'];

        if ($this->allowBillableGuest) {
            $memberParams['allowBillableGuest'] = 'true';
        }

        $member = $this->request('put', "/boards/{$boardId}/members/{$searched['id']}", $memberParams);

        return [
            'id' => (string) ($member['id'] ?? $searched['id']),
            'username' => (string) ($member['username'] ?? $searched['username'] ?? ''),
        ];
    }

    public function ensureBoardWebhook(string $boardId, string $description, ?string $existingWebhookId = null): ?string
    {
        if (filled($existingWebhookId)) {
            return $existingWebhookId;
        }

        $callbackUrl = rtrim((string) config('app.url'), '/').'/webhook/trello';

        foreach ($this->getTokenWebhooks() as $webhook) {
            if (
                (string) data_get($webhook, 'idModel') === $boardId
                && (string) data_get($webhook, 'callbackURL') === $callbackUrl
            ) {
                return isset($webhook['id']) ? (string) $webhook['id'] : null;
            }
        }

        $webhook = $this->request('post', '/webhooks', [
            'callbackURL' => $callbackUrl,
            'idModel' => $boardId,
            'description' => $description,
        ]);

        return isset($webhook['id']) ? (string) $webhook['id'] : null;
    }

    public function postWelcomeCard(string $boardId, Customer $customer, bool $isReuse): string
    {
        $lists = $this->getBoardLists($boardId);
        $firstList = $lists[0] ?? null;

        if ($firstList === null) {
            throw new \RuntimeException("Board {$boardId} has no lists for welcome card.");
        }

        $name = $isReuse
            ? '👋 Welcome back to MayWrites'
            : '👋 Welcome to MayWrites — Start Here!';

        $card = $this->request('post', '/cards', [
            'idList' => $firstList['id'],
            'name' => $name,
            'desc' => $this->welcomeCardDescription($customer),
        ]);

        return (string) $card['id'];
    }

    public function notifyMemberOnBoard(string $cardId, string $text): void
    {
        $this->request('post', "/cards/{$cardId}/actions/comments", [
            'text' => $text,
        ]);
    }

    public function removeMemberFromBoard(string $boardId, string $memberId): void
    {
        $this->request('delete', "/boards/{$boardId}/members/{$memberId}");
    }

    public function deleteBoardWebhook(string $webhookId): void
    {
        $this->request('delete', "/webhooks/{$webhookId}");
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

    /**
     * @return array{board_id: string, board_url: string}
     */
    private function createBoard(Customer $customer): array
    {
        $board = $this->request('post', '/boards', [
            'name' => $this->boardDisplayName($customer),
            'defaultLists' => false,
            'idBoardSource' => $this->templateBoardId,
            'idOrganization' => $this->workspaceId,
            'prefs_permissionLevel' => 'private',
            'prefs_selfJoin' => false,
        ]);

        return [
            'board_id' => (string) $board['id'],
            'board_url' => (string) $board['shortUrl'],
        ];
    }

    /**
     * @return array{board_id: string, board_url: string}|null
     */
    private function findExistingBoardFromDatabase(string $email, ?int $excludeCustomerId): ?array
    {
        $query = Customer::query()
            ->where('email', $email)
            ->whereNotNull('trello_board_id')
            ->whereNull('trello_offboarded_at');

        if ($excludeCustomerId !== null) {
            $query->where('id', '!=', $excludeCustomerId);
        }

        $other = $query->orderByDesc('trello_onboarded_at')->first();

        if ($other === null || ! filled($other->trello_board_id)) {
            return null;
        }

        if ($this->boardExistsAndOpen((string) $other->trello_board_id)) {
            return [
                'board_id' => (string) $other->trello_board_id,
                'board_url' => (string) ($other->trello_board_url ?? $this->getBoardShortUrl((string) $other->trello_board_id)),
            ];
        }

        return null;
    }

    /**
     * @return array{board_id: string, board_url: string}|null
     */
    private function findExistingBoardFromTrello(string $email): ?array
    {
        $member = $this->searchMemberByEmail($email);

        if ($member === null || ! filled($member['id'])) {
            return null;
        }

        $boards = $this->request('get', "/members/{$member['id']}/boards", [
            'filter' => 'open',
            'fields' => 'name,shortUrl,closed,idOrganization',
        ]);

        if (! is_array($boards)) {
            return null;
        }

        $suffix = Str::lower($this->boardNameSuffix);
        $workspaceBoards = [];

        foreach ($boards as $board) {
            if (! is_array($board)) {
                continue;
            }

            if ((bool) ($board['closed'] ?? false)) {
                continue;
            }

            if ((string) ($board['idOrganization'] ?? '') !== $this->workspaceId) {
                continue;
            }

            $workspaceBoards[] = $board;
        }

        foreach ($workspaceBoards as $board) {
            $name = Str::lower((string) ($board['name'] ?? ''));

            if (Str::contains($name, $suffix)) {
                return $this->boardLookupResult($board);
            }
        }

        $first = $workspaceBoards[0] ?? null;

        if ($first !== null) {
            return $this->boardLookupResult($first);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $board
     * @return array{board_id: string, board_url: string}
     */
    private function boardLookupResult(array $board): array
    {
        return [
            'board_id' => (string) $board['id'],
            'board_url' => (string) ($board['shortUrl'] ?? $this->getBoardShortUrl((string) $board['id'])),
        ];
    }

    /**
     * @return array{id: ?string, username: ?string}|null
     */
    private function findBoardMemberByEmail(string $boardId, string $email): ?array
    {
        $members = $this->request('get', "/boards/{$boardId}/members", [
            'fields' => 'id,username,email',
        ]);

        if (! is_array($members)) {
            return null;
        }

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            if (Str::lower((string) ($member['email'] ?? '')) === Str::lower($email)) {
                return [
                    'id' => isset($member['id']) ? (string) $member['id'] : null,
                    'username' => isset($member['username']) ? (string) $member['username'] : null,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{id: ?string, username: ?string}|null
     */
    private function searchMemberByEmail(string $email): ?array
    {
        $results = $this->request('get', '/search/members/', [
            'query' => $email,
            'idOrganization' => $this->workspaceId,
            'limit' => 10,
        ]);

        if (! is_array($results)) {
            return null;
        }

        foreach ($results as $member) {
            if (! is_array($member)) {
                continue;
            }

            if (Str::lower((string) ($member['email'] ?? '')) === Str::lower($email)) {
                return [
                    'id' => isset($member['id']) ? (string) $member['id'] : null,
                    'username' => isset($member['username']) ? (string) $member['username'] : null,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTokenWebhooks(): array
    {
        $webhooks = $this->request('get', "/tokens/{$this->apiToken}/webhooks");

        return is_array($webhooks) ? $webhooks : [];
    }

    private function boardExistsAndOpen(string $boardId): bool
    {
        try {
            $board = $this->request('get', "/boards/{$boardId}", [
                'fields' => 'closed',
            ]);

            return ! (bool) ($board['closed'] ?? false);
        } catch (\RuntimeException) {
            return false;
        }
    }

    private function getBoardShortUrl(string $boardId): string
    {
        $board = $this->request('get', "/boards/{$boardId}", [
            'fields' => 'shortUrl',
        ]);

        return (string) ($board['shortUrl'] ?? "https://trello.com/b/{$boardId}");
    }

    private function boardDisplayName(Customer $customer): string
    {
        return "{$customer->name}'s {$this->boardNameSuffix}";
    }

    private function welcomeCardDescription(Customer $customer): string
    {
        return "Hi {$customer->name}! To submit a writing request, create a new card in this list with:\n\n**Title**: What type of content you need\n**Description**: Details, tone, target audience, keywords, word count\n\nWe'll deliver your draft and move the card to 'In Progress', then 'Done' when complete.\n\nQuestions? Email hello@maywrites.co";
    }

    private function isBillableGuestError(\RuntimeException $exception): bool
    {
        return Str::contains(Str::lower($exception->getMessage()), 'allowbillableguest');
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    private function request(string $method, string $endpoint, array $params = []): array
    {
        $response = $this->sendRequest($method, $endpoint, $params);

        if (! $response->successful()) {
            throw new \RuntimeException('Trello request failed: '.$response->body());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function sendRequest(string $method, string $endpoint, array $params = []): Response
    {
        return $this->client->{$method}($endpoint, array_merge([
            'key' => $this->apiKey,
            'token' => $this->apiToken,
        ], $params));
    }
}
