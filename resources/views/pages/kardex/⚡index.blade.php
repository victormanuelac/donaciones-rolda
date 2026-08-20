<?php

use App\Models\StockEntry;
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
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <flux:heading size="xl">{{ __('Kardex — Inventario') }}</flux:heading>
            <flux:subheading>{{ __('Existencias por bodega, lote y fecha de vencimiento.') }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('kardex.entry')" variant="primary" icon="plus" wire:navigate>{{ __('Registrar entrada') }}</flux:button>
            <flux:button :href="route('kardex.exit')" icon="minus" wire:navigate>{{ __('Registrar salida') }}</flux:button>
        </div>
    </div>

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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
