<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Inactive = 'inactive';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de aprobación',
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
            self::Rejected => 'Rechazado',
        };
    }
}
