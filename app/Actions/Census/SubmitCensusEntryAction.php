<?php

declare(strict_types=1);

namespace App\Actions\Census;

use App\Models\CensusEntry;
use App\Models\Family;
use App\Models\User;
use App\Services\CensusScoring\VulnerabilityIndexService;
use Illuminate\Support\Facades\DB;

class SubmitCensusEntryAction
{
    public function __construct(private VulnerabilityIndexService $scoring)
    {
        //
    }

    /**
     * Crea el hogar, la captura del censo y sus integrantes a partir de un payload
     * validado. Idempotente por client_uuid: si la captura ya fue sincronizada
     * (reintento desde la cola offline), retorna el registro existente sin duplicar.
     *
     * @param  array{client_uuid?: string|null, family: array<string, mixed>, census_entry: array<string, mixed>, members?: array<int, array<string, mixed>>}  $payload
     */
    public function handle(array $payload, User $surveyor): CensusEntry
    {
        $clientUuid = $payload['client_uuid'] ?? null;

        if ($clientUuid !== null) {
            $existing = CensusEntry::where('client_uuid', $clientUuid)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($payload, $surveyor, $clientUuid) {
            // El total de personas del hogar solo se pregunta una vez, en el módulo de
            // composición del censo (census_entry.total_people) — se refleja en families
            // sin volver a pedirlo en el paso de vivienda para no duplicar la pregunta.
            $family = Family::create([
                ...$payload['family'],
                'household_size' => $payload['census_entry']['total_people'],
            ]);

            $scoring = $this->scoring->calculate([...$payload['family'], ...$payload['census_entry']]);

            $censusEntry = $family->censusEntries()->create([
                ...$payload['census_entry'],
                'user_id' => $surveyor->id,
                'client_uuid' => $clientUuid,
                'form_code' => (string) str()->uuid(),
                'vulnerability_score' => $scoring['score'],
                'priority_level' => $scoring['priority_level'],
                'red_flags' => $scoring['red_flags'],
            ]);

            $censusEntry->update([
                'form_code' => sprintf('ROL-2026-%05d', $censusEntry->id),
            ]);

            foreach ($payload['members'] ?? [] as $member) {
                $family->beneficiaries()->create([
                    ...$member,
                    'census_entry_id' => $censusEntry->id,
                ]);
            }

            return $censusEntry;
        });
    }
}
