<?php

use App\Enums\UserRole;
use App\Models\Beneficiary;
use App\Models\Family;
use App\Models\User;
use Livewire\Livewire;

function showFamily(array $overrides = []): Family
{
    return Family::create([...[
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => 'Jefa de hogar '.uniqid(),
        'housing_damage_level' => 'sin_dano',
        'household_size' => 3,
    ], ...$overrides]);
}

test('roles sin permiso no pueden ver el detalle de un hogar', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $family = showFamily();

    $this->actingAs($user)->get(route('beneficiaries.show', $family))->assertForbidden();
})->with([UserRole::Operator, UserRole::Donor, UserRole::Municipal]);

test('un coordinador puede ver el detalle del hogar y sus integrantes', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $family = showFamily(['head_full_name' => 'Yolanda Pérez']);
    Beneficiary::create(['family_id' => $family->id, 'full_name' => 'Yolanda Pérez', 'is_household_head' => true]);

    $this->actingAs($coordinator)
        ->get(route('beneficiaries.show', $family))
        ->assertOk()
        ->assertSee('Yolanda Pérez');
});

test('agregar un integrante lo suma al listado del hogar', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $family = showFamily();

    Livewire::actingAs($coordinator)
        ->test('pages::beneficiaries.show', ['family' => $family])
        ->call('openAddMemberModal')
        ->set('memberFullName', 'Nuevo Integrante')
        ->set('memberRelationship', 'Hijo/a')
        ->call('addMember')
        ->assertHasNoErrors();

    expect(Beneficiary::where('full_name', 'Nuevo Integrante')->where('family_id', $family->id)->exists())->toBeTrue();
});

test('agregar un integrante exige el nombre', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $family = showFamily();

    Livewire::actingAs($coordinator)
        ->test('pages::beneficiaries.show', ['family' => $family])
        ->set('memberFullName', '')
        ->call('addMember')
        ->assertHasErrors(['memberFullName']);

    expect(Beneficiary::where('family_id', $family->id)->count())->toBe(0);
});
