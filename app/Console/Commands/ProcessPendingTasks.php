<?php

namespace App\Console\Commands;

use App\Enums\TrelloTaskStatus;
use App\Jobs\ProcessTrelloTaskJob;
use App\Models\TrelloTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:process-pending')]
#[Description('Dispatch all pending Trello tasks for processing')]
class ProcessPendingTasks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tasks = TrelloTask::query()->where('status', TrelloTaskStatus::Received)->get();

        foreach ($tasks as $task) {
            ProcessTrelloTaskJob::dispatch($task)->onQueue('default');
        }

        $this->info("Queued {$tasks->count()} pending tasks.");

        return self::SUCCESS;
    }
}
