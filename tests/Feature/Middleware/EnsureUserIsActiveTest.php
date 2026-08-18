<?php

use App\Enums\UserStatus;
use App\Models\User;

test('un usuario pending es redirigido a account.pending al visitar el dashboard', function () {
    $user = User::factory()->create(['status' => UserStatus::Pending]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('account.pending'));
});

test('un usuario active puede visitar el dashboard sin redirección', function () {
    $user = User::factory()->create(['status' => UserStatus::Active]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
});

test('un usuario pending puede ver su propia página de estado sin loop de redirección', function () {
    $user = User::factory()->create(['status' => UserStatus::Pending]);

    $response = $this->actingAs($user)->get(route('account.pending'));

    $response->assertOk();
});

test('un usuario pending puede cerrar sesión', function () {
    $user = User::factory()->create(['status' => UserStatus::Pending]);

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect('/');
    $this->assertGuest();
});
