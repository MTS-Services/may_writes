<?php

namespace App\Services;

use App\Data\TemplateBoardLayout;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrelloTemplateBoardService
{
    private function trello(): TrelloService
    {
        return app(TrelloService::class);
    }

    public function syncBoardAppearance(string $boardId): void
    {
        $backgroundId = app(TrelloSettings::class)->backgroundId();

        if (! filled($backgroundId)) {
            return;
        }

        try {
            $this->trello()->putBoard($boardId, [
                'prefs/background' => $backgroundId,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Trello board appearance sync failed', [
                'board_id' => $boardId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function ensureTemplateBoardStructure(Customer $customer): TemplateBoardLayout
    {
        if (! filled($customer->trello_board_id)) {
            throw new \RuntimeException('Cannot ensure template structure: customer has no Trello board.');
        }

        $boardId = (string) $customer->trello_board_id;

        if (! $this->trello()->templateBoardExists($boardId)) {
            throw new \RuntimeException(
                "Trello board {$boardId} was not found or is closed. Re-run onboarding for this customer before syncing structure.",
            );
        }

        $listConfig = $this->templateListsConfig();
        $lists = $this->trello()->tryGetBoardLists($boardId);
        $listIds = [];

        foreach ($listConfig as $key => $listName) {
            $listIds[$key] = $this->findOrCreateListByName($lists, $boardId, (string) $listName, (string) $key);
            $lists = $this->trello()->tryGetBoardLists($boardId);
        }

        $listIds = $this->applyTemplateListOrder($boardId, $listIds);

        $cards = $this->trello()->tryGetBoardCards($boardId);
        $instructionCardIds = [];

        foreach ($this->templateInstructionCardsConfig() as $slug => $definition) {
            $listKey = (string) ($definition['list_key'] ?? '');
            $listId = $listIds[$listKey] ?? null;

            if (! filled($listId)) {
                continue;
            }

            $instructionCardIds[$slug] = $this->resolveOrCreateInstructionCard(
                $boardId,
                (string) $listId,
                $slug,
                $definition,
                $cards,
                $listKey,
            );
        }

        $welcomeCardId = $this->resolveOrCreateWelcomeCard(
            $customer,
            $boardId,
            (string) ($listIds['requests'] ?? ''),
            $cards,
        );

        $layout = new TemplateBoardLayout($listIds, $instructionCardIds, $welcomeCardId);
        $customer->update($layout->toCustomerAttributes());

        return $layout;
    }

    /**
     * Left-to-right list order: requests → in_progress → draft_review → revisions → delivered.
     *
     * @return list<string>
     */
    public static function listOrderKeys(): array
    {
        return ['requests', 'in_progress', 'draft_review', 'revisions', 'delivered'];
    }

    /**
     * @param  array<string, string>  $listIdsByKey
     * @return array<string, string>
     */
    public function applyTemplateListOrder(string $boardId, array $listIdsByKey): array
    {
        $sequence = self::listOrderKeys();
        $position = 16384;

        foreach ($sequence as $key) {
            $listId = $listIdsByKey[$key] ?? null;

            if (! filled($listId)) {
                continue;
            }

            $listName = (string) config("trello_template.lists.{$key}", '');

            if (! $this->trello()->tryPutList((string) $listId, ['pos' => $position])) {
                Log::warning('Trello list missing during ordering; recreating', [
                    'board_id' => $boardId,
                    'list_key' => $key,
                    'list_id' => $listId,
                ]);

                $lists = $this->trello()->tryGetBoardLists($boardId);
                $listId = $this->findOrCreateListByName($lists, $boardId, $listName, $key);
                $listIdsByKey[$key] = $listId;
                $this->trello()->putList($listId, ['pos' => $position]);
            }

            $position += 16384;
        }

        return $listIdsByKey;
    }

    public function restoreProtectedList(Customer $customer, string $listId, string $actionType, ?string $listName = null): TemplateBoardLayout
    {
        $listKey = $this->resolveListKeyForCustomer($customer, $listId)
            ?? $this->resolveListKeyByName($listName);

        if ($listKey === null) {
            return $this->ensureTemplateBoardStructure($customer->fresh());
        }

        $boardId = (string) $customer->trello_board_id;
        $listName = (string) config("trello_template.lists.{$listKey}");

        if ($actionType === 'deleteList') {
            $created = $this->trello()->postList([
                'name' => $listName,
                'idBoard' => $boardId,
                'pos' => $listKey === 'requests' ? 'top' : 'bottom',
            ]);
            $customer->update([$this->listColumnForKey($listKey) => (string) $created['id']]);
        } else {
            try {
                $this->trello()->unarchiveList($listId);
            } catch (\Throwable $exception) {
                Log::warning('Trello unarchive protected list failed; recreating', [
                    'customer_id' => $customer->id,
                    'list_id' => $listId,
                    'error' => $exception->getMessage(),
                ]);

                $created = $this->trello()->postList([
                    'name' => $listName,
                    'idBoard' => $boardId,
                    'pos' => $listKey === 'requests' ? 'top' : 'bottom',
                ]);
                $customer->update([$this->listColumnForKey($listKey) => (string) $created['id']]);
            }
        }

        return $this->ensureTemplateBoardStructure($customer->fresh());
    }

    public function recreateInstructionCard(Customer $customer, string $slug): string
    {
        $definition = config("trello_template.instruction_cards.{$slug}");

        if (! is_array($definition)) {
            throw new \RuntimeException("Unknown instruction card slug: {$slug}");
        }

        $boardId = (string) $customer->trello_board_id;
        $listKey = (string) ($definition['list_key'] ?? '');
        $listId = $customer->{$this->listColumnForKey($listKey)} ?? null;

        if (! filled($listId)) {
            $layout = $this->ensureTemplateBoardStructure($customer->fresh());
            $listId = $layout->listIds[$listKey] ?? null;
        }

        if (! filled($listId)) {
            throw new \RuntimeException("Cannot recreate instruction card: list {$listKey} missing.");
        }

        $card = $this->trello()->postCard([
            'idList' => (string) $listId,
            'name' => (string) $definition['name'],
            'desc' => (string) ($definition['desc'] ?? ''),
        ]);

        $cardId = (string) $card['id'];
        $this->applyInstructionCardLabels($boardId, $cardId, $definition);

        $instructionIds = $customer->trello_instruction_card_ids ?? [];
        $instructionIds[$slug] = $cardId;

        $customer->update(['trello_instruction_card_ids' => $instructionIds]);

        return $cardId;
    }

    public function resolveQueueListId(Customer $customer): ?string
    {
        if (! filled($customer->trello_board_id)) {
            return null;
        }

        if (filled($customer->trello_writing_requests_list_id)) {
            $lists = $this->trello()->tryGetBoardLists((string) $customer->trello_board_id);
            $existing = $this->findListInBoardListsById($lists, (string) $customer->trello_writing_requests_list_id);

            if ($existing !== null && ! (bool) ($existing['closed'] ?? false)) {
                return (string) $customer->trello_writing_requests_list_id;
            }
        }

        $layout = $this->ensureTemplateBoardStructure($customer->fresh());

        return $layout->queueListId();
    }

    public function isProtectedList(Customer $customer, string $listId, ?string $listName = null): bool
    {
        $storedIds = array_filter([
            $customer->trello_writing_requests_list_id,
            $customer->trello_in_progress_list_id,
            $customer->trello_draft_review_list_id,
            $customer->trello_revisions_list_id,
            $customer->trello_delivered_list_id,
        ]);

        if (in_array($listId, $storedIds, true)) {
            return true;
        }

        if ($listName !== null) {
            $needle = Str::lower(trim($listName));
            foreach ($this->templateListsConfig() as $templateName) {
                if (Str::lower(trim($templateName)) === $needle) {
                    return true;
                }
            }
        }

        return false;
    }

    public function instructionSlugForCard(Customer $customer, string $cardId, ?string $cardName = null): ?string
    {
        $stored = $customer->trello_instruction_card_ids ?? [];

        if (is_array($stored)) {
            foreach ($stored as $slug => $id) {
                if ((string) $id === $cardId) {
                    return (string) $slug;
                }
            }
        }

        if ($cardName !== null && $this->isInstructionCardName($cardName)) {
            return $this->slugForInstructionCardName($cardName);
        }

        return null;
    }

    public function isWelcomeCard(Customer $customer, string $cardId, ?string $cardName = null): bool
    {
        if (filled($customer->trello_welcome_card_id) && $cardId === $customer->trello_welcome_card_id) {
            return true;
        }

        if ($cardName !== null && $this->isWelcomeCardName($cardName)) {
            return true;
        }

        return false;
    }

    public function isWelcomeCardName(string $name): bool
    {
        return Str::startsWith(trim($name), (string) config('trello_template.welcome_card_name_prefix'));
    }

    public function recreateWelcomeCard(Customer $customer): string
    {
        $boardId = (string) $customer->trello_board_id;
        $listId = $customer->trello_writing_requests_list_id;

        if (! filled($listId)) {
            $layout = $this->ensureTemplateBoardStructure($customer->fresh());

            return (string) $layout->welcomeCardId;
        }

        $cardId = $this->trello()->postWelcomeCard(
            $boardId,
            $customer->fresh(),
            isReuse: false,
            writingListId: (string) $listId,
        );

        $customer->update(['trello_welcome_card_id' => $cardId]);

        return $cardId;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     */
    private function resolveOrCreateWelcomeCard(
        Customer $customer,
        string $boardId,
        string $requestsListId,
        array $cards,
    ): string {
        $welcomeName = (string) config('trello_template.welcome_card.name', '');

        if (filled($customer->trello_welcome_card_id)) {
            foreach ($cards as $card) {
                if (! is_array($card)) {
                    continue;
                }

                if ((string) ($card['id'] ?? '') === $customer->trello_welcome_card_id) {
                    return $customer->trello_welcome_card_id;
                }
            }
        }

        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }

            if (
                (string) ($card['idList'] ?? '') === $requestsListId
                && $this->isWelcomeCardName((string) ($card['name'] ?? ''))
                && isset($card['id'])
            ) {
                return (string) $card['id'];
            }
        }

        if (! filled($requestsListId)) {
            throw new \RuntimeException('Cannot create welcome card: requests list missing.');
        }

        return $this->trello()->postWelcomeCard(
            $boardId,
            $customer,
            isReuse: false,
            writingListId: $requestsListId,
        );
    }

    public function isInstructionCardName(string $name): bool
    {
        return Str::contains($name, (string) config('trello_template.instruction_card_name_suffix'));
    }

    public function isExampleCardName(string $name): bool
    {
        return Str::startsWith(Str::upper(trim($name)), Str::upper((string) config('trello_template.example_card_name_prefix')));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lists
     */
    private function findOrCreateListByName(array $lists, string $boardId, string $listName, string $listKey): string
    {
        $match = $this->findListInBoardListsByNames($lists, $this->listNamesForKey($listKey, $listName));

        if ($match !== null && isset($match['id'])) {
            $id = (string) $match['id'];
            $resolved = $this->ensureListUsable($id, $boardId, $listName, $listKey);

            if ($resolved !== null) {
                $this->updateListNameIfNeeded($resolved, $listName);

                return $resolved;
            }
        }

        return $this->createListOnBoard($boardId, $listName, $listKey);
    }

    /**
     * @return list<string>
     */
    private function listNamesForKey(string $listKey, string $canonicalName): array
    {
        $aliases = config("trello_template.list_aliases.{$listKey}", []);

        if (! is_array($aliases)) {
            $aliases = [];
        }

        return array_values(array_unique(array_merge([$canonicalName], $aliases)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lists
     * @param  list<string>  $names
     * @return array<string, mixed>|null
     */
    private function findListInBoardListsByNames(array $lists, array $names): ?array
    {
        $needles = array_map(
            static fn (string $name): string => Str::lower(trim($name)),
            $names,
        );

        foreach ($lists as $list) {
            if (! is_array($list)) {
                continue;
            }

            $listName = Str::lower(trim((string) ($list['name'] ?? '')));

            if (in_array($listName, $needles, true) && isset($list['id'])) {
                return $list;
            }
        }

        return null;
    }

    private function ensureListUsable(string $listId, string $boardId, string $listName, string $listKey): ?string
    {
        if ($this->trello()->tryPutList($listId, ['closed' => false])) {
            return $listId;
        }

        if ((bool) data_get($this->findListInBoardListsById(
            $this->trello()->tryGetBoardLists($boardId),
            $listId,
        ), 'closed', false)) {
            try {
                $this->trello()->unarchiveList($listId);

                if ($this->trello()->tryPutList($listId, ['closed' => false])) {
                    return $listId;
                }
            } catch (\Throwable $exception) {
                Log::warning('Trello unarchive list failed; will recreate', [
                    'list_id' => $listId,
                    'list_name' => $listName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::warning('Trello list id not found; recreating list', [
            'board_id' => $boardId,
            'list_key' => $listKey,
            'list_id' => $listId,
            'list_name' => $listName,
        ]);

        return null;
    }

    private function updateListNameIfNeeded(string $listId, string $expectedName): void
    {
        if (! $this->trello()->tryPutList($listId, ['name' => $expectedName])) {
            Log::warning('Trello list rename skipped; list may be missing', [
                'list_id' => $listId,
                'expected_name' => $expectedName,
            ]);
        }
    }

    private function createListOnBoard(string $boardId, string $listName, string $listKey): string
    {
        $created = $this->trello()->postList([
            'name' => $listName,
            'idBoard' => $boardId,
            'pos' => $listKey === 'requests' ? 'top' : 'bottom',
        ]);

        return (string) $created['id'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @param  array<string, mixed>  $definition
     */
    private function resolveOrCreateInstructionCard(
        string $boardId,
        string $listId,
        string $slug,
        array $definition,
        array $cards,
        string $listKey,
    ): string {
        $expectedName = (string) ($definition['name'] ?? '');

        foreach ($cards as $card) {
            if (! is_array($card)) {
                continue;
            }

            if (
                (string) ($card['idList'] ?? '') === $listId
                && trim((string) ($card['name'] ?? '')) === $expectedName
                && isset($card['id'])
            ) {
                return (string) $card['id'];
            }
        }

        $listId = $this->resolveListIdForCardCreate($boardId, $listId, $listKey);

        try {
            $card = $this->trello()->postCard([
                'idList' => $listId,
                'name' => $expectedName,
                'desc' => (string) ($definition['desc'] ?? ''),
            ]);
        } catch (\RuntimeException $exception) {
            if (! $this->trello()->isResourceNotFound($exception)) {
                throw $exception;
            }

            $listName = (string) config("trello_template.lists.{$listKey}", '');
            $lists = $this->trello()->tryGetBoardLists($boardId);
            $listId = $this->findOrCreateListByName($lists, $boardId, $listName, $listKey);

            $card = $this->trello()->postCard([
                'idList' => $listId,
                'name' => $expectedName,
                'desc' => (string) ($definition['desc'] ?? ''),
            ]);
        }

        $cardId = (string) $card['id'];
        $this->applyInstructionCardLabels($boardId, $cardId, $definition);

        return $cardId;
    }

    private function resolveListIdForCardCreate(string $boardId, string $listId, string $listKey): string
    {
        if ($this->trello()->tryPutList($listId, ['closed' => false])) {
            return $listId;
        }

        $listName = (string) config("trello_template.lists.{$listKey}", '');
        $lists = $this->trello()->tryGetBoardLists($boardId);

        return $this->findOrCreateListByName($lists, $boardId, $listName, $listKey);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function applyInstructionCardLabels(string $boardId, string $cardId, array $definition): void
    {
        $labelNames = $definition['label_names'] ?? [];

        if (! is_array($labelNames) || $labelNames === []) {
            return;
        }

        $labels = $this->trello()->tryGetBoardLabels($boardId);
        $ids = [];

        foreach ($labelNames as $name) {
            $id = $this->findLabelIdByName($labels, (string) $name);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return;
        }

        try {
            $this->trello()->postCardLabels($cardId, $ids);
        } catch (\Throwable $exception) {
            Log::warning('Trello instruction card label apply failed', [
                'card_id' => $cardId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $labels
     */
    private function findLabelIdByName(array $labels, string $name): ?string
    {
        $needle = Str::lower(trim($name));

        foreach ($labels as $label) {
            if (! is_array($label)) {
                continue;
            }

            if (Str::lower(trim((string) ($label['name'] ?? ''))) === $needle && isset($label['id'])) {
                return (string) $label['id'];
            }
        }

        return null;
    }

    private function slugForInstructionCardName(string $cardName): ?string
    {
        foreach ($this->templateInstructionCardsConfig() as $slug => $definition) {
            if (trim((string) ($definition['name'] ?? '')) === trim($cardName)) {
                return (string) $slug;
            }
        }

        return null;
    }

    private function resolveListKeyForCustomer(Customer $customer, string $listId): ?string
    {
        $map = [
            'requests' => $customer->trello_writing_requests_list_id,
            'in_progress' => $customer->trello_in_progress_list_id,
            'draft_review' => $customer->trello_draft_review_list_id,
            'revisions' => $customer->trello_revisions_list_id,
            'delivered' => $customer->trello_delivered_list_id ?? $customer->trello_completed_list_id,
        ];

        foreach ($map as $key => $storedId) {
            if (filled($storedId) && (string) $storedId === $listId) {
                return $key;
            }
        }

        return null;
    }

    private function resolveListKeyByName(?string $listName): ?string
    {
        if ($listName === null) {
            return null;
        }

        $needle = Str::lower(trim($listName));

        foreach ($this->templateListsConfig() as $key => $name) {
            if (Str::lower(trim($name)) === $needle) {
                return (string) $key;
            }
        }

        return null;
    }

    private function listColumnForKey(string $listKey): string
    {
        return match ($listKey) {
            'requests' => 'trello_writing_requests_list_id',
            'in_progress' => 'trello_in_progress_list_id',
            'draft_review' => 'trello_draft_review_list_id',
            'revisions' => 'trello_revisions_list_id',
            'delivered' => 'trello_delivered_list_id',
            default => throw new \InvalidArgumentException("Unknown list key: {$listKey}"),
        };
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
     * @return array<string, string>
     */
    private function templateListsConfig(): array
    {
        $lists = config('trello_template.lists');

        return is_array($lists) ? $lists : [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templateInstructionCardsConfig(): array
    {
        $cards = config('trello_template.instruction_cards');

        if (! is_array($cards)) {
            return [];
        }

        return array_filter($cards, is_array(...));
    }
}
