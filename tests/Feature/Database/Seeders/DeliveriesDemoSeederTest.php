<?php

use App\Models\Family;
use App\Models\MasterItem;
use App\Models\StockExit;
use App\Services\Kardex\StockProjectionService;
use Database\Seeders\DeliveriesDemoSeeder;
use Database\Seeders\GeographicZoneSeeder;
use Database\Seeders\KardexDemoSeeder;

function seedDeliveriesDemo(): void
{
    test()->seed(GeographicZoneSeeder::class);
    test()->seed(KardexDemoSeeder::class);
    test()->seed(DeliveriesDemoSeeder::class);
}

test('siembra hogares beneficiarios de ejemplo', function () {
    seedDeliveriesDemo();

    expect(Family::count())->toBe(3)
        ->and(Family::where('head_full_name', 'Yolanda Pérez')->exists())->toBeTrue();
});

test('siembra entregas vinculadas a hogares beneficiarios', function () {
    seedDeliveriesDemo();

    expect(StockExit::whereNotNull('family_id')->count())->toBeGreaterThan(0);
});

test('siembra un item con proyeccion de agotamiento en menos de 21 dias', function () {
    seedDeliveriesDemo();

    $frijol = MasterItem::where('name', 'Frijol')->firstOrFail();
    $days = app(StockProjectionService::class)->daysRemaining($frijol);

    expect($days)->not->toBeNull()->and($days)->toBeLessThanOrEqual(21);
});
