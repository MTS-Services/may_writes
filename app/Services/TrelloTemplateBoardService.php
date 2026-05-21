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
        $backgroundId = (string) config('trello_template.background_id');

        if ($backgroundId === '') {
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
        $listConfig = config('trello_template.lists', []);
        $lists = $this->trello()->getBoardLists($boardId);
        $listIds = [];

        foreach ($listConfig as $key => $listName) {
            $listIds[$key] = $this->findOrCreateListByName($lists, $boardId, (string) $listName);
            $lists = $this->trello()->getBoardLists($boardId);
        }

        $this->applyTemplateListOrder($listIds);

        $cards = $this->trello()->getBoardCards($boardId);
        $instructionCardIds = [];

        foreach (config('trello_template.instruction_cards', []) as $slug => $definition) {
            if (! is_array($definition)) {
                continue;
            }

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
            );
        }

        $layout = new TemplateBoardLayout($listIds, $instructionCardIds);
        $customer->update($layout->toCustomerAttributes());

        return $layout;
    }

    /**
     * @param  array<string, string>  $listIdsByKey
     */
    public function applyTemplateListOrder(array $listIdsByKey): void
    {
        $sequence = ['delivered', 'revisions', 'draft_review', 'in_progress', 'requests'];

        try {
            foreach ($sequence as $key) {
                $listId = $listIdsByKey[$key] ?? null;

                if (! filled($listId)) {
                    continue;
                }

                $this->trello()->putList((string) $listId, [
                    'pos' => $key === 'requests' ? 'top' : 'bottom',
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Trello template list ordering failed', [
                'list_ids' => $listIdsByKey,
                'error' => $exception->getMessage(),
            ]);
        }
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

        $updates = ['trello_instruction_card_ids' => $instructionIds];

        if ($slug === 'requests_instructions') {
            $updates['trello_welcome_card_id'] = $cardId;
        }

        $customer->update($updates);

        return $cardId;
    }

    public function resolveQueueListId(Customer $customer): ?string
    {
        if (! filled($customer->trello_board_id)) {
            return null;
        }

        if (filled($customer->trello_writing_requests_list_id)) {
            $lists = $this->trello()->getBoardLists((string) $customer->trello_board_id);
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
            foreach (config('trello_template.lists', []) as $templateName) {
                if (Str::lower(trim((string) $templateName)) === $needle) {
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

        if (filled($customer->trello_welcome_card_id) && $cardId === $customer->trello_welcome_card_id) {
            return 'requests_instructions';
        }

        return null;
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
                        $this->trello()->unarchiveList($id);
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

        $created = $this->trello()->postList([
            'name' => $listName,
            'idBoard' => $boardId,
            'pos' => 'bottom',
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

        $card = $this->trello()->postCard([
            'idList' => $listId,
            'name' => $expectedName,
            'desc' => (string) ($definition['desc'] ?? ''),
        ]);

        $cardId = (string) $card['id'];
        $this->applyInstructionCardLabels($boardId, $cardId, $definition);

        return $cardId;
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

        $labels = $this->trello()->getBoardLabels($boardId);
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
        foreach (config('trello_template.instruction_cards', []) as $slug => $definition) {
            if (! is_array($definition)) {
                continue;
            }

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

        foreach (config('trello_template.lists', []) as $key => $name) {
            if (Str::lower(trim((string) $name)) === $needle) {
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
}
