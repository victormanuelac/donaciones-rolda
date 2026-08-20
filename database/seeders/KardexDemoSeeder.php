<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\GeographicZone;
use App\Models\MasterItem;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Datos base para que los formularios de Kardex (entradas/salidas) tengan
 * categorías, ítems y bodegas reales con las que probar, en vez de listas vacías.
 */
class KardexDemoSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Medicinas' => ['icon_class' => 'pill', 'items' => [
                ['name' => 'Acetaminofén 500mg', 'unit_of_measure' => 'cajas'],
                ['name' => 'Suero oral', 'unit_of_measure' => 'sobres'],
            ]],
            'Alimentos' => ['icon_class' => 'wheat', 'items' => [
                ['name' => 'Leche en polvo', 'unit_of_measure' => 'bolsas'],
                ['name' => 'Arroz', 'unit_of_measure' => 'kg'],
            ]],
            'Insumos Médicos' => ['icon_class' => 'stethoscope', 'items' => [
                ['name' => 'Guantes de nitrilo', 'unit_of_measure' => 'cajas'],
            ]],
            'Herramientas' => ['icon_class' => 'wrench', 'items' => [
                ['name' => 'Linterna de mano', 'unit_of_measure' => 'unidades'],
            ]],
            'Artículos de Higiene' => ['icon_class' => 'droplet', 'items' => [
                ['name' => 'Kit de aseo personal', 'unit_of_measure' => 'kits'],
            ]],
        ];

        foreach ($categories as $name => $data) {
            $category = Category::create([
                'name' => $name,
                'icon_class' => $data['icon_class'],
            ]);

            foreach ($data['items'] as $item) {
                MasterItem::create([
                    'category_id' => $category->id,
                    'name' => $item['name'],
                    'unit_of_measure' => $item['unit_of_measure'],
                ]);
            }
        }

        $barrioCentro = GeographicZone::where('name', 'Barrio Centro')->first();
        $guayabal = GeographicZone::where('name', 'Corregimiento Guayabal')->first();

        Warehouse::create([
            'geographic_zone_id' => $barrioCentro?->id,
            'name' => 'Bodega Centro',
            'address' => 'Calle 10 #5-20, Roldanillo',
            'contact_person_name' => 'Coordinador de Bodega Centro',
            'contact_phone' => '3120000001',
        ]);

        Warehouse::create([
            'geographic_zone_id' => $guayabal?->id,
            'name' => 'Bodega Guayabal',
            'address' => 'Vía Guayabal km 3, Roldanillo',
            'contact_person_name' => 'Coordinador de Bodega Guayabal',
            'contact_phone' => '3120000002',
        ]);
    }
}
