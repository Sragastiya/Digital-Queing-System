<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\QueueEvent;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueService
{
    // The singleton lock serializes numbering and state changes for this one restaurant.
    private function settings(): QueueSetting
    {
        return QueueSetting::query()->lockForUpdate()->findOrFail(1);
    }

    private function replay(string $operation, string $action, string $actor, ?int $user, ?int $ticket = null): ?QueueEvent
    {
        $event = QueueEvent::where('operation_id', $operation)->first();
        if ($event && ($event->action !== $action || $event->actor !== $actor || $event->user_id !== $user || ($ticket !== null && $event->queue_ticket_id !== $ticket))) {
            throw ValidationException::withMessages(['action' => 'Permintaan tidak cocok. Muat ulang halaman dan coba lagi.']);
        }

        return $event;
    }

    private function event(?QueueTicket $ticket, string $action, string $operation, string $actor, ?int $user, ?TicketStatus $from = null, ?string $reason = null): void
    {
        QueueEvent::create(['queue_ticket_id' => $ticket?->id, 'user_id' => $user, 'actor' => $actor, 'action' => $action,
            'from_status' => $from?->value, 'to_status' => $ticket?->status->value, 'reason' => $reason, 'operation_id' => $operation]);
    }

    public function issue(array $data, string $token, string $operation, ?int $staff = null): QueueTicket
    {
        return DB::transaction(function () use ($data, $token, $operation, $staff) {
            $settings = $this->settings();
            $actor = $staff ? 'staff' : 'customer';
            if ($event = $this->replay($operation, 'issue', $actor, $staff)) {
                $ticket = $event->ticket;
                // Registration retries must possess the same access secret, not just a UUID.
                if (! $ticket || ! hash_equals($ticket->access_token_hash, hash('sha256', $token))) {
                    throw ValidationException::withMessages(['action' => 'Formulir kedaluwarsa. Muat ulang halaman.']);
                }

                return $ticket;
            }
            $ticket = QueueTicket::create(['number' => $settings->next_number, 'access_token_hash' => hash('sha256', $token),
                'customer_name' => $data['customer_name'], 'party_size' => $data['party_size'], 'phone' => $data['phone'] ?? null,
                'status' => TicketStatus::Waiting, 'joined_at' => now()]);
            $settings->increment('next_number');
            $this->event($ticket, 'issue', $operation, $actor, $staff);

            return $ticket;
        }, 3);
    }

    public function callNext(string $operation, int $staff): ?QueueTicket
    {
        return DB::transaction(function () use ($operation, $staff) {
            $settings = $this->settings();
            if ($event = $this->replay($operation, 'call', 'staff', $staff)) {
                return $event->ticket;
            }
            $ticket = QueueTicket::where('status', TicketStatus::Waiting)->orderBy('number')->lockForUpdate()->first();
            if ($ticket) {
                $ticket->update(['status' => TicketStatus::Called, 'called_at' => now(), 'arrival_deadline' => now()->addMinutes($settings->arrival_grace_minutes)]);
            }
            // Persist empty results too: retrying an empty call must not claim a later arrival.
            $this->event($ticket, 'call', $operation, 'staff', $staff, $ticket ? TicketStatus::Waiting : null);

            return $ticket;
        }, 3);
    }

    public function transition(QueueTicket $ticket, string $action, string $operation, ?int $staff, ?string $reason = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $action, $operation, $staff, $reason) {
            $this->settings();
            $actor = $staff ? 'staff' : 'customer';
            if ($event = $this->replay($operation, $action, $actor, $staff, $ticket->id)) {
                if ($action === 'cancel' && $event->reason !== ($staff ? $reason : 'customer')) {
                    throw ValidationException::withMessages(['action' => 'Alasan pembatalan berubah. Muat ulang halaman.']);
                }

                return $event->ticket;
            }
            $ticket = QueueTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $from = $ticket->status;
            $allowed = match ($action) {
                'arrive' => $staff && $from === TicketStatus::Called,
                'complete' => $staff && $from === TicketStatus::Arrived,
                'cancel' => $staff ? in_array($from, [TicketStatus::Waiting, TicketStatus::Called, TicketStatus::Arrived], true) : in_array($from, [TicketStatus::Waiting, TicketStatus::Called], true),
                default => false,
            };
            if (! $allowed) {
                throw ValidationException::withMessages(['action' => 'Status antrean sudah berubah atau tindakan tidak diizinkan. Periksa status terbaru.']);
            }
            if ($action === 'cancel' && $reason === 'no_show' && ($from !== TicketStatus::Called || ! $ticket->arrival_deadline || now()->lt($ticket->arrival_deadline))) {
                throw ValidationException::withMessages(['action' => 'Pembatalan tidak hadir hanya tersedia setelah batas kedatangan.']);
            }
            if ($action === 'cancel' && $reason === 'skipped' && $from === TicketStatus::Arrived) {
                throw ValidationException::withMessages(['action' => 'Pelanggan yang sudah hadir tidak dapat dilewati.']);
            }
            $changes = match ($action) {
                'arrive' => ['status' => TicketStatus::Arrived, 'arrived_at' => now()],
                'complete' => ['status' => TicketStatus::Completed, 'completed_at' => now()],
                'cancel' => ['status' => TicketStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $staff ? $reason : 'customer'],
            };
            $ticket->update($changes);
            $this->event($ticket, $action, $operation, $actor, $staff, $from, $changes['cancellation_reason'] ?? null);

            return $ticket;
        }, 3);
    }
}
