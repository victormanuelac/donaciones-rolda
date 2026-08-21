<?php

use App\Enums\CensusPriorityLevel;
use App\Models\Family;
use App\Models\GeographicZone;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Beneficiarios')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $zoneFilter = null;

    #[Url]
    public ?string $priorityFilter = null;

    public function mount(): void
    {
        $this->authorize('viewAny', \App\Models\Beneficiary::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'zoneFilter', 'priorityFilter'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function zones()
    {
        return GeographicZone::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function priorities()
    {
        return CensusPriorityLevel::cases();
    }

    #[Computed]
    public function families()
    {
        $search = trim($this->search);

        return Family::query()
            ->with(['zone', 'censusEntries' => fn ($query) => $query->latest('surveyed_at')->limit(1), 'beneficiaries'])
            ->when($search !== '', fn ($query) => $query->where('head_full_name', 'like', '%'.$search.'%'))
            ->when($this->zoneFilter, fn ($query) => $query->where('zone_id', $this->zoneFilter))
            ->when($this->priorityFilter, fn ($query) => $query->whereHas('censusEntries', fn ($q) => $q->where('priority_level', $this->priorityFilter)))
            ->orderBy('head_full_name')
            ->paginate(20);
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Beneficiarios') }}</flux:heading>
        <flux:subheading>{{ __('Hogares censados — completa el perfil de vulnerabilidad de sus integrantes.') }}</flux:subheading>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <flux:input wire:model.live.debounce.400ms="search" :placeholder="__('Buscar por jefe de hogar...')" icon="magnifying-glass" class="max-w-xs" />

        @if ($this->zones->isNotEmpty())
            <flux:select wire:model.live="zoneFilter" class="max-w-xs">
                <flux:select.option value="">{{ __('Todas las zonas') }}</flux:select.option>
                @foreach ($this->zones as $zone)
                    <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="priorityFilter" class="max-w-xs">
            <flux:select.option value="">{{ __('Toda prioridad (Fase 1)') }}</flux:select.option>
            @foreach ($this->priorities as $priority)
                <flux:select.option value="{{ $priority->value }}">{{ $priority->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="card-brutal overflow-hidden">
        @if ($this->families->isEmpty())
            <div class="p-10 text-center">
                <p class="font-display text-lg font-bold text-ink">{{ __('Sin hogares registrados') }}</p>
                <p class="text-muted text-sm mt-1">{{ __('Los hogares aparecen aquí después de capturarse en el censo de Fase 1.') }}</p>
            </div>
        @else
            <table class="w-full">
                <thead>
                    <tr class="bg-surface-2 border-b-2 border-line">
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Jefe de hogar') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Zona') }}</th>
                        <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Personas') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Prioridad Fase 1') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Perfiles Fase 2') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->families as $family)
                        <tr wire:key="family-{{ $family->id }}" class="border-b border-line last:border-b-0">
                            <td class="px-4 py-3 text-ink">{{ $family->head_full_name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $family->zone?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-ink">{{ $family->household_size }}</td>
                            <td class="px-4 py-3">
                                @php $censusEntry = $family->censusEntries->first(); @endphp
                                @if ($censusEntry)
                                    <flux:badge :color="match ($censusEntry->priority_level->value) { 'critico' => 'red', 'alto' => 'amber', 'medio' => 'yellow', default => 'zinc' }">
                                        {{ $censusEntry->priority_level->label() }}
                                    </flux:badge>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-muted">
                                {{ $family->beneficiaries->filter(fn ($b) => $b->hasProfile())->count() }} / {{ $family->beneficiaries->count() }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" :href="route('beneficiaries.show', $family)" wire:navigate>{{ __('Ver hogar') }}</flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="p-4 border-t border-line">
                {{ $this->families->links() }}
            </div>
        @endif
    </div>
</section>
