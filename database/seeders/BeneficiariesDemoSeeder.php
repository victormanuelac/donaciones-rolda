<?php

namespace Database\Seeders;

use App\Models\Beneficiary;
use App\Models\Category;
use App\Models\Family;
use App\Models\MasterItem;
use App\Models\ProtocolRecommendation;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Beneficiaries\BeneficiaryRecommendationService;
use App\Services\Beneficiaries\VulnerabilityScoringService;
use Illuminate\Database\Seeder;

/**
 * Perfil de vulnerabilidad (Módulo 7, Fase 2) de ejemplo: reutiliza los
 * hogares ya sembrados por DeliveriesDemoSeeder, agrega dos ítems médicos e
 * ilustra el ciclo completo perfil -> puntaje -> recomendación. Los 3
 * protocolos son los ejemplos de docs/11-Modulo-7-...md — no es una
 * librería médica curada, solo datos de demostración (ver CLAUDE.md).
 */
class BeneficiariesDemoSeeder extends Seeder
{
    public function run(): void
    {
        $operator = User::where('email', 'operador@donaciones-rolda.test')->firstOrFail();
        $centro = Warehouse::where('name', 'Bodega Centro')->firstOrFail();

        [$prenatalIron, $metformin] = $this->seedMedicalItems($centro, $operator);
        $lechePolvo = MasterItem::where('name', 'Leche en polvo')->firstOrFail();

        $this->seedProtocols($lechePolvo, $prenatalIron, $metformin);

        $perez = Family::where('head_full_name', 'Yolanda Pérez')->firstOrFail();
        $gomez = Family::where('head_full_name', 'Carlos Gómez')->firstOrFail();

        // Roldanillo, para que el snapshot de stock de la recomendación
        // pueda calcular una distancia real hasta la bodega.
        $perez->update(['latitude' => 4.4144, 'longitude' => -76.1536]);
        $gomez->update(['latitude' => 4.4200, 'longitude' => -76.1600]);

        $pregnant = Beneficiary::create([
            'family_id' => $perez->id,
            'full_name' => 'Yolanda Pérez',
            'relationship_to_head' => 'Jefa de hogar',
            'sex' => 'mujer',
            'birthdate' => now()->subYears(28)->toDateString(),
            'is_household_head' => true,
            'is_pregnant' => true,
            'pregnancy_trimester' => 7,
            'is_single_parent' => true,
            'employment_status' => 'unemployed',
        ]);

        $withDiabetes = Beneficiary::create([
            'family_id' => $gomez->id,
            'full_name' => 'Carlos Gómez',
            'relationship_to_head' => 'Jefe de hogar',
            'sex' => 'hombre',
            'birthdate' => now()->subYears(58)->toDateString(),
            'is_household_head' => true,
            'chronic_conditions' => ['Diabetes'],
            'employment_status' => 'unemployed',
        ]);

        $this->scoreAndRecommend($pregnant, $operator);
        $this->scoreAndRecommend($withDiabetes, $operator);
    }

    /**
     * @return array{0: MasterItem, 1: MasterItem}
     */
    private function seedMedicalItems(Warehouse $centro, User $operator): array
    {
        $medicinas = Category::where('name', 'Medicinas')->firstOrFail();

        $prenatalIron = MasterItem::create([
            'category_id' => $medicinas->id,
            'name' => 'Hierro y Ácido Fólico (prenatal)',
            'unit_of_measure' => 'frascos',
        ]);

        $metformin = MasterItem::create([
            'category_id' => $medicinas->id,
            'name' => 'Metformina 500mg',
            'unit_of_measure' => 'cajas',
        ]);

        foreach ([$prenatalIron, $metformin] as $item) {
            StockEntry::create([
                'master_item_id' => $item->id,
                'warehouse_id' => $centro->id,
                'registered_by_user_id' => $operator->id,
                'confirmed_by_user_id' => $operator->id,
                'quantity' => 15,
                'expiry_date' => now()->addMonths(12),
                'received_date' => now()->subDays(5)->toDateString(),
            ]);
        }

        return [$prenatalIron, $metformin];
    }

    private function seedProtocols(MasterItem $lechePolvo, MasterItem $prenatalIron, MasterItem $metformin): void
    {
        ProtocolRecommendation::create([
            'protocol_name' => 'Suplementación Menores de 5 años - ICBF',
            'source' => 'icbf',
            'trigger_condition' => ['age_min' => 0, 'age_max' => 5],
            'recommended_items' => [
                ['item_id' => $lechePolvo->id, 'quantity' => 1, 'frequency' => 'daily', 'duration_days' => 30],
            ],
            'confidence_level' => 0.95,
        ]);

        ProtocolRecommendation::create([
            'protocol_name' => 'Embarazo Saludable - OMS',
            'source' => 'who',
            'trigger_condition' => ['pregnancy' => true],
            'recommended_items' => [
                ['item_id' => $prenatalIron->id, 'quantity' => 1, 'frequency' => 'daily', 'duration_days' => 270],
            ],
            'confidence_level' => 0.98,
        ]);

        ProtocolRecommendation::create([
            'protocol_name' => 'Manejo de Diabetes - Salud Local',
            'source' => 'local_health',
            'trigger_condition' => ['chronic_diseases' => ['Diabetes']],
            'recommended_items' => [
                ['item_id' => $metformin->id, 'quantity' => 1, 'frequency' => 'daily', 'duration_days' => null],
            ],
            'confidence_level' => 0.85,
            'requires_medical_approval' => true,
        ]);
    }

    private function scoreAndRecommend(Beneficiary $beneficiary, User $operator): void
    {
        $result = app(VulnerabilityScoringService::class)->calculate($beneficiary);

        $beneficiary->update([
            'vulnerability_score' => $result['total'],
            'priority_level' => $result['priority_level'],
            'last_score_update' => now(),
        ]);

        app(BeneficiaryRecommendationService::class)->generateFor($beneficiary->fresh(), $operator);
    }
}
