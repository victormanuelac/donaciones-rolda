<?php

use App\Models\MasterItem;
use App\Services\Kardex\KardexLedgerService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Ficha Kardex')] class extends Component {
    #[Url]
    public ?int $itemId = null;

    public ?int $warehouseFilter = null;

    #[Computed]
    public function warehouses()
    {
        return auth()->user()->assignableWarehouses();
    }

    #[Computed]
    public function items()
    {
        return MasterItem::query()
            ->whereHas('stockEntries', fn ($query) => $query->whereIn('warehouse_id', $this->warehouses->pluck('id')))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedItem(): ?MasterItem
    {
        return $this->itemId !== null ? $this->items->firstWhere('id', $this->itemId) : null;
    }

    #[Computed]
    public function movements(): array
    {
        if ($this->selectedItem === null) {
            return [];
        }

        $warehouseIds = $this->warehouseFilter ? [$this->warehouseFilter] : $this->warehouses->pluck('id')->all();

        return app(KardexLedgerService::class)->forItem($this->selectedItem, $warehouseIds);
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <flux:heading size="xl">{{ __('Ficha Kardex') }}</flux:heading>
            <flux:subheading>{{ __('Historial de movimientos de un ítem con saldo corriente.') }}</flux:subheading>
        </div>
        <flux:button :href="route('kardex.index')" wire:navigate>{{ __('Volver al Kardex') }}</flux:button>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mb-6">
        <flux:field class="max-w-sm">
            <flux:label>{{ __('Ítem') }}</flux:label>
            <flux:select wire:model.live="itemId">
                <flux:select.option value="">{{ __('Selecciona un ítem...') }}</flux:select.option>
                @foreach ($this->items as $item)
                    <flux:select.option value="{{ $item->id }}">{{ $item->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        @if ($this->warehouses->count() > 1)
            <flux:field class="max-w-xs">
                <flux:label>{{ __('Bodega') }}</flux:label>
                <flux:select wire:model.live="warehouseFilter">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        @endif
    </div>

    @if (! $this->selectedItem)
        <div class="card-brutal p-10 text-center">
            <p class="font-display text-lg font-bold text-ink">{{ __('Selecciona un ítem para ver su ficha') }}</p>
            <p class="text-muted text-sm mt-1">{{ __('Verás cada entrada, salida, traslado y conteo con el saldo resultante.') }}</p>
        </div>
    @elseif (empty($this->movements))
        <div class="card-brutal p-10 text-center">
            <p class="font-display text-lg font-bold text-ink">{{ __('Sin movimientos registrados') }}</p>
        </div>
    @else
        {{-- Sin esto los filtros `.live` congelan la tabla en silencio durante el
     viaje al servidor, sin ninguna señal de actividad (hallazgo M-1). --}}
    <div wire:loading.flex wire:target="itemId, warehouseFilter" class="items-center gap-2 mb-2 text-sm text-muted">
        <flux:icon.loading variant="micro" />
        {{ __('Actualizando...') }}
    </div>

    <div class="card-brutal overflow-hidden transition-opacity"
         wire:loading.class="opacity-50 pointer-events-none"
         wire:target="itemId, warehouseFilter">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead>
                        <tr class="bg-surface-2 border-b-2 border-line">
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Fecha') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Movimiento') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Bodega') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Lote') }}</th>
                            <th scope="col" class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Cantidad') }}</th>
                            <th scope="col" class="text-right px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Saldo') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->movements as $movement)
                            <tr wire:key="movement-{{ $loop->index }}" class="border-b border-line last:border-b-0">
                                <td class="px-4 py-3 text-muted">{{ $movement['date']->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-ink">{{ $movement['label'] }}</td>
                                <td class="px-4 py-3 text-muted">{{ $movement['warehouse_name'] }}</td>
                                <td class="px-4 py-3 text-muted">{{ $movement['lot_number'] ?? '—' }}</td>
                                <td @class(['px-4 py-3 text-right font-bold', 'text-secondary' => $movement['quantity_delta'] > 0, 'text-danger' => $movement['quantity_delta'] < 0, 'text-muted' => $movement['quantity_delta'] === 0])>
                                    {{ $movement['quantity_delta'] > 0 ? '+' : '' }}{{ $movement['quantity_delta'] }}
                                </td>
                                <td class="px-4 py-3 text-right text-ink font-bold">{{ $movement['balance'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</section>
