<?php

declare(strict_types=1);

namespace App\Enums;

enum ExpiryAlertType: string
{
    case ThirtyDays = '30_days';
    case FourteenDays = '14_days';
    case SevenDays = '7_days';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::ThirtyDays => 'Vence en 30 días',
            self::FourteenDays => 'Vence en 14 días',
            self::SevenDays => 'Vence en 7 días',
            self::Expired => 'Vencido',
        };
    }

    /**
     * Determina el tipo de alerta según los días restantes para el vencimiento
     * (negativo si ya venció). Null si todavía no entra en ninguna ventana de aviso.
     */
    public static function fromDaysRemaining(int $days): ?self
    {
        return match (true) {
            $days < 0 => self::Expired,
            $days <= 7 => self::SevenDays,
            $days <= 14 => self::FourteenDays,
            $days <= 30 => self::ThirtyDays,
            default => null,
        };
    }
}
