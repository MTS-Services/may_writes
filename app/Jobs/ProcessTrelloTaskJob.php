<?php

namespace App\Jobs;

use App\Enums\TrelloTaskStatus;
use App\Models\TrelloTask;
use App\Services\ClaudeService;
use App\Services\DocumentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessTrelloTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(public TrelloTask $task) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $task = TrelloTask::query()->with(['customer.plan'])->findOrFail($this->task->id);

        try {
            $task->update(['status' => TrelloTaskStatus::Processing]);

            $summary = app(ClaudeService::class)->summarizeTask($task);
            $task->update(['ai_summary' => $summary]);

            $doc = app(DocumentService::class)->generateTaskDocument($task, $summary);
            $task->update([
                'document_path' => $doc['path'],
                'document_filename' => $doc['filename'],
                'status' => TrelloTaskStatus::Summarized,
                'processed_at' => now(),
            ]);

            Log::info('TrelloTask processed', ['task_id' => $task->id]);
        } catch (\Throwable $exception) {
            $task->update([
                'status' => TrelloTaskStatus::Failed,
                'failed_reason' => $exception->getMessage(),
            ]);

            Log::error('TrelloTask processing failed', [
                'task_id' => $task->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
