<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    public function onboardCustomer(Customer $customer, bool $isRecoveryAttempt = false): array
    {
        $customer->refresh();

        $hadBoardBeforeResolution = filled($customer->trello_board_id)
            && $customer->trello_onboarded_at === null;

        $resolution = $this->allowBillableGuest
            ? $this->resolveBoardForGuestMode($customer)
            : $this->resolveBoardForLookupMode($customer);

        $boardId = $resolution['board_id'];
        $boardUrl = $resolution['board_url'];
        $reusedBoard = $resolution['reused_board'];
        $isResume = $resolution['is_resume'];

        try {
            $member = $this->inviteMemberToBoard(
                $boardId,
                $customer->email,
                permitBillableGuest: $this->allowBillableGuest,
                permitBillableReinviteToExistingBoard: $reusedBoard,
            );
        } catch (\RuntimeException $exception) {
            if (
                ! $isRecoveryAttempt
                && ! $this->allowBillableGuest
                && $this->isBillableGuestError($exception)
            ) {
                $scanned = $this->findExistingBoardByWorkspaceMemberScan($customer->email);

                if ($scanned !== null && $scanned['board_id'] !== $boardId) {
                    $customer->update([
                        'trello_board_id' => $scanned['board_id'],
                        'trello_board_url' => $scanned['board_url'],
                    ]);

                    return $this->onboardCustomer($customer, isRecoveryAttempt: true);
                }
            }

            throw $exception;
        }

        $webhookId = $this->ensureBoardWebhook(
            $boardId,
            "MayWrites webhook for {$customer->name}",
            $customer->trello_webhook_id,
        );

        if (! $hadBoardBeforeResolution) {
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
        }

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

        $fromTrello = $this->findExistingBoardFromTrello($email);

        if ($fromTrello !== null) {
            return $fromTrello;
        }

        return $this->findExistingBoardByWorkspaceMemberScan($email);
    }

    /**
     * @return array{id: ?string, username: ?string}
     */
    public function inviteMemberToBoard(
        string $boardId,
        string $email,
        bool $permitBillableGuest = false,
        bool $permitBillableReinviteToExistingBoard = false,
    ): array {
        $existingMember = $this->findBoardMemberByEmail($boardId, $email);

        if ($existingMember !== null) {
            return $existingMember;
        }

        $pendingInvite = $this->findPendingBoardInviteByEmail($boardId, $email);

        if ($pendingInvite !== null) {
            return $pendingInvite;
        }

        $useBillableGuest = $permitBillableGuest;

        try {
            return $this->putBoardMemberInvite($boardId, $email, $useBillableGuest);
        } catch (\RuntimeException $exception) {
            if ($this->isMemberAlreadyInvitedError($exception)) {
                return $this->resolveMemberForAlreadyInvited($email);
            }

            if (! $this->isBillableGuestError($exception)) {
                throw $exception;
            }

            if ($permitBillableReinviteToExistingBoard) {
                return $this->putBoardMemberInvite($boardId, $email, allowBillableGuest: true);
            }

            if ($permitBillableGuest) {
                return $this->putBoardMemberInviteByMemberId($boardId, $email, allowBillableGuest: true);
            }

            throw new \RuntimeException(
                'This email is already a multi-board guest in the Trello Workspace. '
                .'In lookup mode we reuse an existing board when possible; if none is found, '
                .'set TRELLO_ALLOW_BILLABLE_GUEST=true to create a new paid guest board. '
                .$exception->getMessage(),
            );
        }
    }

    /**
     * @return array{board_id: string, board_url: string, reused_board: bool, is_resume: bool}
     */
    private function resolveBoardForLookupMode(Customer $customer): array
    {
        $existing = $this->findExistingBoardForEmail($customer->email, $customer->id);

        if ($existing !== null) {
            $customer->update([
                'trello_board_id' => $existing['board_id'],
                'trello_board_url' => $existing['board_url'],
            ]);

            return [
                'board_id' => $existing['board_id'],
                'board_url' => $existing['board_url'],
                'reused_board' => true,
                'is_resume' => false,
            ];
        }

        if (filled($customer->trello_board_id) && $this->boardExistsAndOpen((string) $customer->trello_board_id)) {
            $existingBoardId = (string) $customer->trello_board_id;

            return [
                'board_id' => $existingBoardId,
                'board_url' => (string) ($customer->trello_board_url ?? $this->getBoardShortUrl($existingBoardId)),
                'reused_board' => true,
                'is_resume' => $this->findBoardMemberByEmail($existingBoardId, $customer->email) !== null,
            ];
        }

        $created = $this->createBoard($customer);

        $customer->update([
            'trello_board_id' => $created['board_id'],
            'trello_board_url' => $created['board_url'],
        ]);

        return [
            'board_id' => $created['board_id'],
            'board_url' => $created['board_url'],
            'reused_board' => false,
            'is_resume' => false,
        ];
    }

    /**
     * @return array{board_id: string, board_url: string, reused_board: bool, is_resume: bool}
     */
    private function resolveBoardForGuestMode(Customer $customer): array
    {
        if (filled($customer->trello_board_id)) {
            $boardId = (string) $customer->trello_board_id;

            return [
                'board_id' => $boardId,
                'board_url' => (string) ($customer->trello_board_url ?? $this->getBoardShortUrl($boardId)),
                'reused_board' => false,
                'is_resume' => true,
            ];
        }

        $created = $this->createBoard($customer);

        $customer->update([
            'trello_board_id' => $created['board_id'],
            'trello_board_url' => $created['board_url'],
        ]);

        return [
            'board_id' => $created['board_id'],
            'board_url' => $created['board_url'],
            'reused_board' => false,
            'is_resume' => false,
        ];
    }

    /**
     * @return array{id: ?string, username: ?string}
     */
    private function putBoardMemberInvite(string $boardId, string $email, bool $allowBillableGuest): array
    {
        $inviteParams = [
            'email' => $email,
            'type' => 'normal',
        ];

        if ($allowBillableGuest) {
            $inviteParams['allowBillableGuest'] = 'true';
        }

        $member = $this->request('put', "/boards/{$boardId}/members", $inviteParams);

        return $this->memberFromInviteResponse($member, $boardId, $email);
    }

    /**
     * @return array{id: ?string, username: ?string}
     */
    private function putBoardMemberInviteByMemberId(string $boardId, string $email, bool $allowBillableGuest): array
    {
        $searched = $this->searchMemberByEmail($email);

        if ($searched === null || ! filled($searched['id'])) {
            throw new \RuntimeException("Unable to invite Trello member for email: {$email}");
        }

        $memberParams = ['type' => 'normal'];

        if ($allowBillableGuest) {
            $memberParams['allowBillableGuest'] = 'true';
        }

        $member = $this->request('put', "/boards/{$boardId}/members/{$searched['id']}", $memberParams);

        $resolved = $this->memberFromInviteResponse($member, $boardId, $email);

        if (filled($resolved['id'])) {
            return $resolved;
        }

        return [
            'id' => (string) $searched['id'],
            'username' => (string) ($resolved['username'] ?? $searched['username'] ?? ''),
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
        $firstList = $this->resolveFirstListForWelcomeCard($boardId);

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
        if ($memberId === $boardId) {
            throw new \InvalidArgumentException('Trello member id cannot be the same as the board id.');
        }

        try {
            $this->request('delete', "/boards/{$boardId}/members/{$memberId}");
        } catch (\RuntimeException $exception) {
            if ($this->isMembershipNotFoundError($exception)) {
                return;
            }

            throw $exception;
        }
    }

    public function removeMemberFromBoardByEmail(string $boardId, string $email, ?string $storedMemberId = null): void
    {
        $memberId = $this->resolveMemberIdForRemoval($boardId, $email, $storedMemberId);

        if ($memberId === null) {
            Log::info('Trello member removal skipped — no membership found on board', [
                'board_id' => $boardId,
                'email' => $email,
            ]);

            return;
        }

        $this->removeMemberFromBoard($boardId, $memberId);
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

        $boards = $this->tryRequest('get', "/members/{$member['id']}/boards", [
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

        $invitedBoards = $this->tryRequest('get', "/members/{$member['id']}/boardsInvited", [
            'fields' => 'name,shortUrl,closed,idOrganization',
        ]);

        if (is_array($invitedBoards)) {
            $invitedWorkspaceBoards = [];

            foreach ($invitedBoards as $board) {
                if (! is_array($board)) {
                    continue;
                }

                if ((bool) ($board['closed'] ?? false)) {
                    continue;
                }

                if ((string) ($board['idOrganization'] ?? '') !== $this->workspaceId) {
                    continue;
                }

                $invitedWorkspaceBoards[] = $board;
            }

            foreach ($invitedWorkspaceBoards as $board) {
                $name = Str::lower((string) ($board['name'] ?? ''));

                if (Str::contains($name, $suffix)) {
                    return $this->boardLookupResult($board);
                }
            }

            $firstInvited = $invitedWorkspaceBoards[0] ?? null;

            if ($firstInvited !== null) {
                return $this->boardLookupResult($firstInvited);
            }
        }

        return null;
    }

    /**
     * @return array{board_id: string, board_url: string}|null
     */
    private function findExistingBoardByWorkspaceMemberScan(string $email): ?array
    {
        if (! filled($this->workspaceId)) {
            return null;
        }

        $member = $this->searchMemberByEmail($email);

        if ($member === null || ! filled($member['id'])) {
            return null;
        }

        $orgBoards = $this->tryRequest('get', "/organizations/{$this->workspaceId}/boards", [
            'filter' => 'open',
            'fields' => 'name,shortUrl,closed',
        ]);

        if (! is_array($orgBoards)) {
            return null;
        }

        $suffix = Str::lower($this->boardNameSuffix);
        $fallback = null;

        foreach ($orgBoards as $board) {
            if (! is_array($board)) {
                continue;
            }

            if ((bool) ($board['closed'] ?? false)) {
                continue;
            }

            $boardId = (string) $board['id'];

            $boardMember = $this->tryFindBoardMemberByEmail($boardId, $email);

            if ($boardMember === null) {
                continue;
            }

            $name = Str::lower((string) ($board['name'] ?? ''));

            if (Str::contains($name, $suffix)) {
                return $this->boardLookupResult($board);
            }

            if ($fallback === null) {
                $fallback = $this->boardLookupResult($board);
            }
        }

        return $fallback;
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
    private function findPendingBoardInviteByEmail(string $boardId, string $email): ?array
    {
        $member = $this->searchMemberByEmail($email);

        if ($member === null || ! filled($member['id'])) {
            return null;
        }

        $invitedBoards = $this->tryRequest('get', "/members/{$member['id']}/boardsInvited", [
            'fields' => 'id',
        ]);

        if (! is_array($invitedBoards)) {
            return null;
        }

        foreach ($invitedBoards as $board) {
            if (! is_array($board)) {
                continue;
            }

            if ((string) ($board['id'] ?? '') === $boardId) {
                return $member;
            }
        }

        return null;
    }

    /**
     * @return array{id: ?string, username: ?string}
     */
    private function resolveMemberForAlreadyInvited(string $email): array
    {
        $member = $this->searchMemberByEmail($email);

        if ($member !== null) {
            return $member;
        }

        return [
            'id' => null,
            'username' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFirstListForWelcomeCard(string $boardId): array
    {
        $lists = $this->getBoardLists($boardId);

        if ($lists !== []) {
            return $lists[0];
        }

        usleep(500_000);

        $lists = $this->getBoardLists($boardId);

        if ($lists !== []) {
            return $lists[0];
        }

        $list = $this->request('post', '/lists', [
            'name' => 'Writing Requests',
            'idBoard' => $boardId,
        ]);

        return $list;
    }

    /**
     * @return array{id: ?string, username: ?string}|null
     */
    private function searchMemberByEmail(string $email): ?array
    {
        $searchParams = [
            'query' => $email,
            'limit' => 10,
        ];

        if (filled($this->workspaceId)) {
            $searchParams['idOrganization'] = $this->workspaceId;
        }

        $results = $this->tryRequest('get', '/search/members/', $searchParams);

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

    private function isMemberAlreadyInvitedError(\RuntimeException $exception): bool
    {
        return Str::contains(Str::lower($exception->getMessage()), 'already invited');
    }

    private function isMembershipNotFoundError(\RuntimeException $exception): bool
    {
        return Str::contains(Str::lower($exception->getMessage()), 'membership not found');
    }

    public function resolveMemberIdForRemoval(string $boardId, string $email, ?string $storedMemberId = null): ?string
    {
        if (filled($storedMemberId) && $storedMemberId !== $boardId) {
            return $storedMemberId;
        }

        $fromMembers = $this->tryFindBoardMemberByEmail($boardId, $email);

        if ($fromMembers !== null && filled($fromMembers['id']) && $fromMembers['id'] !== $boardId) {
            return $fromMembers['id'];
        }

        $fromMemberships = $this->findBoardMembershipMemberIdByEmail($boardId, $email);

        if ($fromMemberships !== null) {
            return $fromMemberships;
        }

        $pendingInvite = $this->findPendingBoardInviteByEmail($boardId, $email);

        if ($pendingInvite !== null && filled($pendingInvite['id']) && $pendingInvite['id'] !== $boardId) {
            return $pendingInvite['id'];
        }

        $fromSearch = $this->searchMemberByEmail($email);

        if ($fromSearch !== null && filled($fromSearch['id']) && $fromSearch['id'] !== $boardId) {
            return $fromSearch['id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{id: ?string, username: ?string}
     */
    private function memberFromInviteResponse(array $response, string $boardId, string $email): array
    {
        $memberId = isset($response['idMember'])
            ? (string) $response['idMember']
            : (isset($response['id']) ? (string) $response['id'] : null);

        if ($memberId !== null && $memberId !== $boardId) {
            return [
                'id' => $memberId,
                'username' => isset($response['username']) ? (string) $response['username'] : null,
            ];
        }

        $fromBoard = $this->tryFindBoardMemberByEmail($boardId, $email);

        if ($fromBoard !== null && filled($fromBoard['id']) && $fromBoard['id'] !== $boardId) {
            return $fromBoard;
        }

        $fromSearch = $this->searchMemberByEmail($email);

        if ($fromSearch !== null && filled($fromSearch['id']) && $fromSearch['id'] !== $boardId) {
            return $fromSearch;
        }

        return [
            'id' => null,
            'username' => isset($response['username']) ? (string) $response['username'] : ($fromSearch['username'] ?? null),
        ];
    }

    private function findBoardMembershipMemberIdByEmail(string $boardId, string $email): ?string
    {
        $memberships = $this->tryRequest('get', "/boards/{$boardId}/memberships", [
            'member' => 'true',
        ]);

        if (! is_array($memberships)) {
            return null;
        }

        foreach ($memberships as $membership) {
            if (! is_array($membership)) {
                continue;
            }

            $member = $membership['member'] ?? null;

            if (! is_array($member)) {
                continue;
            }

            if (Str::lower((string) ($member['email'] ?? '')) !== Str::lower($email)) {
                continue;
            }

            $idMember = (string) ($membership['idMember'] ?? $member['id'] ?? '');

            if (filled($idMember) && $idMember !== $boardId) {
                return $idMember;
            }
        }

        return null;
    }

    private function tryFindBoardMemberByEmail(string $boardId, string $email): ?array
    {
        try {
            return $this->findBoardMemberByEmail($boardId, $email);
        } catch (\RuntimeException $exception) {
            if ($this->isIgnorableLookupError($exception)) {
                return null;
            }

            throw $exception;
        }
    }

    private function isIgnorableLookupError(\RuntimeException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return Str::contains($message, [
            'model not found',
            'membership not found',
            'not found',
            'invalid value for id',
            'unauthorized organization',
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|array<int, array<string, mixed>>|null
     */
    private function tryRequest(string $method, string $endpoint, array $params = []): ?array
    {
        try {
            return $this->request($method, $endpoint, $params);
        } catch (\RuntimeException $exception) {
            if ($this->isIgnorableLookupError($exception)) {
                Log::warning('Trello lookup request skipped', [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }

            throw $exception;
        }
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
