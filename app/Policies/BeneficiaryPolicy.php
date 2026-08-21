<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Autorización del Módulo 7 (Fase 2): navegar hogares/beneficiarios y
 * completar el perfil de vulnerabilidad. Anticipada por nombre en CLAUDE.md
 * como el mecanismo de autorización de este módulo.
 */
class BeneficiaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageBeneficiaries();
    }

    public function view(User $user): bool
    {
        return $user->canManageBeneficiaries();
    }

    public function manageProfile(User $user): bool
    {
        return $user->canManageBeneficiaries();
    }
}
