<?php

use App\Enums\StockExitReason;
use App\Models\StockEntry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registrar salida — Kardex')] class extends Component {
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
            ->where('status', 'available')
            ->whereIn('warehouse_id', $this->warehouses->pluck('id'))
            ->latest()
            ->get()
            ->filter(fn (StockEntry $entry) => $entry->availableQuantity() > 0);
    }

    #[Computed]
    public function warehouseByStockEntry(): array
    {
        return $this->availableEntries->pluck('warehouse_id', 'id')->toArray();
    }
}; ?>

<section class="w-full max-w-2xl" x-data="stockExitForm(@js($this->warehouseByStockEntry))" x-cloak>
    <flux:heading size="xl">{{ __('Registrar salida de stock') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Registra la entrega de un insumo. Funciona sin conexión.') }}</flux:subheading>

    <div x-show="submitted" x-cloak class="card-brutal p-8 text-center">
        <template x-if="queuedOffline">
            <div>
                <flux:heading size="lg">{{ __('Guardado en este dispositivo') }}</flux:heading>
                <p class="text-muted mt-2">{{ __('No hay conexión ahora mismo. Se sincronizará automáticamente en cuanto vuelvas a tener señal.') }}</p>
            </div>
        </template>
        <template x-if="!queuedOffline">
            <div>
                <flux:heading size="lg">{{ __('Salida registrada') }}</flux:heading>
                <p class="text-muted mt-2">{{ __('El inventario ya quedó actualizado.') }}</p>
            </div>
        </template>
        <div class="flex gap-3 justify-center mt-6">
            <flux:button variant="primary" x-on:click="startNew()">{{ __('Registrar otra salida') }}</flux:button>
            <flux:button :href="route('kardex.index')" wire:navigate>{{ __('Ver Kardex') }}</flux:button>
        </div>
    </div>

    <div x-show="!submitted" x-cloak class="card-brutal p-6 md:p-8 space-y-4">
        <flux:callout x-show="errorMessage" x-cloak variant="danger" x-text="errorMessage"></flux:callout>

        @if ($this->availableEntries->isEmpty())
            <flux:callout variant="secondary">{{ __('No hay lotes con existencias disponibles en tus bodegas asignadas.') }}</flux:callout>
        @else
            <flux:field>
                <flux:label>{{ __('Lote a despachar') }}</flux:label>
                <flux:select x-model="stock_entry_id" x-on:change="onStockEntryChange()">
                    <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                    @foreach ($this->availableEntries as $entry)
                        <flux:select.option value="{{ $entry->id }}">
                            {{ $entry->warehouse->name }} — {{ $entry->masterItem->name }}
                            ({{ $entry->availableQuantity() }} {{ $entry->masterItem->unit_of_measure }} disponibles{{ $entry->lot_number ? ", lote {$entry->lot_number}" : '' }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:input x-model="quantity_released" :label="__('Cantidad a despachar')" type="number" min="1" />

            <flux:field>
                <flux:label>{{ __('Motivo de la salida') }}</flux:label>
                <flux:select x-model="exit_reason">
                    <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                    @foreach (StockExitReason::cases() as $reason)
                        <flux:select.option value="{{ $reason->value }}">{{ $reason->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <flux:input x-model="received_by_name" :label="__('Nombre de quien recibe (opcional)')" />
            <flux:input x-model="destination_description" :label="__('Destino (opcional, ej. Albergue temporal)')" />
            <flux:input x-model="notes" :label="__('Notas (opcional)')" />

            <div class="flex justify-end pt-2">
                <flux:button variant="primary" x-on:click="submit()" x-bind:disabled="submitting || !stock_entry_id || !exit_reason || !quantity_released">
                    <span x-show="!submitting">{{ __('Guardar salida') }}</span>
                    <span x-show="submitting" x-cloak>{{ __('Guardando...') }}</span>
                </flux:button>
            </div>
        @endif
    </div>
</section>
