<?php

namespace Database\Seeders;

use App\Models\GeographicZone;
use Illuminate\Database\Seeder;

class GeographicZoneSeeder extends Seeder
{
    /**
     * Jerarquía de ejemplo documentada en docs/02-Modelo-Datos-MER-DDL.md.
     */
    public function run(): void
    {
        $municipality = GeographicZone::create([
            'zone_type' => 'municipio',
            'name' => 'Roldanillo',
        ]);

        $urbanZone = GeographicZone::create([
            'zone_type' => 'comuna',
            'name' => 'Zona Urbana',
            'parent_zone_id' => $municipality->id,
        ]);

        GeographicZone::create([
            'zone_type' => 'barrio',
            'name' => 'Barrio Centro',
            'parent_zone_id' => $urbanZone->id,
        ]);

        GeographicZone::create([
            'zone_type' => 'barrio',
            'name' => 'Barrio El Salado',
            'parent_zone_id' => $urbanZone->id,
        ]);

        $corregimiento = GeographicZone::create([
            'zone_type' => 'corregimiento',
            'name' => 'Corregimiento Guayabal',
            'parent_zone_id' => $municipality->id,
        ]);

        GeographicZone::create([
            'zone_type' => 'vereda',
            'name' => 'Vereda La Osa',
            'parent_zone_id' => $corregimiento->id,
        ]);
    }
}
