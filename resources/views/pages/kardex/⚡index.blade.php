<?php

use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\Warehouse;
use App\Services\Kardex\StockProjectionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kardex — Inventario')] class extends Component {
    public ?int $warehouseFilter = null;

    public function mount(): void
    {
        $warehouses = auth()->user()->assignableWarehouses();

        if ($warehouses->count() === 1) {
            $this->warehouseFilter = $warehouses->first()->id;
        }
    }

    #[Computed]
    public function warehouses()
    {
        return auth()->user()->assignableWarehouses();
    }

    #[Computed]
    public function entries()
    {
        return StockEntry::query()
            ->with(['masterItem.category', 'warehouse'])
            ->whereIn('warehouse_id', $this->warehouses->pluck('id'))
            ->when($this->warehouseFilter, fn ($query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->latest()
            ->get()
            ->filter(fn (StockEntry $entry) => $entry->availableQuantity() > 0 || $entry->status->value !== 'available');
    }

    #[Computed]
    public function lowStockItems()
    {
        $warehouseIds = $this->warehouseFilter ? [$this->warehouseFilter] : $this->warehouses->pluck('id')->all();

        return MasterItem::query()
            ->whereNotNull('reorder_point')
            ->whereHas('stockEntries', fn ($query) => $query->whereIn('warehouse_id', $warehouseIds))
            ->get()
            ->filter(fn (MasterItem $item) => $item->isBelowReorderPoint($warehouseIds));
    }

    /**
     * Ítems con existencias que, al ritmo de consumo de los últimos 30 días,
     * se agotarían en 21 días o menos. Requiere historial de salidas reciente
     * para poder proyectar — sin eso no aparece aquí (ver StockProjectionService).
     */
    #[Computed]
    public function projectedStockouts()
    {
        $warehouseIds = $this->warehouseFilter ? [$this->warehouseFilter] : $this->warehouses->pluck('id')->all();
        $projection = app(StockProjectionService::class);

        return MasterItem::query()
            ->whereHas('stockEntries', fn ($query) => $query->whereIn('warehouse_id', $warehouseIds)->where('status', 'available'))
            ->get()
            ->map(fn (MasterItem $item) => [
                'item' => $item,
                'days_remaining' => $projection->daysRemaining($item, $warehouseIds),
            ])
            ->filter(fn (array $row) => $row['days_remaining'] !== null && $row['days_remaining'] <= 21)
            ->sortBy('days_remaining')
            ->values();
    }

    #[Computed]
    public function overCapacityWarehouses()
    {
        return $this->warehouses
            ->when($this->warehouseFilter, fn ($collection) => $collection->where('id', $this->warehouseFilter))
            ->filter(fn (Warehouse $warehouse) => $warehouse->isOverCapacity());
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <flux:heading size="xl">{{ __('Kardex — Inventario') }}</flux:heading>
            <flux:subheading>{{ __('Existencias por bodega, lote y fecha de vencimiento.') }}</flux:subheading>
        </div>
        <div class="flex gap-2 flex-wrap">
            <flux:button :href="route('kardex.entry')" variant="primary" icon="plus" wire:navigate>{{ __('Registrar entrada') }}</flux:button>
            <flux:button :href="route('kardex.exit')" icon="minus" wire:navigate>{{ __('Registrar salida') }}</flux:button>
            <flux:button :href="route('kardex.transfer')" icon="arrows-right-left" wire:navigate>{{ __('Trasladar') }}</flux:button>
            <flux:button :href="route('kardex.count')" icon="clipboard-document-check" wire:navigate>{{ __('Conteo físico') }}</flux:button>
            <flux:button :href="route('kardex.expiry-alerts')" icon="exclamation-triangle" wire:navigate>{{ __('Vencimientos') }}</flux:button>
        </div>
    </div>

    @if ($this->lowStockItems->isNotEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
            <flux:callout.heading>{{ __('Ítems bajo el punto de reorden') }}</flux:callout.heading>
            <flux:callout.text>
                {{ $this->lowStockItems->map(fn ($item) => "{$item->name} ({$item->totalAvailableQuantity($this->warehouseFilter ? [$this->warehouseFilter] : $this->warehouses->pluck('id')->all())} {$item->unit_of_measure})")->implode(' · ') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->projectedStockouts->isNotEmpty())
        <flux:callout variant="danger" icon="chart-bar" class="mb-4">
            <flux:callout.heading>{{ __('Proyección de agotamiento (según ritmo de entregas de los últimos 30 días)') }}</flux:callout.heading>
            <flux:callout.text>
                {{ $this->projectedStockouts->map(fn (array $row) => $row['days_remaining'] <= 0
                    ? "{$row['item']->name} ({$row['item']->unit_of_measure}): ".__('agotado')
                    : "{$row['item']->name}: ~".__(':days días', ['days' => $row['days_remaining']]))->implode(' · ') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->overCapacityWarehouses->isNotEmpty())
        <flux:callout variant="warning" icon="archive-box-x-mark" class="mb-4">
            <flux:callout.heading>{{ __('Bodegas por encima de su capacidad máxima') }}</flux:callout.heading>
            <flux:callout.text>
                {{ $this->overCapacityWarehouses->map(fn (Warehouse $warehouse) => "{$warehouse->name}: {$warehouse->occupiedUnits()} / {$warehouse->max_capacity_units}")->implode(' · ') }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->warehouses->count() > 1)
        <flux:field class="max-w-xs mb-4">
            <flux:label>{{ __('Bodega') }}</flux:label>
            <flux:select wire:model.live="warehouseFilter">
                <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                @foreach ($this->warehouses as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    @endif

    <div class="card-brutal overflow-hidden">
        @if ($this->entries->isEmpty())
            <div class="p-10 text-center">
                <p class="font-display text-lg font-bold text-ink">{{ __('Sin existencias registradas') }}</p>
                <p class="text-muted text-sm mt-1">{{ __('Registra una entrada para empezar a llevar el Kardex.') }}</p>
            </div>
        @else
            <table class="w-full">
                <thead>
                    <tr class="bg-surface-2 border-b-2 border-line">
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Ítem') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Bodega') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Lote') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Vencimiento') }}</th>
                        <th class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Disponible') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Estado') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->entries as $entry)
                        <tr wire:key="entry-{{ $entry->id }}" class="border-b border-line last:border-b-0">
                            <td class="px-4 py-3 text-ink">
                                {{ $entry->masterItem->name }}
                                <span class="text-muted text-xs block">{{ $entry->masterItem->category->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-muted">{{ $entry->warehouse->name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $entry->lot_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted">
                                @if ($entry->expiry_date)
                                    <span @class(['text-danger font-bold' => $entry->expiry_date->isPast() || $entry->expiry_date->diffInDays(now()) <= 30])>
                                        {{ $entry->expiry_date->format('d/m/Y') }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-ink font-bold">{{ $entry->availableQuantity() }} {{ $entry->masterItem->unit_of_measure }}</td>
                            <td class="px-4 py-3">
                                <flux:badge :color="$entry->status->value === 'available' ? 'green' : 'zinc'">{{ $entry->status->label() }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" :href="route('kardex.ledger', ['itemId' => $entry->master_item_id])" wire:navigate>{{ __('Ver ficha') }}</flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
