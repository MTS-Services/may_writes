<?php

namespace App\Models;

use App\Enums\TrelloTaskStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TrelloTask extends Model
{
    protected $fillable = [
        'customer_id',
        'trello_card_id',
        'trello_board_id',
        'title',
        'description',
        'raw_payload',
        'status',
        'ai_summary',
        'document_path',
        'document_filename',
        'processed_at',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'status' => TrelloTaskStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function documentUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->document_path) {
                return null;
            }

            return Storage::url($this->document_path);
        });
    }
}
