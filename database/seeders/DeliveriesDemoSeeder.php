<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Family;
use App\Models\GeographicZone;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Hogares beneficiarios de ejemplo (como si vinieran del censo, Módulo 7) y su
 * historial de entregas (Módulo 6), para que "Entregas y Seguimiento" y la
 * proyección de agotamiento del Kardex tengan datos reales con qué mostrarse.
 * Debe correr después de GeographicZoneSeeder y KardexDemoSeeder (usa sus
 * zonas, bodegas, catálogo y usuario operador).
 */
class DeliveriesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $operator = User::where('email', 'operador@donaciones-rolda.test')->firstOrFail();
        $centro = Warehouse::where('name', 'Bodega Centro')->firstOrFail();
        $guayabal = Warehouse::where('name', 'Bodega Guayabal')->firstOrFail();

        $perez = $this->seedFamily('Yolanda Pérez', 'Barrio Centro', 'urbana', 4);
        $gomez = $this->seedFamily('Carlos Gómez', 'Barrio El Salado', 'urbana', 3);
        $rodriguez = $this->seedFamily('Ana Rodríguez', 'Vereda La Osa', 'rural', 5);

        $arroz = MasterItem::where('name', 'Arroz')->firstOrFail();
        $acetaminofen = MasterItem::where('name', 'Acetaminofén 500mg')->firstOrFail();
        $sueroOral = MasterItem::where('name', 'Suero oral')->firstOrFail();

        $arrozCentro = StockEntry::where('master_item_id', $arroz->id)->where('warehouse_id', $centro->id)->firstOrFail();
        $acetaminofenCentro = StockEntry::where('master_item_id', $acetaminofen->id)->where('warehouse_id', $centro->id)->firstOrFail();
        $sueroGuayabal = StockEntry::where('master_item_id', $sueroOral->id)->where('warehouse_id', $guayabal->id)->firstOrFail();

        // Varias entregas a la misma familia, para poder ver el aviso de
        // "entregas recientes a este hogar" al registrar una nueva.
        foreach ([28, 21, 14, 7] as $daysAgo) {
            $this->seedDelivery($arrozCentro, $centro, $perez, $operator, 3, $daysAgo);
        }

        foreach ([20, 10, 3] as $daysAgo) {
            $this->seedDelivery($acetaminofenCentro, $centro, $gomez, $operator, 2, $daysAgo);
        }

        foreach ([15, 4] as $daysAgo) {
            $this->seedDelivery($sueroGuayabal, $guayabal, $rodriguez, $operator, 5, $daysAgo);
        }

        // Ítem dedicado a demostrar la proyección de agotamiento: pocas
        // existencias + consumo reciente sostenido -> se agota en pocos días.
        $this->seedProjectedStockout($centro, $perez, $gomez, $operator);
    }

    private function seedFamily(string $headFullName, string $zoneName, string $zoneType, int $householdSize): Family
    {
        $zone = GeographicZone::where('name', $zoneName)->first();

        return Family::create([
            'zone_id' => $zone?->id,
            'zone_type' => $zoneType,
            'neighborhood' => $zoneName,
            'address' => "Dirección de referencia, {$zoneName}",
            'head_full_name' => $headFullName,
            'housing_damage_level' => 'averiada_habitable',
            'household_size' => $householdSize,
        ]);
    }

    private function seedDelivery(StockEntry $entry, Warehouse $warehouse, Family $family, User $operator, int $quantity, int $daysAgo): void
    {
        StockExit::create([
            'stock_entry_id' => $entry->id,
            'warehouse_id' => $warehouse->id,
            'family_id' => $family->id,
            'released_by_user_id' => $operator->id,
            'received_by_name' => $family->head_full_name,
            'exit_reason' => 'emergency_assistance',
            'quantity_released' => $quantity,
            'release_date' => now()->subDays($daysAgo),
        ]);
    }

    private function seedProjectedStockout(Warehouse $centro, Family $perez, Family $gomez, User $operator): void
    {
        $category = Category::where('name', 'Alimentos')->firstOrFail();

        $frijol = MasterItem::create([
            'category_id' => $category->id,
            'name' => 'Frijol',
            'unit_of_measure' => 'kg',
        ]);

        $entry = StockEntry::create([
            'master_item_id' => $frijol->id,
            'warehouse_id' => $centro->id,
            'registered_by_user_id' => $operator->id,
            'confirmed_by_user_id' => $operator->id,
            'quantity' => 18,
            'lot_number' => 'L-2608',
            'expiry_date' => now()->addMonths(9),
            'received_date' => now()->subDays(25)->toDateString(),
        ]);

        foreach ([[$perez, 20, 8], [$gomez, 12, 7]] as [$family, $daysAgo, $quantity]) {
            $this->seedDelivery($entry, $centro, $family, $operator, $quantity, $daysAgo);
        }
    }
}
