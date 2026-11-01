<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueTicket extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token_hash', 'phone'];

    protected function casts(): array
    {
        return ['status' => TicketStatus::class, 'number' => 'integer', 'party_size' => 'integer',
            'joined_at' => 'immutable_datetime', 'called_at' => 'immutable_datetime', 'arrival_deadline' => 'immutable_datetime',
            'arrived_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }

    public function displayNumber(): string
    {
        return 'A-'.str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    public function events(): HasMany
    {
        return $this->hasMany(QueueEvent::class);
    }
}
