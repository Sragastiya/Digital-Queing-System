<?php

namespace App\Services;

use App\Enums\CustomerCancellationReason;
use App\Enums\TicketStatus;
use App\Enums\QueueType;
use App\Models\QueueEvent;
use App\Models\QueueSetting;
use App\Models\QueueTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QueueService
{
    // Keep one short mutex for global numbering and operation replay across both queues.
    private function settings(): QueueSetting
    {
        return QueueSetting::query()->lockForUpdate()->findOrFail(1);
    }

    private function replay(string $operation, string $action, string $actor, ?int $user, QueueType $queueType, ?int $ticket = null): ?QueueEvent
    {
        $event = QueueEvent::where('operation_id', $operation)->first();
        if ($event && ($event->action !== $action || $event->actor !== $actor || $event->user_id !== $user || $event->queue_type !== $queueType || ($ticket !== null && $event->queue_ticket_id !== $ticket))) {
            throw ValidationException::withMessages(['action' => 'Permintaan tidak cocok. Muat ulang halaman dan coba lagi.']);
        }

        return $event;
    }

    private function event(?QueueTicket $ticket, string $action, string $operation, string $actor, ?int $user, QueueType $queueType, ?TicketStatus $from = null, ?string $reason = null, ?int $relatedTicket = null): void
    {
        QueueEvent::create(['queue_ticket_id' => $ticket?->id, 'queue_type' => $queueType, 'user_id' => $user, 'actor' => $actor, 'action' => $action,
            'from_status' => $from?->value, 'to_status' => $ticket?->status->value, 'reason' => $reason, 'operation_id' => $operation, 'related_ticket_id' => $relatedTicket]);
    }

    private function createTicket(QueueSetting $settings, array $data, string $token, QueueType $queueType): QueueTicket
    {
        $ticket = QueueTicket::create(['number' => $settings->next_number, 'queue_order' => $settings->next_number,
            'queue_type' => $queueType, 'access_token_hash' => hash('sha256', $token),
            'customer_name' => $data['customer_name'], 'party_size' => $data['party_size'], 'phone' => $data['phone'] ?? null,
            'status' => TicketStatus::Waiting, 'joined_at' => now()]);
        $settings->increment('next_number');

        return $ticket;
    }

    public function issue(array $data, string $token, string $operation, ?int $staff = null): QueueTicket
    {
        $type = $data['queue_type'] ?? null;
        $queueType = $type instanceof QueueType ? $type : (is_string($type) ? QueueType::tryFrom($type) : null);
        if (! $queueType) {
            throw ValidationException::withMessages(['queue_type' => 'Pilih jenis antrean yang valid.']);
        }

        return DB::transaction(function () use ($data, $token, $operation, $staff, $queueType) {
            $settings = $this->settings();
            $actor = $staff ? 'staff' : 'customer';
            if ($event = $this->replay($operation, 'issue', $actor, $staff, $queueType)) {
                $ticket = $event->ticket;
                // Registration retries must possess the same access secret, not just a UUID.
                if (! $ticket || ! hash_equals($ticket->access_token_hash, hash('sha256', $token))) {
                    throw ValidationException::withMessages(['action' => 'Formulir kedaluwarsa. Muat ulang halaman.']);
                }

                return $ticket;
            }
            $ticket = $this->createTicket($settings, $data, $token, $queueType);
            $this->event($ticket, 'issue', $operation, $actor, $staff, $queueType);

            return $ticket;
        }, 3);
    }

    public function callNext(string $operation, int $staff, QueueType $queueType): ?QueueTicket
    {
        return DB::transaction(function () use ($operation, $staff, $queueType) {
            $settings = $this->settings();
            if ($event = $this->replay($operation, 'call', 'staff', $staff, $queueType)) {
                return $event->ticket;
            }
            $ticket = $this->markNextCalled($settings, $queueType);
            // Persist empty results too: retrying an empty call must not claim a later arrival.
            $this->event($ticket, 'call', $operation, 'staff', $staff, $queueType, $ticket ? TicketStatus::Waiting : null);

            return $ticket;
        }, 3);
    }

    // The caller holds the settings lock for both manual and automatic calls.
    private function markNextCalled(QueueSetting $settings, QueueType $queueType): ?QueueTicket
    {
        $ticket = QueueTicket::where('queue_type', $queueType)->where('status', TicketStatus::Waiting)
            ->orderBy('queue_order')->orderBy('number')->lockForUpdate()->first();
        if ($ticket) {
            $calledAt = now()->toImmutable();
            $ticket->update(['status' => TicketStatus::Called, 'called_at' => $calledAt,
                'arrival_deadline' => $calledAt->addMinutes($settings->arrival_grace_minutes)]);
        }

        return $ticket;
    }

    public function transition(QueueTicket $ticket, string $action, string $operation, ?int $staff, ?string $reason = null): QueueTicket
    {
        if ($action === 'cancel' && ! $staff && ! CustomerCancellationReason::tryFrom($reason ?? '')) {
            throw ValidationException::withMessages(['reason' => 'Pilih alasan pembatalan yang tersedia.']);
        }
        return DB::transaction(function () use ($ticket, $action, $operation, $staff, $reason) {
            $settings = $this->settings();
            // Reload the persisted ticket under the same lock order; posted or stale
            // queue attributes cannot change the transition's audit/replay context.
            $ticket = QueueTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $queueType = $ticket->queue_type;
            $actor = $staff ? 'staff' : 'customer';
            if ($event = $this->replay($operation, $action, $actor, $staff, $queueType, $ticket->id)) {
                if ($action === 'cancel' && $event->reason !== $reason) {
                    throw ValidationException::withMessages(['action' => 'Alasan pembatalan berubah. Muat ulang halaman.']);
                }

                return $event->ticket;
            }
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
                'cancel' => ['status' => TicketStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason],
            };
            $ticket->update($changes);
            $next = null;
            if ($action === 'cancel' && $actor === 'customer' && $from === TicketStatus::Called && $settings->autoCallEnabled($queueType)) {
                $next = $this->markNextCalled($settings, $queueType);
            }
            $this->event($ticket, $action, $operation, $actor, $staff, $queueType, $from, $changes['cancellation_reason'] ?? null, $next?->id);
            if ($next) {
                $this->event($next, 'auto_call', (string) Str::uuid(), 'system', null, $queueType, TicketStatus::Waiting);
            }

            return $ticket;
        }, 3);
    }

    public function giveTurn(QueueTicket $ticket, string $operation): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $operation) {
            $this->settings();
            $ticket = QueueTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($event = $this->replay($operation, 'give_turn', 'customer', null, $ticket->queue_type, $ticket->id)) {
                return $event->ticket;
            }
            $this->requireWaiting($ticket);
            $followers = QueueTicket::where('queue_type', $ticket->queue_type)->where('status', TicketStatus::Waiting)
                ->where('queue_order', '>', $ticket->queue_order)->orderBy('queue_order')->orderBy('number')->limit(3)->lockForUpdate()->get();
            if ($followers->isEmpty()) {
                throw ValidationException::withMessages(['action' => 'Belum ada pelanggan menunggu di belakang Anda. Posisi tidak diubah.']);
            }
            // Rotate existing slots: ticket numbers and the other service remain untouched.
            $slot = $ticket->queue_order;
            foreach ($followers as $follower) {
                $nextSlot = $follower->queue_order;
                $follower->update(['queue_order' => $slot]);
                $slot = $nextSlot;
            }
            $ticket->update(['queue_order' => $slot]);
            $this->event($ticket, 'give_turn', $operation, 'customer', null, $ticket->queue_type, TicketStatus::Waiting, 'back_'.$followers->count());

            return $ticket;
        }, 3);
    }

    public function switchService(QueueTicket $ticket, string $sourceToken, QueueType $destination, string $operation): array
    {
        return DB::transaction(function () use ($ticket, $sourceToken, $destination, $operation) {
            $settings = $this->settings();
            $ticket = QueueTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if (! hash_equals($ticket->access_token_hash, hash('sha256', $sourceToken))) {
                throw ValidationException::withMessages(['action' => 'Tautan akses tidak cocok dengan tiket.']);
            }
            // Recover the same private redirect on retries; never store the plaintext secret.
            $token = $this->transferToken($sourceToken, $operation);
            if ($event = $this->replay($operation, 'switch_service', 'customer', null, $ticket->queue_type, $ticket->id)) {
                $replacement = $event->relatedTicket;
                if (! $replacement || $replacement->queue_type !== $destination || ! hash_equals($replacement->access_token_hash, hash('sha256', $token))) {
                    throw ValidationException::withMessages(['action' => 'Tujuan perpindahan berubah. Muat ulang halaman.']);
                }

                return ['ticket' => $replacement, 'token' => $token];
            }
            $this->requireWaiting($ticket);
            if ($destination === $ticket->queue_type) {
                throw ValidationException::withMessages(['queue_type' => 'Pilih layanan yang berbeda dari tiket saat ini.']);
            }
            $replacement = $this->createTicket($settings, $ticket->only(['customer_name', 'party_size', 'phone']), $token, $destination);
            $ticket->update(['status' => TicketStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => 'service_switch']);
            $this->event($ticket, 'switch_service', $operation, 'customer', null, $ticket->queue_type, TicketStatus::Waiting, 'service_switch', $replacement->id);

            return ['ticket' => $replacement, 'token' => $token];
        }, 3);
    }

    public function transferToken(string $sourceToken, string $operation): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new \LogicException('An application key is required for private ticket transfers.');
        }

        return hash_hmac('sha256', "queue-switch:v1\0".$sourceToken."\0".$operation, $key);
    }

    private function requireWaiting(QueueTicket $ticket): void
    {
        if ($ticket->status !== TicketStatus::Waiting) {
            throw ValidationException::withMessages(['action' => 'Pindah layanan dan beri giliran hanya tersedia saat masih menunggu. Periksa status terbaru.']);
        }
    }
}
