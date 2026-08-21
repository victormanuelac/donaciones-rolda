<?php

use App\Actions\Kardex\RegisterStockCountAction;
use App\Models\StockEntry;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

// A diferencia de entrada/salida (offline-first, para el momento en que llega
// o sale la mercancía), el conteo físico es una tarea de reconciliación
// periódica que puede esperar a tener conexión: no justifica la complejidad
// de la cola offline.
new #[Title('Conteo físico — Kardex')] class extends Component {
    public ?int $warehouseFilter = null;

    public ?int $stock_entry_id = null;

    public ?int $counted_quantity = null;

    public string $notes = '';

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
    public function countableEntries()
    {
        return StockEntry::query()
            ->with(['masterItem', 'warehouse'])
            ->whereIn('warehouse_id', $this->warehouses->pluck('id'))
            ->when($this->warehouseFilter, fn ($query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->whereIn('status', ['available', 'expired'])
            ->orderBy('lot_number')
            ->get();
    }

    #[Computed]
    public function selectedEntry(): ?StockEntry
    {
        return $this->stock_entry_id !== null
            ? $this->countableEntries->firstWhere('id', $this->stock_entry_id)
            : null;
    }

    public function register(RegisterStockCountAction $action): void
    {
        $this->validate([
            'stock_entry_id' => ['required', 'integer', 'exists:stock_entries,id'],
            'counted_quantity' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $count = $action->handle([
                'stock_entry_id' => $this->stock_entry_id,
                'counted_quantity' => $this->counted_quantity,
                'notes' => $this->notes !== '' ? $this->notes : null,
            ], auth()->user());
        } catch (AuthorizationException $e) {
            $this->addError('stock_entry_id', $e->getMessage());

            return;
        }

        $message = match (true) {
            $count->difference === 0 => __('Conteo registrado: coincide con el sistema, sin ajustes.'),
            $count->difference > 0 => __('Conteo registrado: se agregaron :qty unidades encontradas de más.', ['qty' => $count->difference]),
            default => __('Conteo registrado: se dieron de baja :qty unidades faltantes.', ['qty' => abs($count->difference)]),
        };

        Flux::toast(variant: 'success', text: $message);

        $this->reset(['stock_entry_id', 'counted_quantity', 'notes']);
    }
}; ?>

<section class="w-full max-w-2xl">
    <flux:heading size="xl">{{ __('Registrar conteo físico') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Reconcilia lo que dice el sistema contra lo que hay realmente en la bodega.') }}</flux:subheading>

    <div class="card-brutal p-6 md:p-8 space-y-4">
        <flux:error name="stock_entry_id" />

        @if ($this->warehouses->count() > 1)
            <flux:field>
                <flux:label>{{ __('Bodega') }}</flux:label>
                <flux:select wire:model.live="warehouseFilter">
                    <flux:select.option value="">{{ __('Todas') }}</flux:select.option>
                    @foreach ($this->warehouses as $warehouse)
                        <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        @endif

        @if ($this->countableEntries->isEmpty())
            <flux:callout variant="secondary">{{ __('No hay lotes disponibles para contar en tus bodegas asignadas.') }}</flux:callout>
        @else
            <flux:field>
                <flux:label>{{ __('Lote a contar') }}</flux:label>
                <flux:select wire:model.live="stock_entry_id">
                    <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                    @foreach ($this->countableEntries as $entry)
                        <flux:select.option value="{{ $entry->id }}">
                            {{ $entry->warehouse->name }} — {{ $entry->masterItem->name }}
                            {{ $entry->lot_number ? "(lote {$entry->lot_number})" : '' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            @if ($this->selectedEntry)
                <flux:callout variant="secondary" icon="information-circle">
                    <flux:callout.text>
                        {{ __('El sistema dice: :qty :unit disponibles.', ['qty' => $this->selectedEntry->availableQuantity(), 'unit' => $this->selectedEntry->masterItem->unit_of_measure]) }}
                    </flux:callout.text>
                </flux:callout>

                <flux:input wire:model="counted_quantity" :label="__('Cantidad contada físicamente')" type="number" min="0" />
                <flux:textarea wire:model="notes" :label="__('Notas (opcional)')" />

                <div class="flex justify-end pt-2">
                    <flux:button variant="primary" wire:click="register" wire:loading.attr="disabled" wire:target="register">
                        {{ __('Guardar conteo') }}
                    </flux:button>
                </div>
            @endif
        @endif
    </div>
</section>
