<?php

namespace App\Services;

use App\DataTransferObjects\CardContent;

class CardContentExtractor
{
    public function __construct(private TrelloService $trello) {}

    public function extract(string $cardId): CardContent
    {
        $card = $this->trello->getCardDetails($cardId);
        $title = trim((string) ($card['name'] ?? ''));
        $description = trim((string) ($card['desc'] ?? ''));

        $sections = ["Title: {$title}"];

        if ($description !== '') {
            $sections[] = "Description:\n{$description}";
        }

        $checklists = $this->trello->getCardChecklists($cardId);

        foreach ($checklists as $checklist) {
            $checklistName = trim((string) ($checklist['name'] ?? 'Checklist'));
            $items = [];

            foreach ($checklist['checkItems'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemName = trim((string) ($item['name'] ?? ''));

                if ($itemName !== '') {
                    $items[] = "- {$itemName}";
                }
            }

            if ($items !== []) {
                $sections[] = "{$checklistName}:\n".implode("\n", $items);
            }
        }

        return new CardContent(
            title: $title,
            description: $description,
            aggregatedForAi: implode("\n\n", $sections),
        );
    }
}
