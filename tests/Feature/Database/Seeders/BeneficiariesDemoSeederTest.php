<?php

use App\Enums\BeneficiaryPriorityLevel;
use App\Models\Beneficiary;
use App\Models\BeneficiaryRecommendation;
use App\Models\ProtocolRecommendation;
use Database\Seeders\BeneficiariesDemoSeeder;
use Database\Seeders\DeliveriesDemoSeeder;
use Database\Seeders\GeographicZoneSeeder;
use Database\Seeders\KardexDemoSeeder;

function seedBeneficiariesDemo(): void
{
    test()->seed(GeographicZoneSeeder::class);
    test()->seed(KardexDemoSeeder::class);
    test()->seed(DeliveriesDemoSeeder::class);
    test()->seed(BeneficiariesDemoSeeder::class);
}

test('siembra los 3 protocolos de ejemplo', function () {
    seedBeneficiariesDemo();

    expect(ProtocolRecommendation::count())->toBe(3);
});

test('siembra beneficiarios con perfil de vulnerabilidad completo', function () {
    seedBeneficiariesDemo();

    $pregnant = Beneficiary::where('full_name', 'Yolanda Pérez')->firstOrFail();
    $withDiabetes = Beneficiary::where('full_name', 'Carlos Gómez')->firstOrFail();

    expect($pregnant->hasProfile())->toBeTrue()
        ->and($pregnant->is_pregnant)->toBeTrue()
        ->and($withDiabetes->hasProfile())->toBeTrue()
        ->and($withDiabetes->chronic_conditions)->toBe(['Diabetes']);
});

test('siembra recomendaciones generadas para los beneficiarios de ejemplo', function () {
    seedBeneficiariesDemo();

    $pregnant = Beneficiary::where('full_name', 'Yolanda Pérez')->firstOrFail();

    $recommendation = BeneficiaryRecommendation::where('beneficiary_id', $pregnant->id)->first();

    expect($recommendation)->not->toBeNull()
        ->and($recommendation->masterItem->name)->toBe('Hierro y Ácido Fólico (prenatal)')
        ->and($recommendation->available_stock)->toBeGreaterThan(0);
});

test('el embarazo en tercer trimestre queda con prioridad alta', function () {
    seedBeneficiariesDemo();

    $pregnant = Beneficiary::where('full_name', 'Yolanda Pérez')->firstOrFail();

    expect($pregnant->priority_level)->toBeIn([BeneficiaryPriorityLevel::Priority, BeneficiaryPriorityLevel::Critical]);
});
