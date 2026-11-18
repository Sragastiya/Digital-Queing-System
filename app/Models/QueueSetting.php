<?php

namespace App\Models;

use App\Enums\QueueType;
use Illuminate\Database\Eloquent\Model;

class QueueSetting extends Model
{
    protected $guarded = ['id'];

    public $incrementing = false;

    protected function casts(): array
    {
        return ['next_number' => 'integer', 'arrival_grace_minutes' => 'integer', 'estimate_interval_minutes' => 'integer',
            'near_turn_threshold' => 'integer', 'auto_call_dine_in' => 'boolean', 'auto_call_takeaway' => 'boolean'];
    }

    public static function autoCallAttribute(QueueType $queueType): string
    {
        return 'auto_call_'.$queueType->value;
    }

    public function autoCallAvailable(QueueType $queueType): bool
    {
        return array_key_exists(self::autoCallAttribute($queueType), $this->getAttributes());
    }

    public function autoCallEnabled(QueueType $queueType): bool
    {
        return $this->autoCallAvailable($queueType) && (bool) $this->getAttribute(self::autoCallAttribute($queueType));
    }
}
