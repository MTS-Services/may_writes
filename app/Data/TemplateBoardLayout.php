<?php

namespace App\Data;

/**
 * Resolved Trello template list and instruction card ids for a customer board.
 */
readonly class TemplateBoardLayout
{
    /**
     * @param  array<string, string>  $listIds
     * @param  array<string, string>  $instructionCardIds
     */
    public function __construct(
        public array $listIds,
        public array $instructionCardIds,
        public ?string $welcomeCardId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCustomerAttributes(): array
    {
        return [
            'trello_writing_requests_list_id' => $this->listIds['requests'] ?? null,
            'trello_in_progress_list_id' => $this->listIds['in_progress'] ?? null,
            'trello_draft_review_list_id' => $this->listIds['draft_review'] ?? null,
            'trello_revisions_list_id' => $this->listIds['revisions'] ?? null,
            'trello_delivered_list_id' => $this->listIds['delivered'] ?? null,
            'trello_completed_list_id' => $this->listIds['delivered'] ?? null,
            'trello_instruction_card_ids' => $this->instructionCardIds,
            'trello_welcome_card_id' => $this->welcomeCardId,
        ];
    }

    public function queueListId(): ?string
    {
        return $this->listIds['requests'] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function allListIds(): array
    {
        return array_values(array_filter($this->listIds));
    }

    public function isProtectedListId(string $listId): bool
    {
        return in_array($listId, $this->allListIds(), true);
    }

    public function instructionSlugForCardId(string $cardId): ?string
    {
        foreach ($this->instructionCardIds as $slug => $id) {
            if ($id === $cardId) {
                return $slug;
            }
        }

        return null;
    }
}
