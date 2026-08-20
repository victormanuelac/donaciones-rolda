<?php

declare(strict_types=1);

namespace App\Enums;

enum CensusPriorityLevel: string
{
    case Critico = 'critico';
    case Alto = 'alto';
    case Medio = 'medio';
    case Bajo = 'bajo';

    public function label(): string
    {
        return match ($this) {
            self::Critico => 'Crítico',
            self::Alto => 'Alto',
            self::Medio => 'Medio',
            self::Bajo => 'Bajo',
        };
    }

    /**
     * Punto de corte del índice de vulnerabilidad 0-100 (Fase 1 de triaje).
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 75 => self::Critico,
            $score >= 50 => self::Alto,
            $score >= 25 => self::Medio,
            default => self::Bajo,
        };
    }
}
