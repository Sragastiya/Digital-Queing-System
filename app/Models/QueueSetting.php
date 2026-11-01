<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueSetting extends Model
{
    protected $guarded = ['id'];

    public $incrementing = false;

    protected function casts(): array
    {
        return ['next_number' => 'integer', 'arrival_grace_minutes' => 'integer', 'estimate_interval_minutes' => 'integer', 'near_turn_threshold' => 'integer'];
    }
}
