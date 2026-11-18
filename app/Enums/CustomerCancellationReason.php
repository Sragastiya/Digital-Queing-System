<?php

namespace App\Enums;

enum CustomerCancellationReason: string
{
    case TooLong = 'too_long';
    case FoundElsewhere = 'found_elsewhere';
    case PlansChanged = 'plans_changed';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TooLong => 'Terlalu lama',
            self::FoundElsewhere => 'Sudah dapat tempat lain',
            self::PlansChanged => 'Rencana berubah',
            self::Other => 'Lainnya',
        };
    }
}
