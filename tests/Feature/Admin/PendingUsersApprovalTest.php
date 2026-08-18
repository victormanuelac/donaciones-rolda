<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Livewire\Livewire;

test('un admin puede ver el panel de usuarios pendientes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.pending'))
        ->assertOk();
});

test('un no-admin recibe 403 al intentar ver el panel', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    $this->actingAs($operator)
        ->get(route('admin.users.pending'))
        ->assertForbidden();
});

test('un admin puede aprobar a un usuario pendiente asignándole un rol', function () {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->create(['status' => UserStatus::Pending, 'role' => UserRole::Operator]);

    Livewire::actingAs($admin)
        ->test('pages::admin.users-pending')
        ->set("selectedRole.{$pending->id}", UserRole::Coordinator->value)
        ->call('approve', $pending->id);

    $pending->refresh();

    expect($pending->status)->toBe(UserStatus::Active)
        ->and($pending->role)->toBe(UserRole::Coordinator);
});

test('un admin puede rechazar a un usuario pendiente', function () {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->create(['status' => UserStatus::Pending]);

    Livewire::actingAs($admin)
        ->test('pages::admin.users-pending')
        ->call('reject', $pending->id);

    expect($pending->refresh()->status)->toBe(UserStatus::Rejected);
});

test('no se puede volver a aprobar a un usuario que ya no está pendiente', function () {
    $admin = User::factory()->admin()->create();
    $active = User::factory()->create(['status' => UserStatus::Active]);

    Livewire::actingAs($admin)
        ->test('pages::admin.users-pending')
        ->call('approve', $active->id)
        ->assertForbidden();
});

test('el panel solo lista usuarios pending, no active/rejected/inactive', function () {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->create(['status' => UserStatus::Pending, 'name' => 'Pendiente Uno']);
    User::factory()->create(['status' => UserStatus::Active, 'name' => 'Activo']);
    User::factory()->create(['status' => UserStatus::Rejected, 'name' => 'Rechazado']);

    Livewire::actingAs($admin)
        ->test('pages::admin.users-pending')
        ->assertSee('Pendiente Uno')
        ->assertDontSee('Activo')
        ->assertDontSee('Rechazado');
});
