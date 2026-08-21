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
    case InventoryAdjustment = 'inventory_adjustment';
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
            self::InventoryAdjustment => 'Ajuste por conteo físico',
            self::Other => 'Otro',
        };
    }

    /**
     * Motivos de baja de inventario sin entrega a un beneficiario — son los únicos
     * que pueden usarse para dar de baja un lote ya vencido (ver RegisterStockExitAction).
     * El ajuste por conteo también aplica sobre lotes vencidos: es una corrección de
     * un dato incorrecto, no un despacho real, así que debe poder registrarse igual.
     */
    public function isWriteOff(): bool
    {
        return in_array($this, [self::Loss, self::Damage, self::ExpiredDiscard, self::InventoryAdjustment], true);
    }

    /**
     * Motivos que representan demanda real, para proyectar el ritmo de consumo:
     * se excluyen los traslados (no bajan el total del sistema, solo lo mueven)
     * y las bajas por pérdida, daño o vencimiento (no reflejan consumo).
     *
     * Vive en el enum porque lo comparten `StockProjectionService` (proyección
     * por ítem) y `KardexAlertsService` (agregado de todos los ítems a la vez).
     *
     * @return array<int, string>
     */
    public static function demandValues(): array
    {
        return array_map(
            fn (self $reason) => $reason->value,
            [self::Donation, self::SubsidizedSale, self::EmergencyAssistance, self::Other],
        );
    }
}
