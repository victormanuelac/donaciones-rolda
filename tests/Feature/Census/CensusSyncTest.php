<?php

use App\Enums\CensusPriorityLevel;
use App\Enums\UserRole;
use App\Models\CensusEntry;
use App\Models\Family;
use App\Models\User;
use Illuminate\Support\Str;

function validCensusEntryPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'client_uuid' => (string) Str::uuid(),
        'family' => [
            'zone_type' => 'urbana',
            'neighborhood' => 'Barrio Centro',
            'address' => 'Calle 5 #10-20',
            'phone' => '3121234567',
            'latitude' => 4.4144,
            'longitude' => -76.1536,
            'gps_accuracy_meters' => 12,
            'head_full_name' => 'María García López',
            'head_document_type' => 'CC',
            'head_document_number' => '1234567890',
            'head_sex' => 'mujer',
            'housing_damage_level' => 'averiada_habitable',
            'tenure_type' => 'propia',
            'water_access' => 'si',
            'water_source' => 'acueducto',
            'electricity_access' => 'si',
            'sanitation_access' => 'si',
        ],
        'census_entry' => [
            'surveyed_at' => now()->toDateTimeString(),
            'surveyor_entity' => 'donaciones_rolda',
            'consent_given' => true,
            'consent_given_by_name' => 'María García López',
            'consent_relationship' => 'jefe de hogar',
            'total_people' => 4,
            'under_5_count' => 1,
            'over_60_count' => 0,
            'pregnant_lactating_count' => 0,
            'disability_count' => 0,
            'chronic_illness_count' => 0,
            'meals_yesterday' => 3,
            'injured' => false,
            'needs_urgent_medical_attention' => false,
            'lost_permanent_medication' => false,
            'sleeping_location' => 'su_vivienda',
            'needs_temporary_shelter' => 'no',
            'access_passable' => 'si',
            'priority_needs' => ['agua', 'alimentos'],
            'registered_in_rud' => 'no_sabe',
            'damage_verified' => 'si',
            'needs_structural_assessment' => false,
        ],
        'members' => [
            ['full_name' => 'Juan García', 'sex' => 'hombre', 'relationship_to_head' => 'cónyuge'],
        ],
    ], $overrides);
}

test('un operador autenticado puede sincronizar una captura del censo', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    $response = $this->actingAs($operator)->postJson('/censo/sync', [
        'entries' => [validCensusEntryPayload()],
    ]);

    $response->assertOk();
    $response->assertJsonPath('results.0.status', 'ok');

    expect(Family::count())->toBe(1)
        ->and(CensusEntry::count())->toBe(1);

    $entry = CensusEntry::first();

    expect(Family::first()->household_size)->toBe($entry->total_people)
        ->and($entry->user_id)->toBe($operator->id)
        ->and($entry->form_code)->toStartWith('ROL-2026-')
        ->and($entry->beneficiaries)->toHaveCount(1);
});

test('reenviar el mismo client_uuid no duplica la captura', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $payload = validCensusEntryPayload();

    $this->actingAs($operator)->postJson('/censo/sync', ['entries' => [$payload]])->assertOk();
    $this->actingAs($operator)->postJson('/censo/sync', ['entries' => [$payload]])->assertOk();

    expect(CensusEntry::count())->toBe(1);
});

test('un hogar en vivienda destruida con menor de 5 años queda con prioridad crítica', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    $payload = validCensusEntryPayload([
        'family' => ['housing_damage_level' => 'destruida'],
        'census_entry' => ['sleeping_location' => 'a_la_intemperie', 'under_5_count' => 1],
    ]);

    $this->actingAs($operator)->postJson('/censo/sync', ['entries' => [$payload]])->assertOk();

    $entry = CensusEntry::first();

    expect($entry->priority_level)->toBe(CensusPriorityLevel::Critico)
        ->and($entry->red_flags)->toContain('sin_techo_con_menor_o_gestante');
});

test('rechaza la captura si falta un campo obligatorio', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    $payload = validCensusEntryPayload();
    unset($payload['family']['address']);

    $response = $this->actingAs($operator)->postJson('/censo/sync', ['entries' => [$payload]]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['entries.0.family.address']);
    expect(CensusEntry::count())->toBe(0);
});

test('rechaza la captura si no se otorgó el consentimiento', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);

    $payload = validCensusEntryPayload(['census_entry' => ['consent_given' => false]]);

    $response = $this->actingAs($operator)->postJson('/censo/sync', ['entries' => [$payload]]);

    $response->assertUnprocessable();
    expect(CensusEntry::count())->toBe(0);
});

test('un rol sin acceso de campo no puede sincronizar el censo', function () {
    $donor = User::factory()->create(['role' => UserRole::Donor]);

    $response = $this->actingAs($donor)->postJson('/censo/sync', [
        'entries' => [validCensusEntryPayload()],
    ]);

    $response->assertForbidden();
});

test('un invitado no puede sincronizar el censo', function () {
    $response = $this->postJson('/censo/sync', ['entries' => [validCensusEntryPayload()]]);

    $response->assertUnauthorized();
});

test('un operador puede ver el formulario de censo y un donante no', function () {
    $operator = User::factory()->create(['role' => UserRole::Operator]);
    $donor = User::factory()->create(['role' => UserRole::Donor]);

    $this->actingAs($operator)->get('/censo/nuevo')->assertOk();
    $this->actingAs($donor)->get('/censo/nuevo')->assertForbidden();
});
