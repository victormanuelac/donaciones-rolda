<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function canSurveyCensus(): bool
    {
        return in_array($this->role, [UserRole::Operator, UserRole::Coordinator, UserRole::Admin], true);
    }

    public function canManageStock(): bool
    {
        return in_array($this->role, [UserRole::Operator, UserRole::Coordinator, UserRole::Admin], true);
    }

    /**
     * Perfil de vulnerabilidad (Módulo 7, Fase 2): trabajo clínico/social más
     * sensible que el triaje de campo, restringido frente a canSurveyCensus().
     */
    public function canManageBeneficiaries(): bool
    {
        return in_array($this->role, [UserRole::Coordinator, UserRole::Admin, UserRole::Doctor], true);
    }

    /**
     * @return HasMany<WarehouseAssignment, $this>
     */
    public function warehouseAssignments(): HasMany
    {
        return $this->hasMany(WarehouseAssignment::class);
    }

    /**
     * Bodegas donde puede registrar entradas/salidas. Admin y coordinador operan
     * cualquier bodega activa; el operador solo las que tiene asignadas.
     *
     * @return Collection<int, Warehouse>
     */
    public function assignableWarehouses(): Collection
    {
        if (in_array($this->role, [UserRole::Admin, UserRole::Coordinator], true)) {
            return Warehouse::query()->where('is_active', true)->orderBy('name')->get();
        }

        return Warehouse::query()
            ->whereHas('assignments', fn ($query) => $query->where('user_id', $this->id)->where('is_active', true))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
