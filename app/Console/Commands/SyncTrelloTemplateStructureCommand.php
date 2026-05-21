<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\TrelloService;
use Illuminate\Console\Attribute\AsCommand;
use Illuminate\Console\Command;

#[AsCommand(name: 'trello:sync-template-structure')]
class SyncTrelloTemplateStructureCommand extends Command
{
    protected $signature = 'trello:sync-template-structure {customer? : Customer id or email}';

    protected $description = 'Sync Trello board background, list order, and instruction cards from the template definition';

    public function handle(TrelloService $trelloService): int
    {
        $argument = $this->argument('customer');

        $query = Customer::query()
            ->whereNotNull('trello_onboarded_at')
            ->whereNotNull('trello_board_id');

        if (filled($argument)) {
            $query->where(function ($builder) use ($argument): void {
                $builder->where('id', $argument)->orWhere('email', $argument);
            });
        }

        $customers = $query->get();

        if ($customers->isEmpty()) {
            $this->warn('No matching onboarded customers found.');

            return self::SUCCESS;
        }

        foreach ($customers as $customer) {
            $boardId = (string) $customer->trello_board_id;

            try {
                $trelloService->syncBoardAppearance($boardId);
                $trelloService->ensureTemplateBoardStructure($customer->fresh());
                $this->info("Synced template structure for customer {$customer->id} ({$customer->email})");
            } catch (\Throwable $exception) {
                $message = $exception->getMessage();

                if (str_contains($message, 'not found') && str_contains($message, 'Re-run onboarding')) {
                    $this->warn("Skipped customer {$customer->id} ({$customer->email}): board missing — run customers:retry-trello-onboarding {$customer->email}");
                } else {
                    $this->error("Failed for customer {$customer->id}: {$message}");
                }
            }
        }

        return self::SUCCESS;
    }
}
