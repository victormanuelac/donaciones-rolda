<?php

use App\Models\GeographicZone;
use App\Models\StockExit;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Entregas y Seguimiento')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $zoneFilter = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'zoneFilter'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function zones()
    {
        return GeographicZone::where('is_active', true)->orderBy('name')->get();
    }

    #[Computed]
    public function deliveries()
    {
        $warehouseIds = auth()->user()->assignableWarehouses()->pluck('id');
        $search = trim($this->search);

        return StockExit::query()
            ->whereNotNull('family_id')
            ->whereIn('warehouse_id', $warehouseIds)
            ->with(['family', 'stockEntry.masterItem', 'warehouse', 'releasedBy'])
            ->when($search !== '', fn ($query) => $query->whereHas('family', fn ($q) => $q->where('head_full_name', 'like', '%'.$search.'%')))
            ->when($this->zoneFilter, fn ($query) => $query->whereHas('family', fn ($q) => $q->where('zone_id', $this->zoneFilter)))
            ->latest('release_date')
            ->paginate(20);
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <flux:heading size="xl">{{ __('Entregas y Seguimiento') }}</flux:heading>
            <flux:subheading>{{ __('Historial de entregas físicas a hogares beneficiarios.') }}</flux:subheading>
        </div>
        <flux:button :href="route('deliveries.register')" variant="primary" icon="plus" wire:navigate>{{ __('Registrar entrega') }}</flux:button>
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
    </div>

    {{-- Sin esto los filtros `.live` congelan la tabla en silencio durante el
     viaje al servidor, sin ninguna señal de actividad (hallazgo M-1). --}}
    <div wire:loading.flex wire:target="search, zoneFilter" class="items-center gap-2 mb-2 text-sm text-muted">
        <flux:icon.loading variant="micro" />
        {{ __('Actualizando...') }}
    </div>

    <div class="card-brutal overflow-hidden transition-opacity"
         wire:loading.class="opacity-50 pointer-events-none"
         wire:target="search, zoneFilter">
        @if ($this->deliveries->isEmpty())
            <div class="p-10 text-center">
                <p class="font-display text-lg font-bold text-ink">{{ __('Sin entregas registradas') }}</p>
                <p class="text-muted text-sm mt-1">{{ __('Registra la primera entrega a un hogar beneficiario.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead>
                        <tr class="bg-surface-2 border-b-2 border-line">
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Fecha') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Hogar') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Ítem') }}</th>
                            <th scope="col" class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Cantidad') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Bodega') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Entregado por') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->deliveries as $delivery)
                            <tr wire:key="delivery-{{ $delivery->id }}" class="border-b border-line last:border-b-0">
                                <td class="px-4 py-3 text-muted">{{ $delivery->release_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-ink">
                                    {{ $delivery->family?->head_full_name ?? '—' }}
                                    <span class="text-muted text-xs block">{{ $delivery->family?->neighborhood }}</span>
                                </td>
                                <td class="px-4 py-3 text-ink">{{ $delivery->stockEntry->masterItem->name }}</td>
                                <td class="px-4 py-3 text-right text-ink font-bold">{{ $delivery->quantity_released }} {{ $delivery->stockEntry->masterItem->unit_of_measure }}</td>
                                <td class="px-4 py-3 text-muted">{{ $delivery->warehouse->name }}</td>
                                <td class="px-4 py-3 text-muted">{{ $delivery->releasedBy?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-line">
                {{ $this->deliveries->links() }}
            </div>
        @endif
    </div>
</section>
