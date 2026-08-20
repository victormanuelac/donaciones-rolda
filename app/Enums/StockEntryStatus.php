<?php

declare(strict_types=1);

namespace App\Enums;

enum StockEntryStatus: string
{
    case PendingArrival = 'pending_arrival';
    case Available = 'available';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::PendingArrival => 'Pendiente de llegada',
            self::Available => 'Disponible',
            self::Expired => 'Vencido',
            self::Withdrawn => 'Retirado',
        };
    }
}
