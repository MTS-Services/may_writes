<?php

namespace App\Services;

use App\Data\TemplateBoardLayout;
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

    public string $workspaceId;

    public bool $allowBillableGuest;

    public string $boardNameSuffix;

    public string $baseUrl = 'https://api.trello.com/1';

    public PendingRequest $client;

    public function __construct()
    {
        $this->apiKey = (string) config('services.trello.api_key');
        $this->apiToken = (string) config('services.trello.api_token');
        $this->workspaceId = (string) config('services.trello.workspace_id');
        $this->allowBillableGuest = (bool) config('services.trello.allow_billable_guest', false);
        $this->boardNameSuffix = (string) config('services.trello.board_name_suffix', 'Writing Board');
        $this->client = Http::baseUrl($this->baseUrl)->acceptJson()->timeout(20)->connectTimeout(10);
    }

    /**
     * PUT the board name to match the customer's current plan display (idempotent).
     */
    public function syncBoardDisplayName(Customer $customer): void
    {
        if (! filled($customer->trello_board_id) || $customer->trello_onboarded_at === null) {
            return;
        }

        $customer->loadMissing('plan');
        $boardId = (string) $customer->trello_board_id;
        $name = $this->boardDisplayName($customer);

        try {
            $this->request('put', "/boards/{$boardId}", ['name' => $name]);
        } catch (\Throwable $exception) {
            Log::warning('Trello board display name sync failed', [
                'customer_id' => $customer->id,
                'board_id' => $boardId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function templateBoard(): TrelloTemplateBoardService
    {
        return app(TrelloTemplateBoardService::class);
    }

    public function syncBoardAppearance(string $boardId): void
    {
        $this->templateBoard()->syncBoardAppearance($boardId);
    }

    public function ensureTemplateBoardStructure(Customer $customer): TemplateBoardLayout
    {
        return $this->templateBoard()->ensureTemplateBoardStructure($customer);
    }

    public function restoreProtectedList(Customer $customer, string $listId, string $actionType, ?string $listName = null): TemplateBoardLayout
    {
        return $this->templateBoard()->restoreProtectedList($customer, $listId, $actionType, $listName);
    }

    public function recreateInstructionCard(Customer $customer, string $slug): string
    {
        return $this->templateBoard()->recreateInstructionCard($customer, $slug);
    }

    /**
     * @deprecated Use ensureTemplateBoardStructure() instead.
     *
     * @return array{writing_requests_list_id: string, in_progress_list_id: string, completed_list_id: string}
     */
    public function ensureKanbanLists(string $boardId): array
    {
        $customer = Customer::query()->where('trello_board_id', $boardId)->first();

        if ($customer === null) {
            throw new \RuntimeException('Cannot ensure kanban lists: no customer for board '.$boardId);
        }

        $layout = $this->ensureTemplateBoardStructure($customer);

        return [
            'writing_requests_list_id' => (string) ($layout->listIds['requests'] ?? ''),
            'in_progress_list_id' => (string) ($layout->listIds['in_progress'] ?? ''),
            'completed_list_id' => (string) ($layout->listIds['delivered'] ?? ''),
        ];
    }

    public function unarchiveList(string $listId): void
    {
        $this->putList($listId, ['closed' => false]);
    }

    /**
     * Resolve the requests (queue) list id; ensures template structure when needed.
     */
    public function resolveAndPersistWritingRequestsList(Customer $customer): ?string
    {
        return $this->templateBoard()->resolveQueueListId($customer);
    }

    /**
     * @deprecated Use recreateInstructionCard($customer, 'requests_instructions') instead.
     */
    public function recreateWelcomeSentinel(Customer $customer): string
    {
        return $this->recreateInstructionCard($customer, 'requests_instructions');
    }

    /**
     * @return array{board_id: string, board_url: string, member_id: ?string, webhook_id: ?string, reused_board: bool, writing_requests_list_id: string, in_progress_list_id: string, completed_list_id: string, draft_review_list_id: string, revisions_list_id: string, delivered_list_id: string, instruction_card_ids: array<string, string>, welcome_card_id: ?string}
     */
    public function onboardCustomer(Customer $customer, bool $isRecoveryAttempt = false): array
    {
        $customer->refresh();

        $resolution = $this->allowBillableGuest
            ? $this->resolveBoardForGuestMode($customer)
            : $this->resolveBoardForLookupMode($customer);

        $boardId = $resolution['board_id'];
        $boardUrl = $resolution['board_url'];
        $reusedBoard = $resolution['reused_board'];

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

        $this->syncBoardAppearance($boardId);

        $customer->refresh();
        $layout = $this->ensureTemplateBoardStructure($customer);
        $welcomeCardId = $layout->instructionCardIds['requests_instructions'] ?? null;

        if ($reusedBoard && filled($welcomeCardId)) {
            $username = $member['username'] ?? null;
            $mention = filled($username) ? '@'.$username.' ' : '';
            $this->notifyMemberOnBoard(
                $welcomeCardId,
                "{$mention}Welcome back to MayWrites! Your writing board is ready — add new requests in the REQUESTS (QUEUE) column.",
            );
        }

        return [
            'board_id' => $boardId,
            'board_url' => $boardUrl,
            'member_id' => $member['id'] ?? null,
            'webhook_id' => $webhookId,
            'reused_board' => $reusedBoard,
            'writing_requests_list_id' => (string) ($layout->listIds['requests'] ?? ''),
            'in_progress_list_id' => (string) ($layout->listIds['in_progress'] ?? ''),
            'completed_list_id' => (string) ($layout->listIds['delivered'] ?? ''),
            'draft_review_list_id' => (string) ($layout->listIds['draft_review'] ?? ''),
            'revisions_list_id' => (string) ($layout->listIds['revisions'] ?? ''),
            'delivered_list_id' => (string) ($layout->listIds['delivered'] ?? ''),
            'instruction_card_ids' => $layout->instructionCardIds,
            'welcome_card_id' => $welcomeCardId,
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

    public function postWelcomeCard(
        string $boardId,
        Customer $customer,
        bool $isReuse,
        bool $isSentinelRestore = false,
        ?string $writingListId = null,
    ): string {
        if (filled($writingListId)) {
            $idList = (string) $writingListId;
        } else {
            $firstList = $this->resolveFirstListForWelcomeCard($boardId);
            $idList = (string) $firstList['id'];
        }

        $name = $isReuse
            ? '👋 Welcome back to MayWrites'
            : '👋 Welcome to MayWrites — Start Here!';

        $desc = $isSentinelRestore
            ? $this->welcomeCardSentinelDescription($customer)
            : $this->welcomeCardDescription($customer);

        $card = $this->request('post', '/cards', [
            'idList' => $idList,
            'name' => $name,
            'desc' => $desc,
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
    public function getBoardLists(string $boardId, string $filter = 'all'): array
    {
        return $this->request('get', "/boards/{$boardId}/lists", [
            'filter' => $filter,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBoardCards(string $boardId): array
    {
        $cards = $this->request('get', "/boards/{$boardId}/cards");

        return is_array($cards) ? $cards : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBoardLabels(string $boardId): array
    {
        $labels = $this->request('get', "/boards/{$boardId}/labels");

        return is_array($labels) ? $labels : [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function putBoard(string $boardId, array $params): array
    {
        return $this->request('put', "/boards/{$boardId}", $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function putList(string $listId, array $params): array
    {
        return $this->request('put', "/lists/{$listId}", $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function postList(array $params): array
    {
        return $this->request('post', '/lists', $params);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function postCard(array $params): array
    {
        return $this->request('post', '/cards', $params);
    }

    /**
     * @param  array<int, string>  $labelIds
     */
    public function postCardLabels(string $cardId, array $labelIds): void
    {
        foreach ($labelIds as $labelId) {
            $this->request('post', "/cards/{$cardId}/idLabels", [
                'value' => $labelId,
            ]);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function getCardDetails(string $cardId): array
    {
        return $this->request('get', "/cards/{$cardId}");
    }

    public function templateBoardExists(string $boardId): bool
    {
        return $this->boardExistsAndOpen($boardId);
    }

    private function trelloSettings(): TrelloSettings
    {
        return app(TrelloSettings::class);
    }

    private function resolvedTemplateBoardId(): ?string
    {
        return $this->trelloSettings()->templateBoardId();
    }

    private function shouldCopyFromTemplateBoard(): bool
    {
        return filled($this->resolvedTemplateBoardId());
    }

    /**
     * @return array{board_id: string, board_url: string}
     */
    private function createBoard(Customer $customer): array
    {
        $customer->loadMissing('plan');

        $copyFromTemplate = $this->shouldCopyFromTemplateBoard();

        try {
            return $this->postCreateBoard($customer, $copyFromTemplate);
        } catch (\RuntimeException $exception) {
            if (! $copyFromTemplate || ! $this->isInvalidTemplateBoardSourceError($exception)) {
                throw $exception;
            }

            Log::warning('Trello template board copy failed; falling back to config-only board create', [
                'template_board_id' => $this->resolvedTemplateBoardId(),
                'error' => $exception->getMessage(),
            ]);

            return $this->postCreateBoard($customer, copyFromTemplate: false);
        }
    }

    /**
     * @return array{board_id: string, board_url: string}
     */
    private function postCreateBoard(Customer $customer, bool $copyFromTemplate): array
    {
        $params = [
            'name' => $this->boardDisplayName($customer),
            'defaultLists' => false,
            'idOrganization' => $this->workspaceId,
            'prefs_permissionLevel' => 'private',
            'prefs_selfJoin' => false,
        ];

        if ($copyFromTemplate) {
            $params['idBoardSource'] = (string) $this->resolvedTemplateBoardId();
            $params['keepFromSource'] = 'cards';
        }

        $backgroundId = $this->trelloSettings()->backgroundId();

        if (filled($backgroundId)) {
            $params['prefs_background'] = $backgroundId;
        }

        $board = $this->request('post', '/boards', $params);

        return [
            'board_id' => (string) $board['id'],
            'board_url' => (string) $board['shortUrl'],
        ];
    }

    private function isInvalidTemplateBoardSourceError(\RuntimeException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return Str::contains($message, [
            'idboardsource',
            'not found',
            'invalid',
            '404',
            'does not exist',
            'no board',
        ]);
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
     * @param  array<int, array<string, mixed>>  $lists
     */
    private function resolveWritingRequestsListIdFromLists(array $lists, string $boardId): string
    {
        $target = Str::lower(trim($this->queueListName()));

        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }

            if (Str::lower(trim((string) ($list['name'] ?? ''))) === $target && isset($list['id'])) {
                $id = (string) $list['id'];

                if ((bool) ($list['closed'] ?? false)) {
                    try {
                        $this->unarchiveList($id);
                    } catch (\Throwable $exception) {
                        Log::warning('Trello unarchive writing list by name failed', [
                            'list_id' => $id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                return $id;
            }
        }

        foreach ($lists as $list) {
            if (! is_array($list) || (bool) ($list['closed'] ?? false)) {
                continue;
            }

            if (isset($list['id'])) {
                return (string) $list['id'];
            }
        }

        $list = $this->request('post', '/lists', [
            'name' => $this->queueListName(),
            'idBoard' => $boardId,
            'pos' => 'top',
        ]);

        return (string) $list['id'];
    }

    private function queueListName(): string
    {
        return (string) config('trello_template.lists.requests', 'REQUESTS (QUEUE) COLUMN');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lists
     * @return array<string, mixed>|null
     */
    private function findListInBoardListsById(array $lists, string $listId): ?array
    {
        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }

            if ((string) ($list['id'] ?? '') === $listId) {
                return $list;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lists
     */
    private function findOrCreateListByName(array $lists, string $boardId, string $listName): string
    {
        $needle = Str::lower(trim($listName));

        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }

            if (Str::lower(trim((string) ($list['name'] ?? ''))) === $needle && isset($list['id'])) {
                $id = (string) $list['id'];

                if ((bool) ($list['closed'] ?? false)) {
                    try {
                        $this->unarchiveList($id);
                    } catch (\Throwable $exception) {
                        Log::warning('Trello unarchive list by name failed', [
                            'list_id' => $id,
                            'name' => $listName,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                return $id;
            }
        }

        $created = $this->request('post', '/lists', [
            'name' => $listName,
            'idBoard' => $boardId,
            'pos' => 'bottom',
        ]);

        return (string) $created['id'];
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
            'name' => $this->queueListName(),
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
        $planName = $customer->plan?->name;

        if (filled($planName)) {
            return "{$customer->name}'s {$planName} {$this->boardNameSuffix}";
        }

        return "{$customer->name}'s {$this->boardNameSuffix}";
    }

    private function welcomeCardDescription(Customer $customer): string
    {
        return "Hi {$customer->name}! To submit a writing request, create a new card in this list with:\n\n**Title**: What type of content you need\n**Description**: Details, tone, target audience, keywords, word count\n\nWe'll deliver your draft and move the card to 'In Progress', then 'Done' when complete.\n\nQuestions? Email hello@maywrites.co";
    }

    private function welcomeCardSentinelDescription(Customer $customer): string
    {
        return $this->welcomeCardDescription($customer)."\n\n**Please do not delete this card.** It anchors MayWrites automation for this board.";
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
