<?php

namespace Database\Seeders;

use App\Actions\Kardex\RegisterStockCountAction;
use App\Models\Category;
use App\Models\GeographicZone;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * Datos base y de movimiento para que el Kardex (entradas/salidas, semáforo,
 * alertas de vencimiento, stock mínimo) tenga algo real que mostrar en vez de
 * listas vacías. Credenciales del operador documentadas en README.md — solo
 * para entornos local/pruebas, igual que el admin de DatabaseSeeder.
 */
class KardexDemoSeeder extends Seeder
{
    public function run(): void
    {
        $items = $this->seedCatalog();
        [$centro, $guayabal] = $this->seedWarehouses();
        $operator = $this->seedOperator($centro, $guayabal);

        $entries = $this->seedStockEntries($items, $centro, $guayabal, $operator);
        $this->seedCounts($entries, $operator);

        // Genera automáticamente las alertas de vencimiento de los lotes que
        // sembramos con fecha próxima, y corrige estados (expired/withdrawn)
        // con la misma lógica que corre a diario en producción.
        Artisan::call('kardex:update-stock-entry-statuses');
    }

    /**
     * @return array<string, MasterItem>
     */
    private function seedCatalog(): array
    {
        $catalog = [
            'Medicinas' => ['icon_class' => 'pill', 'items' => [
                ['name' => 'Acetaminofén 500mg', 'unit_of_measure' => 'cajas'],
                ['name' => 'Suero oral', 'unit_of_measure' => 'sobres'],
            ]],
            'Alimentos' => ['icon_class' => 'wheat', 'items' => [
                ['name' => 'Leche en polvo', 'unit_of_measure' => 'bolsas', 'reorder_point' => 10],
                ['name' => 'Arroz', 'unit_of_measure' => 'kg'],
            ]],
            'Insumos Médicos' => ['icon_class' => 'stethoscope', 'items' => [
                ['name' => 'Guantes de nitrilo', 'unit_of_measure' => 'cajas', 'reorder_point' => 30],
            ]],
            'Herramientas' => ['icon_class' => 'wrench', 'items' => [
                ['name' => 'Linterna de mano', 'unit_of_measure' => 'unidades'],
            ]],
            'Artículos de Higiene' => ['icon_class' => 'droplet', 'items' => [
                ['name' => 'Kit de aseo personal', 'unit_of_measure' => 'kits'],
            ]],
        ];

        $items = [];

        foreach ($catalog as $name => $data) {
            $category = Category::create([
                'name' => $name,
                'icon_class' => $data['icon_class'],
            ]);

            foreach ($data['items'] as $item) {
                $items[$item['name']] = MasterItem::create([
                    'category_id' => $category->id,
                    'name' => $item['name'],
                    'unit_of_measure' => $item['unit_of_measure'],
                    'reorder_point' => $item['reorder_point'] ?? null,
                ]);
            }
        }

        return $items;
    }

    /**
     * @return array{0: Warehouse, 1: Warehouse}
     */
    private function seedWarehouses(): array
    {
        $barrioCentro = GeographicZone::where('name', 'Barrio Centro')->first();
        $guayabalZone = GeographicZone::where('name', 'Corregimiento Guayabal')->first();

        $centro = Warehouse::create([
            'geographic_zone_id' => $barrioCentro?->id,
            'name' => 'Bodega Centro',
            'address' => 'Calle 10 #5-20, Roldanillo',
            'contact_person_name' => 'Coordinador de Bodega Centro',
            'contact_phone' => '3120000001',
            'max_capacity_units' => 130, // deliberadamente por debajo del total sembrado (144), para demostrar la alerta de sobrecupo
        ]);

        $guayabal = Warehouse::create([
            'geographic_zone_id' => $guayabalZone?->id,
            'name' => 'Bodega Guayabal',
            'address' => 'Vía Guayabal km 3, Roldanillo',
            'contact_person_name' => 'Coordinador de Bodega Guayabal',
            'contact_phone' => '3120000002',
        ]);

        return [$centro, $guayabal];
    }

    private function seedOperator(Warehouse $centro, Warehouse $guayabal): User
    {
        $operator = User::factory()->create([
            'name' => 'Operador Donaciones Rolda',
            'email' => 'operador@donaciones-rolda.test',
            'password' => Hash::make('OperadorRolda#2026'),
        ]);

        WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $centro->id]);
        WarehouseAssignment::create(['user_id' => $operator->id, 'warehouse_id' => $guayabal->id]);

        return $operator;
    }

    /**
     * @param  array<string, MasterItem>  $items
     * @return array<string, StockEntry>
     */
    private function seedStockEntries(array $items, Warehouse $centro, Warehouse $guayabal, User $operator): array
    {
        // [bodega, ítem, cantidad, fecha de vencimiento (null = sin vencimiento próximo), lote]
        $entries = [
            [$centro, 'Acetaminofén 500mg', 40, now()->addMonths(18), 'L-2601'],       // 🟢 alta
            [$centro, 'Suero oral', 15, now()->addMonths(12), 'L-2602'],               // 🟡 media
            [$centro, 'Leche en polvo', 4, now()->addMonths(8), 'L-2603'],             // 🔴 baja + bajo reorden
            [$centro, 'Arroz', 60, now()->addMonths(10), 'L-2604'],                    // 🟢 alta
            [$centro, 'Guantes de nitrilo', 22, now()->addMonths(24), 'L-2605'],       // 🟢 alta, pero bajo reorden (30 entre ambas bodegas)
            [$centro, 'Linterna de mano', 8, null, 'L-2606'],                          // 🟡 media
            [$centro, 'Kit de aseo personal', 2, now()->addDays(7), 'L-2607'],         // 🔴 baja + vence en 7 días

            [$guayabal, 'Acetaminofén 500mg', 10, now()->addMonths(18), 'L-2701'],     // 🟡 media
            [$guayabal, 'Suero oral', 50, now()->addDays(5), 'L-2702'],                // 🟢 alta + vence en 5 días
            [$guayabal, 'Arroz', 3, now()->addMonths(10), 'L-2703'],                   // 🔴 baja (se agota más abajo)
            [$guayabal, 'Guantes de nitrilo', 7, now()->addMonths(24), 'L-2704'],      // 🟡 media
            [$guayabal, 'Linterna de mano', 22, null, 'L-2705'],                       // 🟢 alta
            [$guayabal, 'Kit de aseo personal', 18, now()->addMonths(9), 'L-2706'],    // 🟡 media
        ];

        /** @var array<string, StockEntry> $created */
        $created = [];

        foreach ($entries as [$warehouse, $itemName, $quantity, $expiryDate, $lot]) {
            $created["{$warehouse->name}-{$itemName}"] = StockEntry::create([
                'master_item_id' => $items[$itemName]->id,
                'warehouse_id' => $warehouse->id,
                'registered_by_user_id' => $operator->id,
                'confirmed_by_user_id' => $operator->id,
                'quantity' => $quantity,
                'lot_number' => $lot,
                'expiry_date' => $expiryDate,
                'received_date' => now()->subDays(random_int(1, 20))->toDateString(),
            ]);
        }

        // Historial de movimiento: un par de salidas normales...
        StockExit::create([
            'stock_entry_id' => $created['Bodega Centro-Acetaminofén 500mg']->id,
            'warehouse_id' => $centro->id,
            'released_by_user_id' => $operator->id,
            'exit_reason' => 'donation',
            'quantity_released' => 5,
            'received_by_name' => 'Familia Pérez',
            'release_date' => now()->subDays(3),
        ]);

        StockExit::create([
            'stock_entry_id' => $created['Bodega Centro-Suero oral']->id,
            'warehouse_id' => $centro->id,
            'released_by_user_id' => $operator->id,
            'exit_reason' => 'emergency_assistance',
            'quantity_released' => 2,
            'destination_description' => 'Albergue temporal Coliseo Municipal',
            'release_date' => now()->subDay(),
        ]);

        // ...y una que agota el lote por completo, para demostrar la transición a "retirado".
        StockExit::create([
            'stock_entry_id' => $created['Bodega Guayabal-Arroz']->id,
            'warehouse_id' => $guayabal->id,
            'released_by_user_id' => $operator->id,
            'exit_reason' => 'donation',
            'quantity_released' => 3,
            'received_by_name' => 'Comedor comunitario Guayabal',
            'release_date' => now()->subHours(6),
        ]);

        return $created;
    }

    /**
     * Conteos físicos de ejemplo (Kardex — cycle counting): uno sin
     * diferencia (queda registrado, sin mover inventario) y uno con faltante
     * (genera automáticamente la salida de ajuste vía RegisterStockCountAction),
     * para que la ficha Kardex y el conteo tengan datos reales que mostrar.
     *
     * @param  array<string, StockEntry>  $entries
     */
    private function seedCounts(array $entries, User $operator): void
    {
        $action = new RegisterStockCountAction;

        $action->handle([
            'stock_entry_id' => $entries['Bodega Centro-Arroz']->id,
            'counted_quantity' => $entries['Bodega Centro-Arroz']->availableQuantity(),
        ], $operator);

        $linterna = $entries['Bodega Centro-Linterna de mano'];
        $action->handle([
            'stock_entry_id' => $linterna->id,
            'counted_quantity' => $linterna->availableQuantity() - 1,
            'notes' => 'Faltante detectado en conteo mensual de bodega.',
        ], $operator);
    }
}
