<?php

use App\Enums\BeneficiaryRecommendationStatus;
use App\Models\Beneficiary;
use App\Services\Beneficiaries\BeneficiaryRecommendationService;
use App\Services\Beneficiaries\VulnerabilityScoringService;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Perfil de vulnerabilidad')] class extends Component {
    public Beneficiary $beneficiary;

    public string $chronicConditions = '';

    public string $currentSymptoms = '';

    public ?string $lastMedicalReview = null;

    public ?string $medicalNotes = null;

    // Flux checkbox envía null (no false) cuando queda desmarcado, así que
    // estas 4 quedan nullable y se normalizan a bool al leerlas/guardarlas.
    public ?bool $isPregnant = false;

    public ?int $pregnancyTrimester = null;

    public ?bool $hasDisability = false;

    public ?string $disabilityType = null;

    public ?bool $isSingleParent = false;

    public ?bool $hasNoHome = false;

    public ?string $employmentStatus = null;

    public ?string $educationalLevel = null;

    /** @var array{demographic: int, health: int, nutritional: int, social: int, total: int, priority_level: \App\Enums\BeneficiaryPriorityLevel}|null */
    public ?array $scoreResult = null;

    /** @var array<int, \App\Models\BeneficiaryRecommendation> */
    public array $recommendations = [];

    public function mount(Beneficiary $beneficiary): void
    {
        $this->authorize('manageProfile', Beneficiary::class);

        $this->beneficiary = $beneficiary;
        $this->chronicConditions = implode(', ', $beneficiary->chronic_conditions ?? []);
        $this->currentSymptoms = implode(', ', $beneficiary->current_symptoms ?? []);
        $this->lastMedicalReview = $beneficiary->last_medical_review?->toDateString();
        $this->medicalNotes = $beneficiary->medical_notes;
        $this->isPregnant = $beneficiary->is_pregnant;
        $this->pregnancyTrimester = $beneficiary->pregnancy_trimester;
        $this->hasDisability = $beneficiary->has_disability;
        $this->disabilityType = $beneficiary->disability_type;
        $this->isSingleParent = $beneficiary->is_single_parent;
        $this->hasNoHome = $beneficiary->has_no_home;
        $this->employmentStatus = $beneficiary->employment_status;
        $this->educationalLevel = $beneficiary->educational_level;

        if ($beneficiary->hasProfile()) {
            $this->loadRecommendations();
        }
    }

    public function save(VulnerabilityScoringService $scoring, BeneficiaryRecommendationService $recommender): void
    {
        $this->authorize('manageProfile', Beneficiary::class);

        $data = $this->validate([
            'lastMedicalReview' => ['nullable', 'date'],
            'medicalNotes' => ['nullable', 'string', 'max:2000'],
            'pregnancyTrimester' => ['nullable', 'integer', 'min:1', 'max:9'],
            'disabilityType' => ['nullable', 'string', 'max:100'],
            'employmentStatus' => ['nullable', 'in:employed,unemployed,student,retired'],
            'educationalLevel' => ['nullable', 'in:none,primary,secondary,tertiary'],
        ]);

        $this->beneficiary->update([
            'chronic_conditions' => $this->splitList($this->chronicConditions),
            'current_symptoms' => $this->splitList($this->currentSymptoms),
            'last_medical_review' => $data['lastMedicalReview'],
            'medical_notes' => $data['medicalNotes'],
            'is_pregnant' => (bool) $this->isPregnant,
            'pregnancy_trimester' => $this->isPregnant ? $data['pregnancyTrimester'] : null,
            'has_disability' => (bool) $this->hasDisability,
            'disability_type' => $this->hasDisability ? $data['disabilityType'] : null,
            'is_single_parent' => (bool) $this->isSingleParent,
            'has_no_home' => (bool) $this->hasNoHome,
            'employment_status' => $data['employmentStatus'],
            'educational_level' => $data['educationalLevel'],
        ]);

        $this->beneficiary->refresh();

        $result = $scoring->calculate($this->beneficiary);

        $this->beneficiary->update([
            'vulnerability_score' => $result['total'],
            'priority_level' => $result['priority_level'],
            'last_score_update' => now(),
        ]);

        $recommender->generateFor($this->beneficiary->fresh(), auth()->user());
        $this->loadRecommendations();

        $this->scoreResult = $result;

        Flux::toast(variant: 'success', text: __('Perfil guardado. Puntaje: :score (:priority).', ['score' => $result['total'], 'priority' => $result['priority_level']->label()]));
    }

    private function loadRecommendations(): void
    {
        $this->recommendations = $this->beneficiary->recommendations()
            ->where('status', BeneficiaryRecommendationStatus::Pending)
            ->with(['masterItem', 'protocol'])
            ->get()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function splitList(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}; ?>

<section class="w-full max-w-3xl">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <flux:heading size="xl">{{ __('Perfil de vulnerabilidad') }}</flux:heading>
            <flux:subheading>{{ $beneficiary->full_name }} — {{ $beneficiary->family->head_full_name }}</flux:subheading>
        </div>
        <flux:button :href="route('beneficiaries.show', $beneficiary->family)" wire:navigate>{{ __('Volver al hogar') }}</flux:button>
    </div>

    <div class="card-brutal p-6 md:p-8 space-y-4">
        <flux:input wire:model="chronicConditions" :label="__('Condiciones crónicas')" :description="__('Sepáralas por coma. Ej. Diabetes, Hipertensión')" />
        <flux:input wire:model="currentSymptoms" :label="__('Síntomas actuales')" :description="__('Sepáralos por coma. Ej. Fiebre, Tos')" />
        <flux:input wire:model="lastMedicalReview" :label="__('Última revisión médica (opcional)')" type="date" />
        <flux:textarea wire:model="medicalNotes" :label="__('Notas médicas (opcional)')" />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
                <flux:checkbox wire:model.live="isPregnant" :label="__('Está embarazada')" />
                @if ($isPregnant)
                    <flux:input wire:model="pregnancyTrimester" :label="__('Trimestre de embarazo')" type="number" min="1" max="9" />
                @endif
            </div>

            <div class="space-y-2">
                <flux:checkbox wire:model.live="hasDisability" :label="__('Tiene una discapacidad')" />
                @if ($hasDisability)
                    <flux:input wire:model="disabilityType" :label="__('Tipo de discapacidad')" />
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:checkbox wire:model="isSingleParent" :label="__('Madre/padre soltero/a')" />
            <flux:checkbox wire:model="hasNoHome" :label="__('Sin hogar')" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>{{ __('Situación laboral (opcional)') }}</flux:label>
                <flux:select wire:model="employmentStatus">
                    <flux:select.option value="">{{ __('Sin definir') }}</flux:select.option>
                    <flux:select.option value="employed">{{ __('Empleado/a') }}</flux:select.option>
                    <flux:select.option value="unemployed">{{ __('Desempleado/a') }}</flux:select.option>
                    <flux:select.option value="student">{{ __('Estudiante') }}</flux:select.option>
                    <flux:select.option value="retired">{{ __('Jubilado/a') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nivel educativo (opcional)') }}</flux:label>
                <flux:select wire:model="educationalLevel">
                    <flux:select.option value="">{{ __('Sin definir') }}</flux:select.option>
                    <flux:select.option value="none">{{ __('Ninguno') }}</flux:select.option>
                    <flux:select.option value="primary">{{ __('Primaria') }}</flux:select.option>
                    <flux:select.option value="secondary">{{ __('Secundaria') }}</flux:select.option>
                    <flux:select.option value="tertiary">{{ __('Superior') }}</flux:select.option>
                </flux:select>
            </flux:field>
        </div>

        <div class="flex justify-end pt-2">
            <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                {{ __('Guardar perfil') }}
            </flux:button>
        </div>
    </div>

    @if ($scoreResult)
        <div class="card-brutal p-6 mt-6">
            <h2 class="font-display font-bold text-ink mb-3">{{ __('Resultado del puntaje') }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                <div>
                    <p class="text-xs text-muted">{{ __('Demográfico') }}</p>
                    <p class="text-ink font-bold">{{ $scoreResult['demographic'] }} / 30</p>
                </div>
                <div>
                    <p class="text-xs text-muted">{{ __('Salud') }}</p>
                    <p class="text-ink font-bold">{{ $scoreResult['health'] }} / 30</p>
                </div>
                <div>
                    <p class="text-xs text-muted">{{ __('Nutricional') }}</p>
                    <p class="text-ink font-bold">{{ $scoreResult['nutritional'] }} / 20</p>
                </div>
                <div>
                    <p class="text-xs text-muted">{{ __('Social') }}</p>
                    <p class="text-ink font-bold">{{ $scoreResult['social'] }} / 20</p>
                </div>
            </div>
            <flux:badge :color="match ($scoreResult['priority_level']->value) { 'critical' => 'red', 'priority' => 'amber', default => 'zinc' }" size="lg">
                {{ __('Total: :score / 100 — :priority', ['score' => $scoreResult['total'], 'priority' => $scoreResult['priority_level']->label()]) }}
            </flux:badge>
        </div>
    @endif

    @if (! empty($recommendations))
        <div class="card-brutal overflow-hidden mt-6">
            <div class="p-4 border-b-2 border-line">
                <h2 class="font-display font-bold text-ink">{{ __('Recomendaciones generadas') }}</h2>
            </div>
            <div class="divide-y divide-line">
                @foreach ($recommendations as $recommendation)
                    <div wire:key="recommendation-{{ $recommendation->id }}" class="p-4 flex items-start justify-between gap-4">
                        <div>
                            <p class="text-ink font-medium">{{ $recommendation->masterItem->name }}</p>
                            <p class="text-xs text-muted">
                                {{ __('Protocolo') }}: {{ $recommendation->protocol->protocol_name }}
                                ({{ __('confianza') }} {{ number_format((float) $recommendation->protocol->confidence_level * 100, 0) }}%)
                                @if ($recommendation->protocol->requires_medical_approval)
                                    · {{ __('requiere aprobación médica') }}
                                @endif
                            </p>
                            <p class="text-xs text-muted">{{ __('Cantidad') }}: {{ $recommendation->quantity_recommended }} ({{ $recommendation->frequency }})</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm text-ink font-bold">{{ $recommendation->available_stock }} {{ __('disponibles') }}</p>
                            @if (! empty($recommendation->available_warehouses))
                                <p class="text-xs text-muted">
                                    {{ $recommendation->available_warehouses[0]['name'] }}
                                    @if ($recommendation->available_warehouses[0]['distance_km'] !== null)
                                        ({{ $recommendation->available_warehouses[0]['distance_km'] }} km)
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
