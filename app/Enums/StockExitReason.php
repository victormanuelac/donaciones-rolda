<?php

declare(strict_types=1);

namespace App\Enums;

enum StockExitReason: string
{
    case Donation = 'donation';
    case SubsidizedSale = 'subsidized_sale';
    case EmergencyAssistance = 'emergency_assistance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Donation => 'Donación',
            self::SubsidizedSale => 'Venta subsidiada',
            self::EmergencyAssistance => 'Asistencia de emergencia',
            self::Other => 'Otro',
        };
    }
}
