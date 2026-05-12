<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Customer extends Model
{
    use Billable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'stripe_id',
        'plan_id',
        'trello_board_id',
        'trello_board_url',
        'trello_member_id',
        'trello_invited_at',
        'welcome_email_sent_at',
        'pm_type',
        'pm_last_four',
        'status',
        'subscribed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'trello_invited_at' => 'datetime',
            'welcome_email_sent_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function trelloTasks(): HasMany
    {
        return $this->hasMany(TrelloTask::class);
    }

    protected function formattedSubscribedAt(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->subscribed_at?->format('F j, Y g:i A'));
    }

    public function isActive(): bool
    {
        return $this->status === CustomerStatus::Active;
    }
}
