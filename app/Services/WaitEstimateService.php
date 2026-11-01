<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\QueueSetting;
use App\Models\QueueTicket;

class WaitEstimateService
{
    public function snapshot(?QueueTicket $ticket = null): array
    {
        $settings = QueueSetting::findOrFail(1);
        $waiting = QueueTicket::where('status', TicketStatus::Waiting);
        $count = (clone $waiting)->count();
        $ahead = $ticket && $ticket->status === TicketStatus::Waiting ? (clone $waiting)->where('number', '<', $ticket->number)->count() : null;
        $latest = QueueTicket::whereNotNull('called_at')->orderByDesc('called_at')->orderByDesc('number')->first();

        return ['waiting_count' => $count, 'ahead' => $ahead,
            'estimate_minutes' => $settings->estimate_interval_minutes === null ? null : ($ticket ? ($ahead === null ? null : $ahead * $settings->estimate_interval_minutes) : $count * $settings->estimate_interval_minutes),
            'near_turn' => $ahead !== null && $ahead <= $settings->near_turn_threshold, 'latest_called' => $latest?->displayNumber(),
            'grace_minutes' => $settings->arrival_grace_minutes];
    }
}
