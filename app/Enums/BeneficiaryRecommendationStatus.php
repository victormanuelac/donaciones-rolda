<?php

declare(strict_types=1);

namespace App\Enums;

enum BeneficiaryRecommendationStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Fulfilled => 'Cumplida',
            self::Expired => 'Vencida',
            self::Cancelled => 'Cancelada',
        };
    }
}
