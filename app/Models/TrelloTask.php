<?php

namespace App\Models;

use App\Enums\WritingWorkflowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrelloTask extends Model
{
    protected $fillable = [
        'customer_id',
        'trello_card_id',
        'trello_board_id',
        'trello_list_id',
        'title',
        'description',
        'workflow_status',
        'content_fingerprint',
        'latest_version_id',
    ];

    protected function casts(): array
    {
        return [
            'workflow_status' => WritingWorkflowStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TrelloTaskVersion::class)->orderBy('version_number');
    }

    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(TrelloTaskVersion::class, 'latest_version_id');
    }

    public static function descriptionFingerprint(?string $description): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $description)) ?? '';

        return hash('sha256', $normalized);
    }
}
