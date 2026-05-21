<?php

namespace App\Models;

use App\Enums\TrelloTaskPipelineStatus;
use App\Enums\TrelloTaskVersionTrigger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrelloTaskVersion extends Model
{
    protected $fillable = [
        'trello_task_id',
        'version_number',
        'trigger',
        'title',
        'description',
        'aggregated_content',
        'content_fingerprint',
        'word_count_original',
        'word_count_processed',
        'was_truncated',
        'truncated_notice',
        'ai_summary',
        'document_path',
        'document_filename',
        'pipeline_status',
        'processed_at',
        'failed_reason',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'trigger' => TrelloTaskVersionTrigger::class,
            'pipeline_status' => TrelloTaskPipelineStatus::class,
            'was_truncated' => 'boolean',
            'processed_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TrelloTask::class, 'trello_task_id');
    }
}
