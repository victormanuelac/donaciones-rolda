<?php

declare(strict_types=1);

namespace App\Enums;

enum AvailabilityLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::High => 'Alta',
            self::Medium => 'Media',
            self::Low => 'Baja',
            self::None => 'Agotado',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::High => '🟢',
            self::Medium => '🟡',
            self::Low => '🔴',
            self::None => '⚫',
        };
    }

    /**
     * Umbrales documentados en docs/04-Diagramas-Flujos-Modulos.md
     * ("Cantidad: Alta (> 20 unidades)").
     */
    public static function fromQuantity(int $quantity): self
    {
        return match (true) {
            $quantity > 20 => self::High,
            $quantity >= 6 => self::Medium,
            $quantity >= 1 => self::Low,
            default => self::None,
        };
    }
}
