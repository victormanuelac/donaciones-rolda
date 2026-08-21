<?php

use App\Enums\UserRole;
use App\Models\CensusEntry;
use App\Models\Family;
use App\Models\GeographicZone;
use App\Models\User;
use Livewire\Livewire;

function indexFamily(array $overrides = []): Family
{
    return Family::create([...[
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => 'Jefa de hogar '.uniqid(),
        'housing_damage_level' => 'sin_dano',
        'household_size' => 3,
    ], ...$overrides]);
}

function indexCensusEntry(Family $family, User $surveyor, string $priorityLevel = 'bajo'): CensusEntry
{
    return $family->censusEntries()->create([
        'user_id' => $surveyor->id,
        'form_code' => 'ROL-TEST-'.uniqid(),
        'surveyed_at' => now(),
        'surveyor_entity' => 'Municipio',
        'consent_given' => true,
        'total_people' => $family->household_size,
        'sleeping_location' => 'su_vivienda',
        'needs_temporary_shelter' => 'no',
        'access_passable' => 'si',
        'priority_needs' => ['agua'],
        'registered_in_rud' => 'no',
        'damage_verified' => 'no',
        'vulnerability_score' => 10,
        'priority_level' => $priorityLevel,
    ]);
}

test('roles sin permiso no pueden ver el listado de beneficiarios', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('beneficiaries.index'))->assertForbidden();
})->with([UserRole::Operator, UserRole::Donor, UserRole::Municipal]);

test('coordinador, admin y doctor pueden ver el listado', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('beneficiaries.index'))->assertOk();
})->with([UserRole::Coordinator, UserRole::Admin, UserRole::Doctor]);

test('el listado filtra por nombre del jefe de hogar', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $perez = indexFamily(['head_full_name' => 'Yolanda Pérez']);
    $gomez = indexFamily(['head_full_name' => 'Carlos Gómez']);

    Livewire::actingAs($coordinator)
        ->test('pages::beneficiaries.index')
        ->set('search', 'Gómez')
        ->assertSee('Carlos Gómez')
        ->assertDontSee('Yolanda Pérez');
});

test('el listado filtra por prioridad del censo de fase 1', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $critical = indexFamily(['head_full_name' => 'Hogar Crítico']);
    $low = indexFamily(['head_full_name' => 'Hogar Bajo']);
    indexCensusEntry($critical, $coordinator, 'critico');
    indexCensusEntry($low, $coordinator, 'bajo');

    Livewire::actingAs($coordinator)
        ->test('pages::beneficiaries.index')
        ->set('priorityFilter', 'critico')
        ->assertSee('Hogar Crítico')
        ->assertDontSee('Hogar Bajo');
});

test('el listado filtra por zona geografica', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $zoneA = GeographicZone::create(['zone_type' => 'barrio', 'name' => 'Barrio Centro']);
    $zoneB = GeographicZone::create(['zone_type' => 'barrio', 'name' => 'Barrio El Salado']);
    $inZone = indexFamily(['zone_id' => $zoneA->id, 'head_full_name' => 'Hogar en Centro']);
    $outsideZone = indexFamily(['zone_id' => $zoneB->id, 'head_full_name' => 'Hogar en Salado']);

    Livewire::actingAs($coordinator)
        ->test('pages::beneficiaries.index')
        ->set('zoneFilter', $zoneA->id)
        ->assertSee('Hogar en Centro')
        ->assertDontSee('Hogar en Salado');
});
