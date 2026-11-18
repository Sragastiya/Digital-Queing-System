<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Enums\QueueType;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use Illuminate\Validation\ValidationException;

class WaitEstimateService
{
    public function snapshot(QueueType $queueType, ?QueueTicket $ticket = null): array
    {
        if ($ticket && $ticket->queue_type !== $queueType) {
            throw ValidationException::withMessages(['queue_type' => 'Jenis antrean tidak cocok dengan tiket.']);
        }
        $settings = QueueSetting::findOrFail(1);
        $waiting = QueueTicket::where('queue_type', $queueType->value)->where('status', TicketStatus::Waiting);
        $count = (clone $waiting)->count();
        $ahead = $ticket && $ticket->status === TicketStatus::Waiting ? (clone $waiting)->where('queue_order', '<', $ticket->queue_order)->count() : null;
        $latest = QueueTicket::where('queue_type', $queueType->value)->whereNotNull('called_at')->orderByDesc('called_at')->orderByDesc('number')->first();

        return ['queue_type' => $queueType->value, 'queue_label' => $queueType->label(), 'waiting_count' => $count, 'ahead' => $ahead,
            'estimate_minutes' => $settings->estimate_interval_minutes === null ? null : ($ticket ? ($ahead === null ? null : $ahead * $settings->estimate_interval_minutes) : $count * $settings->estimate_interval_minutes),
            'near_turn' => $ahead !== null && $ahead <= $settings->near_turn_threshold, 'latest_called' => $latest?->displayNumber(),
            'grace_minutes' => $settings->arrival_grace_minutes];
    }
}
