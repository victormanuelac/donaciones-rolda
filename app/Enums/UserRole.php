<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Coordinator = 'coordinator';
    case Doctor = 'doctor';
    case Donor = 'donor';
    case Municipal = 'municipal';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Operator => 'Operador de campo',
            self::Coordinator => 'Coordinador',
            self::Doctor => 'Médico',
            self::Donor => 'Donante',
            self::Municipal => 'Municipalidad',
        };
    }
}
