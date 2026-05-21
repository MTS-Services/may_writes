<?php

namespace App\Console\Commands;

use App\Enums\TrelloTaskPipelineStatus;
use App\Jobs\ProcessTrelloTaskJob;
use App\Models\TrelloTaskVersion;
use Illuminate\Console\Command;

class ProcessPendingTasks extends Command
{
    protected $signature = 'trello:process-pending-tasks';

    protected $description = 'Dispatch jobs for queued Trello task versions';

    public function handle(): int
    {
        $versions = TrelloTaskVersion::query()
            ->where('pipeline_status', TrelloTaskPipelineStatus::Queued)
            ->get();

        foreach ($versions as $version) {
            ProcessTrelloTaskJob::dispatch($version->id)->onQueue('default');
        }

        $this->info("Dispatched {$versions->count()} version(s).");

        return self::SUCCESS;
    }
}
