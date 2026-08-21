<?php

use App\Models\StockEntry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Trasladar stock — Kardex')] class extends Component {
    #[Computed]
    public function warehouses()
    {
        return auth()->user()->assignableWarehouses();
    }

    #[Computed]
    public function availableEntries()
    {
        return StockEntry::query()
            ->with(['masterItem', 'warehouse'])
            ->withAvailableQuantity()
            ->where('status', 'available')
            ->whereIn('warehouse_id', $this->warehouses->pluck('id'))
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()))
            ->orderBy('expiry_date')
            ->get()
            ->filter(fn (StockEntry $entry) => $entry->availableQuantity() > 0);
    }

    #[Computed]
    public function warehouseByStockEntry(): array
    {
        return $this->availableEntries->pluck('warehouse_id', 'id')->toArray();
    }

    #[Computed]
    public function fefoOrderedStockEntryIds(): array
    {
        return $this->availableEntries->pluck('id')->toArray();
    }
}; ?>

<section class="w-full max-w-2xl" x-data="stockTransferForm(@js($this->warehouseByStockEntry), @js($this->fefoOrderedStockEntryIds))" x-cloak>
    <flux:heading size="xl">{{ __('Trasladar stock entre bodegas') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Mueve un lote completo o parcial de una bodega a otra. Funciona sin conexión.') }}</flux:subheading>

    <div x-show="submitted" x-cloak class="card-brutal p-8 text-center">
        <template x-if="queuedOffline">
            <div>
                <flux:heading size="lg">{{ __('Guardado en este dispositivo') }}</flux:heading>
                <p class="text-muted mt-2">{{ __('No hay conexión ahora mismo. Se sincronizará automáticamente en cuanto vuelvas a tener señal.') }}</p>
            </div>
        </template>
        <template x-if="!queuedOffline">
            <div>
                <flux:heading size="lg">{{ __('Traslado registrado') }}</flux:heading>
                <p class="text-muted mt-2">{{ __('El stock ya está disponible en la bodega destino.') }}</p>
            </div>
        </template>
        <div class="flex gap-3 justify-center mt-6">
            <flux:button variant="primary" x-on:click="startNew()">{{ __('Registrar otro traslado') }}</flux:button>
            <flux:button :href="route('kardex.index')" wire:navigate>{{ __('Ver Kardex') }}</flux:button>
        </div>
    </div>

    <div x-show="!submitted" x-cloak class="card-brutal p-6 md:p-8 space-y-4">
        <flux:callout x-show="errorMessage" x-cloak variant="danger" x-text="errorMessage"></flux:callout>

        @if ($this->availableEntries->isEmpty())
            <flux:callout variant="secondary">{{ __('No hay lotes con existencias disponibles en tus bodegas asignadas.') }}</flux:callout>
        @else
            <flux:field>
                <flux:label>{{ __('Lote a trasladar') }}</flux:label>
                <flux:description>{{ __('Ordenados por fecha de vencimiento más próxima primero (FEFO). El primero es el sugerido.') }}</flux:description>
                <flux:select x-model="stock_entry_id" x-on:change="onStockEntryChange()">
                    <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                    @foreach ($this->availableEntries as $entry)
                        <flux:select.option value="{{ $entry->id }}">
                            {{ $loop->first ? __('(Sugerido) ') : '' }}{{ $entry->warehouse->name }} — {{ $entry->masterItem->name }}
                            ({{ $entry->availableQuantity() }} {{ $entry->masterItem->unit_of_measure }} disponibles{{ $entry->lot_number ? ", lote {$entry->lot_number}" : '' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Bodega destino') }}</flux:label>
                <flux:select x-model="destination_warehouse_id">
                    <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}" x-bind:disabled="source_warehouse_id == {{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:input x-model="quantity" :label="__('Cantidad a trasladar')" type="number" min="1" />
            <flux:input x-model="notes" :label="__('Notas (opcional)')" />

            <div class="flex justify-end pt-2">
                <flux:button
                    variant="primary"
                    x-on:click="submit()"
                    x-bind:disabled="submitting || !stock_entry_id || !destination_warehouse_id || !quantity || destination_warehouse_id == source_warehouse_id"
                >
                    <span x-show="!submitting">{{ __('Guardar traslado') }}</span>
                    <span x-show="submitting" x-cloak>{{ __('Guardando...') }}</span>
                </flux:button>
            </div>
        @endif
    </div>
</section>
