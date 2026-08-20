<?php

declare(strict_types=1);

namespace App\Enums;

enum StockExitReason: string
{
    case Donation = 'donation';
    case SubsidizedSale = 'subsidized_sale';
    case EmergencyAssistance = 'emergency_assistance';
    case Transfer = 'transfer';
    case Loss = 'loss';
    case Damage = 'damage';
    case ExpiredDiscard = 'expired_discard';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Donation => 'Donación',
            self::SubsidizedSale => 'Venta subsidiada',
            self::EmergencyAssistance => 'Asistencia de emergencia',
            self::Transfer => 'Traslado a otra bodega',
            self::Loss => 'Pérdida',
            self::Damage => 'Daño',
            self::ExpiredDiscard => 'Descarte por vencimiento',
            self::Other => 'Otro',
        };
    }

    /**
     * Motivos de baja de inventario sin entrega a un beneficiario — son los únicos
     * que pueden usarse para dar de baja un lote ya vencido (ver RegisterStockExitAction).
     */
    public function isWriteOff(): bool
    {
        return in_array($this, [self::Loss, self::Damage, self::ExpiredDiscard], true);
    }
}
