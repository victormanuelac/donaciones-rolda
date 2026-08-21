<?php

use App\Enums\BeneficiaryPriorityLevel;
use App\Enums\UserRole;
use App\Models\Beneficiary;
use App\Models\Category;
use App\Models\Family;
use App\Models\MasterItem;
use App\Models\ProtocolRecommendation;
use App\Models\User;
use Livewire\Livewire;

function profileFamily(array $overrides = []): Family
{
    return Family::create([...[
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => 'Jefa de hogar '.uniqid(),
        'housing_damage_level' => 'sin_dano',
        'household_size' => 1,
    ], ...$overrides]);
}

function profileBeneficiary(Family $family, array $overrides = []): Beneficiary
{
    return Beneficiary::create([...[
        'family_id' => $family->id,
        'full_name' => 'Beneficiario de prueba',
        'birthdate' => now()->subYears(2)->toDateString(),
    ], ...$overrides]);
}

test('roles sin permiso no pueden ver el perfil de un beneficiario', function (UserRole $role) {
    $user = User::factory()->create(['role' => $role]);
    $family = profileFamily();
    $beneficiary = profileBeneficiary($family);

    $this->actingAs($user)->get(route('beneficiaries.profile', $beneficiary))->assertForbidden();
})->with([UserRole::Operator, UserRole::Donor, UserRole::Municipal]);

test('guardar el perfil calcula el puntaje y la prioridad', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $family = profileFamily();
    $beneficiary = profileBeneficiary($family, ['birthdate' => now()->subYears(2)->toDateString()]);

    Livewire::actingAs($coordinator)
        ->test('pages::beneficiaries.profile', ['beneficiary' => $beneficiary])
        ->set('hasNoHome', true)
        ->set('chronicConditions', 'Diabetes, Hipertensión')
        ->call('save')
        ->assertHasNoErrors();

    $beneficiary->refresh();

    expect($beneficiary->hasProfile())->toBeTrue()
        ->and($beneficiary->priority_level)->toBe(BeneficiaryPriorityLevel::Critical)
        ->and($beneficiary->chronic_conditions)->toBe(['Diabetes', 'Hipertensión']);
});

test('guardar el perfil genera recomendaciones que cruzan con protocolos vigentes', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $category = Category::create(['name' => 'Categoría '.uniqid()]);
    $item = MasterItem::create(['category_id' => $category->id, 'name' => 'Leche en polvo', 'unit_of_measure' => 'bolsas']);

    ProtocolRecommendation::create([
        'protocol_name' => 'Suplementación menores de 5 años',
        'source' => 'icbf',
        'trigger_condition' => ['age_min' => 0, 'age_max' => 5],
        'recommended_items' => [['item_id' => $item->id, 'quantity' => 1, 'frequency' => 'daily', 'duration_days' => 30]],
        'confidence_level' => 0.95,
    ]);

    $family = profileFamily();
    $beneficiary = profileBeneficiary($family, ['birthdate' => now()->subYears(2)->toDateString()]);

    Livewire::actingAs($coordinator)
        ->test('pages::beneficiaries.profile', ['beneficiary' => $beneficiary])
        ->call('save')
        ->assertSee('Leche en polvo')
        ->assertSee('Suplementación menores de 5 años');
});

test('el pregnancy_trimester se limpia si no esta embarazada', function () {
    $coordinator = User::factory()->create(['role' => UserRole::Coordinator]);
    $family = profileFamily();
    $beneficiary = profileBeneficiary($family, ['is_pregnant' => true, 'pregnancy_trimester' => 8]);

    Livewire::actingAs($coordinator)
        ->test('pages::beneficiaries.profile', ['beneficiary' => $beneficiary])
        ->set('isPregnant', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($beneficiary->fresh()->pregnancy_trimester)->toBeNull();
});
