<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('el seeder crea el usuario admin de pruebas documentado en el README', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@donaciones-rolda.test')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->role)->toBe(UserRole::Admin)
        ->and($admin->status)->toBe(UserStatus::Active)
        ->and($admin->isAdmin())->toBeTrue()
        ->and(Hash::check('AdminRolda#2026', $admin->password))->toBeTrue();
});

test('un usuario admin creado por factory pasa isAdmin()', function () {
    $admin = User::factory()->admin()->create();
    $operator = User::factory()->create();

    expect($admin->isAdmin())->toBeTrue()
        ->and($operator->isAdmin())->toBeFalse()
        ->and($operator->role)->toBe(UserRole::Operator);
});
