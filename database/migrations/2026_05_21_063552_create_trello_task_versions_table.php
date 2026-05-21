<?php

use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\TrelloTaskVersionTrigger;
use App\Enums\WritingWorkflowStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trello_task_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trello_task_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->string('trigger')->default(TrelloTaskVersionTrigger::Created->value);
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('aggregated_content')->nullable();
            $table->string('content_fingerprint', 64)->nullable();
            $table->unsignedInteger('word_count_original')->nullable();
            $table->unsignedInteger('word_count_processed')->nullable();
            $table->boolean('was_truncated')->default(false);
            $table->text('truncated_notice')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_filename')->nullable();
            $table->string('pipeline_status')->default(TrelloTaskPipelineStatus::Queued->value);
            $table->timestamp('processed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['trello_task_id', 'version_number']);
        });

        if (Schema::hasColumn('trello_tasks', 'status')) {
            $this->migrateLegacyTasksToVersions();
        }

        Schema::table('trello_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('trello_tasks', 'status')) {
                $table->dropColumn([
                    'status',
                    'ai_summary',
                    'document_path',
                    'document_filename',
                    'processed_at',
                    'failed_reason',
                    'raw_payload',
                ]);
            }

            $table->foreign('latest_version_id')
                ->references('id')
                ->on('trello_task_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trello_tasks', function (Blueprint $table) {
            $table->dropForeign(['latest_version_id']);
        });

        Schema::dropIfExists('trello_task_versions');

        Schema::table('trello_tasks', function (Blueprint $table) {
            $table->string('status')->default('received');
            $table->json('raw_payload');
            $table->text('ai_summary')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_filename')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('failed_reason')->nullable();
        });
    }

    private function migrateLegacyTasksToVersions(): void
    {
        $statusMap = [
            'received' => TrelloTaskPipelineStatus::Queued->value,
            'processing' => TrelloTaskPipelineStatus::Processing->value,
            'summarized' => TrelloTaskPipelineStatus::Summarized->value,
            'failed' => TrelloTaskPipelineStatus::Failed->value,
        ];

        foreach (DB::table('trello_tasks')->get() as $task) {
            $pipelineStatus = $statusMap[$task->status] ?? TrelloTaskPipelineStatus::Queued->value;

            $versionId = DB::table('trello_task_versions')->insertGetId([
                'trello_task_id' => $task->id,
                'version_number' => 1,
                'trigger' => TrelloTaskVersionTrigger::Created->value,
                'title' => $task->title,
                'description' => $task->description,
                'aggregated_content' => trim(($task->title ?? '')."\n\n".($task->description ?? '')),
                'content_fingerprint' => hash('sha256', (string) ($task->description ?? '')),
                'ai_summary' => $task->ai_summary,
                'document_path' => $task->document_path,
                'document_filename' => $task->document_filename,
                'pipeline_status' => $pipelineStatus,
                'processed_at' => $task->processed_at,
                'failed_reason' => $task->failed_reason,
                'raw_payload' => $task->raw_payload,
                'created_at' => $task->created_at,
                'updated_at' => $task->updated_at,
            ]);

            DB::table('trello_tasks')->where('id', $task->id)->update([
                'workflow_status' => WritingWorkflowStatus::Initialized->value,
                'latest_version_id' => $versionId,
                'content_fingerprint' => hash('sha256', (string) ($task->description ?? '')),
            ]);
        }
    }
};
