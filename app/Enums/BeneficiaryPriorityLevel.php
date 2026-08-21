<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Prioridad del perfil de vulnerabilidad individual (Módulo 7, Fase 2),
 * calculada por VulnerabilityScoringService. Deliberadamente distinta de
 * CensusPriorityLevel (triaje de hogar, Fase 1) — son dos sistemas de
 * puntaje independientes, uno por hogar y otro por persona, que no se
 * intenta reconciliar.
 */
enum BeneficiaryPriorityLevel: string
{
    case Critical = 'critical';
    case Priority = 'priority';
    case Normal = 'normal';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Crítico',
            self::Priority => 'Prioritario',
            self::Normal => 'Normal',
        };
    }

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 70 => self::Critical,
            $score >= 40 => self::Priority,
            default => self::Normal,
        };
    }
}
