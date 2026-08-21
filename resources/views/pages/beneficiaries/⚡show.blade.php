<?php

use App\Models\Beneficiary;
use App\Models\Family;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Hogar — Beneficiarios')] class extends Component {
    public Family $family;

    public bool $showAddMemberModal = false;

    public string $memberFullName = '';

    public ?string $memberRelationship = null;

    public ?string $memberSex = null;

    public ?string $memberBirthdate = null;

    public function mount(Family $family): void
    {
        $this->authorize('view', Beneficiary::class);

        $this->family = $family;
    }

    #[Computed]
    public function censusEntry()
    {
        return $this->family->censusEntries()->latest('surveyed_at')->first();
    }

    #[Computed]
    public function beneficiaries()
    {
        return $this->family->beneficiaries()->orderByDesc('is_household_head')->orderBy('full_name')->get();
    }

    public function openAddMemberModal(): void
    {
        $this->authorize('manageProfile', Beneficiary::class);

        $this->reset(['memberFullName', 'memberRelationship', 'memberSex', 'memberBirthdate']);
        $this->resetValidation();
        $this->showAddMemberModal = true;
    }

    public function addMember(): void
    {
        $this->authorize('manageProfile', Beneficiary::class);

        $data = $this->validate([
            'memberFullName' => ['required', 'string', 'max:150'],
            'memberRelationship' => ['nullable', 'string', 'max:60'],
            'memberSex' => ['nullable', 'string', 'max:20'],
            'memberBirthdate' => ['nullable', 'date'],
        ]);

        $this->family->beneficiaries()->create([
            'full_name' => $data['memberFullName'],
            'relationship_to_head' => $data['memberRelationship'],
            'sex' => $data['memberSex'],
            'birthdate' => $data['memberBirthdate'],
        ]);

        $this->showAddMemberModal = false;
        unset($this->beneficiaries);

        Flux::toast(variant: 'success', text: __('Integrante agregado.'));
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <flux:heading size="xl">{{ $family->head_full_name }}</flux:heading>
            <flux:subheading>{{ $family->neighborhood ?? $family->address }} — {{ __('Hogar de :size personas', ['size' => $family->household_size]) }}</flux:subheading>
        </div>
        <flux:button :href="route('beneficiaries.index')" wire:navigate>{{ __('Volver al listado') }}</flux:button>
    </div>

    @if ($this->censusEntry)
        <div class="card-brutal p-5 mb-6">
            <h2 class="font-display font-bold text-ink mb-3">{{ __('Censo de Fase 1') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-3">
                <div>
                    <p class="text-xs text-muted">{{ __('Puntaje de vulnerabilidad') }}</p>
                    <p class="text-ink font-bold">{{ $this->censusEntry->vulnerability_score }} / 100</p>
                </div>
                <div>
                    <p class="text-xs text-muted">{{ __('Prioridad') }}</p>
                    <flux:badge :color="match ($this->censusEntry->priority_level->value) { 'critico' => 'red', 'alto' => 'amber', 'medio' => 'yellow', default => 'zinc' }">
                        {{ $this->censusEntry->priority_level->label() }}
                    </flux:badge>
                </div>
                <div>
                    <p class="text-xs text-muted">{{ __('Encuestado') }}</p>
                    <p class="text-ink">{{ $this->censusEntry->surveyed_at?->format('d/m/Y') }}</p>
                </div>
            </div>

            @if (! empty($this->censusEntry->red_flags))
                <flux:callout variant="danger" icon="exclamation-triangle" class="mb-3">
                    <flux:callout.text>{{ implode(', ', $this->censusEntry->red_flags) }}</flux:callout.text>
                </flux:callout>
            @endif

            @if (! empty($this->censusEntry->priority_needs))
                <p class="text-sm text-muted">
                    <strong class="text-ink">{{ __('Necesidades prioritarias') }}:</strong> {{ implode(', ', $this->censusEntry->priority_needs) }}
                </p>
            @endif
        </div>
    @else
        <flux:callout variant="secondary" class="mb-6">{{ __('Este hogar todavía no tiene un censo de Fase 1 registrado.') }}</flux:callout>
    @endif

    <div class="flex items-center justify-between mb-3">
        <h2 class="font-display font-bold text-ink">{{ __('Integrantes del hogar') }}</h2>
        <flux:button size="sm" icon="plus" wire:click="openAddMemberModal">{{ __('Agregar integrante') }}</flux:button>
    </div>

    <div class="card-brutal overflow-hidden">
        @if ($this->beneficiaries->isEmpty())
            <div class="p-10 text-center">
                <p class="font-display text-lg font-bold text-ink">{{ __('Sin integrantes registrados') }}</p>
                <p class="text-muted text-sm mt-1">{{ __('Agrega al menos uno para poder completar su perfil de vulnerabilidad.') }}</p>
            </div>
        @else
            <div class="divide-y divide-line">
                @foreach ($this->beneficiaries as $member)
                    <div wire:key="member-{{ $member->id }}" class="p-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-ink font-medium">
                                {{ $member->full_name }}
                                @if ($member->is_household_head)
                                    <flux:badge size="sm" color="zinc">{{ __('Jefe de hogar') }}</flux:badge>
                                @endif
                            </p>
                            <p class="text-xs text-muted">
                                {{ $member->relationship_to_head ?? '—' }}
                                @if ($member->age() !== null) — {{ __(':age años', ['age' => $member->age()]) }} @endif
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            @if ($member->hasProfile())
                                <flux:badge :color="match ($member->priority_level->value) { 'critical' => 'red', 'priority' => 'amber', default => 'zinc' }">
                                    {{ $member->priority_level->label() }} · {{ $member->vulnerability_score }}
                                </flux:badge>
                            @else
                                <flux:badge color="zinc">{{ __('Sin perfil') }}</flux:badge>
                            @endif
                            <flux:button size="sm" class="mt-2" :href="route('beneficiaries.profile', $member)" wire:navigate>
                                {{ $member->hasProfile() ? __('Editar perfil') : __('Completar perfil') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <flux:modal wire:model="showAddMemberModal" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Agregar integrante') }}</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="memberFullName" :label="__('Nombre completo')" />
                <flux:input wire:model="memberRelationship" :label="__('Parentesco con el jefe de hogar (opcional)')" />
                <flux:input wire:model="memberSex" :label="__('Sexo (opcional)')" />
                <flux:input wire:model="memberBirthdate" :label="__('Fecha de nacimiento (opcional)')" type="date" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('showAddMemberModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="addMember">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
