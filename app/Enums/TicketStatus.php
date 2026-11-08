<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case Arrived = 'arrived';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu', self::Called => 'Dipanggil', self::Arrived => 'Hadir',
            self::Completed => 'Selesai', self::Cancelled => 'Dibatalkan',
        };
    }
}
