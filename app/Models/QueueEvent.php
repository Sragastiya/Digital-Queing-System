<?php

namespace App\Models;

use App\Enums\QueueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['queue_type' => QueueType::class];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
