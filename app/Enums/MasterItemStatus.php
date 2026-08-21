<?php

declare(strict_types=1);

namespace App\Enums;

enum MasterItemStatus: string
{
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Consolidated = 'consolidated';

    public function label(): string
    {
        return match ($this) {
            self::UnderReview => 'En revisión',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Consolidated => 'Consolidado (duplicado)',
        };
    }
}
