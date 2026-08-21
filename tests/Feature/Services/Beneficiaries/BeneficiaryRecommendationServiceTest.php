<?php

use App\Enums\BeneficiaryRecommendationStatus;
use App\Models\Beneficiary;
use App\Models\BeneficiaryRecommendation;
use App\Models\Category;
use App\Models\Family;
use App\Models\MasterItem;
use App\Models\ProtocolRecommendation;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Beneficiaries\BeneficiaryRecommendationService;

function recommendationFamily(array $overrides = []): Family
{
    return Family::create([...[
        'zone_type' => 'urbana',
        'address' => 'Calle 1 #2-3',
        'head_full_name' => 'Jefa de hogar',
        'housing_damage_level' => 'sin_dano',
        'household_size' => 1,
        'latitude' => 4.4144,
        'longitude' => -76.1536,
    ], ...$overrides]);
}

function recommendationBeneficiary(Family $family, array $overrides = []): Beneficiary
{
    return Beneficiary::create([...[
        'family_id' => $family->id,
        'full_name' => 'Beneficiario de prueba',
        'birthdate' => now()->subYears(30)->toDateString(),
    ], ...$overrides]);
}

function recommendationItem(): MasterItem
{
    $category = Category::create(['name' => 'Categoría '.uniqid()]);

    return MasterItem::create([
        'category_id' => $category->id,
        'name' => 'Leche en polvo',
        'unit_of_measure' => 'bolsas',
    ]);
}

function recommendationWarehouse(): Warehouse
{
    return Warehouse::create([
        'name' => 'Bodega de prueba',
        'address' => 'Dirección',
        'contact_person_name' => 'Coordinador',
        'contact_phone' => '3000000000',
        'latitude' => 4.4144,
        'longitude' => -76.1536,
    ]);
}

beforeEach(function () {
    $this->service = new BeneficiaryRecommendationService;
    $this->recommender = User::factory()->create();
});

test('un protocolo por rango de edad genera una recomendacion', function () {
    $item = recommendationItem();
    $warehouse = recommendationWarehouse();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $this->recommender->id,
        'quantity' => 20,
    ]);

    $protocol = ProtocolRecommendation::create([
        'protocol_name' => 'Suplementación menores de 5 años',
        'source' => 'icbf',
        'trigger_condition' => ['age_min' => 0, 'age_max' => 5],
        'recommended_items' => [['item_id' => $item->id, 'quantity' => 1, 'frequency' => 'daily', 'duration_days' => 30]],
        'confidence_level' => 0.95,
    ]);

    $family = recommendationFamily();
    $child = recommendationBeneficiary($family, ['birthdate' => now()->subYears(2)->toDateString()]);
    $adult = recommendationBeneficiary($family, ['birthdate' => now()->subYears(30)->toDateString()]);

    $childRecommendations = $this->service->generateFor($child, $this->recommender);
    $adultRecommendations = $this->service->generateFor($adult, $this->recommender);

    expect($childRecommendations)->toHaveCount(1)
        ->and($childRecommendations[0]->protocol_recommendation_id)->toBe($protocol->id)
        ->and($childRecommendations[0]->master_item_id)->toBe($item->id)
        ->and($childRecommendations[0]->status)->toBe(BeneficiaryRecommendationStatus::Pending)
        ->and($childRecommendations[0]->available_stock)->toBe(20)
        ->and($adultRecommendations)->toBeEmpty();
});

test('el snapshot de stock incluye la distancia a la bodega', function () {
    $item = recommendationItem();
    $warehouse = recommendationWarehouse();

    StockEntry::create([
        'master_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'registered_by_user_id' => $this->recommender->id,
        'quantity' => 10,
    ]);

    ProtocolRecommendation::create([
        'protocol_name' => 'Embarazo saludable',
        'source' => 'who',
        'trigger_condition' => ['pregnancy' => true],
        'recommended_items' => [['item_id' => $item->id, 'quantity' => 1, 'frequency' => 'daily', 'duration_days' => 270]],
        'confidence_level' => 0.98,
    ]);

    $family = recommendationFamily();
    $beneficiary = recommendationBeneficiary($family, ['is_pregnant' => true]);

    $recommendations = $this->service->generateFor($beneficiary, $this->recommender);

    expect((float) $recommendations[0]->available_warehouses[0]['distance_km'])->toBe(0.0);
});

test('un protocolo con condicion cronica solo aplica si el beneficiario la tiene', function () {
    $item = recommendationItem();

    ProtocolRecommendation::create([
        'protocol_name' => 'Manejo de diabetes',
        'source' => 'local_health',
        'trigger_condition' => ['chronic_diseases' => ['Diabetes']],
        'recommended_items' => [['item_id' => $item->id, 'quantity' => 1, 'frequency' => 'daily', 'duration_days' => null]],
        'confidence_level' => 0.85,
        'requires_medical_approval' => true,
    ]);

    $family = recommendationFamily();
    $withDiabetes = recommendationBeneficiary($family, ['chronic_conditions' => ['Diabetes']]);
    $without = recommendationBeneficiary($family, ['chronic_conditions' => ['Asma']]);

    expect($this->service->generateFor($withDiabetes, $this->recommender))->toHaveCount(1)
        ->and($this->service->generateFor($without, $this->recommender))->toBeEmpty();
});

test('un protocolo inactivo no genera recomendaciones', function () {
    $item = recommendationItem();

    ProtocolRecommendation::create([
        'protocol_name' => 'Protocolo inactivo',
        'source' => 'municipal',
        'trigger_condition' => ['age_min' => 0, 'age_max' => 100],
        'recommended_items' => [['item_id' => $item->id, 'quantity' => 1, 'frequency' => 'once', 'duration_days' => null]],
        'confidence_level' => 0.80,
        'is_active' => false,
    ]);

    $family = recommendationFamily();
    $beneficiary = recommendationBeneficiary($family);

    expect($this->service->generateFor($beneficiary, $this->recommender))->toBeEmpty();
});

test('generar recomendaciones dos veces no duplica filas', function () {
    $item = recommendationItem();

    ProtocolRecommendation::create([
        'protocol_name' => 'Protocolo de prueba',
        'source' => 'donor',
        'trigger_condition' => ['age_min' => 0, 'age_max' => 100],
        'recommended_items' => [['item_id' => $item->id, 'quantity' => 1, 'frequency' => 'once', 'duration_days' => null]],
        'confidence_level' => 0.80,
    ]);

    $family = recommendationFamily();
    $beneficiary = recommendationBeneficiary($family);

    $this->service->generateFor($beneficiary, $this->recommender);
    $this->service->generateFor($beneficiary, $this->recommender);

    expect(BeneficiaryRecommendation::count())->toBe(1);
});
