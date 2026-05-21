<?php

namespace App\Jobs;

use App\Enums\TrelloTaskPipelineStatus;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use App\Services\CardContentExtractor;
use App\Services\ClaudeService;
use App\Services\DocumentService;
use App\Services\RequestWordLimitService;
use App\Services\TrelloService;
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

    public function __construct(public int $trelloTaskVersionId) {}

    public function handle(
        RequestWordLimitService $wordLimits,
        CardContentExtractor $contentExtractor,
        ClaudeService $claude,
        DocumentService $documents,
        TrelloService $trello,
    ): void {
        $version = TrelloTaskVersion::query()
            ->with(['task.customer.plan'])
            ->findOrFail($this->trelloTaskVersionId);

        $task = $version->task;

        try {
            $version->update(['pipeline_status' => TrelloTaskPipelineStatus::Processing]);

            $cardContent = $contentExtractor->extract($task->trello_card_id);
            $description = $cardContent->description;
            $plan = $task->customer->plan;
            $limit = $wordLimits->limitForPlan($plan);
            $truncation = $wordLimits->applyLimit($description, $limit);
            $processedDescription = $truncation->text;

            $truncatedNotice = null;

            if ($truncation->wasTruncated && $limit !== null) {
                $truncatedNotice = $wordLimits->truncationNotice($plan, $limit);
                $trello->putCard($task->trello_card_id, ['desc' => $processedDescription]);
                $trello->notifyMemberOnBoard($task->trello_card_id, $truncatedNotice);
            }

            $version->update([
                'description' => $processedDescription,
                'aggregated_content' => $cardContent->aggregatedForAi,
                'word_count_original' => $truncation->originalCount,
                'word_count_processed' => $truncation->processedCount,
                'was_truncated' => $truncation->wasTruncated,
                'truncated_notice' => $truncatedNotice,
            ]);

            $task->update([
                'title' => $cardContent->title,
                'description' => $processedDescription,
                'content_fingerprint' => TrelloTask::descriptionFingerprint($processedDescription),
            ]);

            $brief = $claude->summarizeVersion($task, $version, $cardContent->aggregatedForAi);
            $version->update(['ai_summary' => json_encode($brief, JSON_THROW_ON_ERROR)]);

            $doc = $documents->generateVersionDocument($task, $version, $brief);
            $version->update([
                'document_path' => $doc['path'],
                'document_filename' => $doc['filename'],
                'pipeline_status' => TrelloTaskPipelineStatus::Summarized,
                'processed_at' => now(),
            ]);

            $task->update(['latest_version_id' => $version->id]);

            Log::info('TrelloTask version processed', [
                'task_id' => $task->id,
                'version_id' => $version->id,
            ]);
        } catch (\Throwable $exception) {
            $version->update([
                'pipeline_status' => TrelloTaskPipelineStatus::Failed,
                'failed_reason' => $exception->getMessage(),
            ]);

            Log::error('TrelloTask version processing failed', [
                'task_id' => $task->id,
                'version_id' => $version->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
