<?php

use App\Enums\AvailabilityLevel;
use App\Enums\StockEntryStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ExpiryAlert;
use App\Models\MasterItem;
use App\Models\StockCount;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\KardexDemoSeeder;
use Illuminate\Support\Facades\Hash;

test('crea un operador de prueba con acceso a ambas bodegas', function () {
    $this->seed(KardexDemoSeeder::class);

    $operator = User::where('email', 'operador@donaciones-rolda.test')->first();

    expect($operator)->not->toBeNull()
        ->and($operator->role)->toBe(UserRole::Operator)
        ->and($operator->status)->toBe(UserStatus::Active)
        ->and(Hash::check('OperadorRolda#2026', $operator->password))->toBeTrue()
        ->and($operator->warehouseAssignments()->count())->toBe(2);
});

test('siembra existencias con los tres niveles de semáforo', function () {
    $this->seed(KardexDemoSeeder::class);

    $levels = StockEntry::where('status', StockEntryStatus::Available)
        ->get()
        ->map(fn (StockEntry $entry) => AvailabilityLevel::fromQuantity($entry->availableQuantity())->value)
        ->unique();

    expect($levels)->toContain('high')
        ->and($levels)->toContain('medium')
        ->and($levels)->toContain('low');
});

test('siembra un lote que queda retirado por agotarse', function () {
    $this->seed(KardexDemoSeeder::class);

    expect(StockEntry::where('status', StockEntryStatus::Withdrawn)->count())->toBeGreaterThan(0);
});

test('siembra al menos una alerta de vencimiento próximo', function () {
    $this->seed(KardexDemoSeeder::class);

    expect(ExpiryAlert::whereNull('resolved_at')->count())->toBeGreaterThan(0);
});

test('siembra al menos un ítem por debajo de su punto de reorden', function () {
    $this->seed(KardexDemoSeeder::class);

    $lowStockItems = MasterItem::whereNotNull('reorder_point')
        ->get()
        ->filter(fn (MasterItem $item) => $item->isBelowReorderPoint());

    expect($lowStockItems)->not->toBeEmpty();
});

test('siembra las dos bodegas de ejemplo activas', function () {
    $this->seed(KardexDemoSeeder::class);

    expect(Warehouse::where('is_active', true)->count())->toBe(2);
});

test('siembra una bodega por encima de su capacidad máxima', function () {
    $this->seed(KardexDemoSeeder::class);

    $centro = Warehouse::where('name', 'Bodega Centro')->firstOrFail();

    expect($centro->max_capacity_units)->not->toBeNull()
        ->and($centro->isOverCapacity())->toBeTrue();
});

test('siembra conteos físicos de ejemplo, incluido uno con ajuste', function () {
    $this->seed(KardexDemoSeeder::class);

    expect(StockCount::count())->toBe(2)
        ->and(StockCount::where('difference', 0)->count())->toBe(1)
        ->and(StockCount::where('difference', '<', 0)->count())->toBe(1);
});
