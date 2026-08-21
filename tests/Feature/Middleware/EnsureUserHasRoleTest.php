<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['role:admin,coordinator'])->get('/__test/solo-admin-o-coordinador', fn () => 'ok');
});

test('un rol permitido puede pasar el middleware', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get('/__test/solo-admin-o-coordinador')
        ->assertOk();
})->with([UserRole::Admin, UserRole::Coordinator]);

test('un rol no permitido recibe 403', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get('/__test/solo-admin-o-coordinador')
        ->assertForbidden();
})->with([UserRole::Operator, UserRole::Doctor, UserRole::Donor, UserRole::Municipal]);

test('un invitado sin sesión recibe 403', function () {
    $this->get('/__test/solo-admin-o-coordinador')->assertForbidden();
});
