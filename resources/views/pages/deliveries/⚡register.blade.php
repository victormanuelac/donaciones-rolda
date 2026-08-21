<?php

use App\Actions\Kardex\RegisterStockExitAction;
use App\Enums\StockExitReason;
use App\Exceptions\ExpiredStockException;
use App\Exceptions\InsufficientStockException;
use App\Models\Family;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Services\Kardex\StockProjectionService;
use Flux\Flux;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registrar entrega — Entregas y Seguimiento')] class extends Component {
    public string $familySearch = '';

    public ?int $family_id = null;

    public ?int $stock_entry_id = null;

    public int $quantity_released = 1;

    public string $exit_reason = 'emergency_assistance';

    public string $notes = '';

    #[Computed]
    public function warehouses()
    {
        return auth()->user()->assignableWarehouses();
    }

    /**
     * Búsqueda en línea del hogar beneficiario por nombre del jefe de hogar.
     * No se cachea offline a propósito: son datos con PII (censo, Módulo 7).
     */
    #[Computed]
    public function familyResults()
    {
        $search = trim($this->familySearch);

        if ($this->family_id !== null || mb_strlen($search) < 3) {
            return collect();
        }

        return Family::query()
            ->where('head_full_name', 'like', '%'.$search.'%')
            ->orderBy('head_full_name')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function selectedFamily(): ?Family
    {
        return $this->family_id !== null ? Family::find($this->family_id) : null;
    }

    /**
     * Últimas entregas ya registradas para el hogar seleccionado, para que el
     * operador pueda ver si ya recibió ayuda recientemente antes de despachar otra.
     */
    #[Computed]
    public function recentDeliveries()
    {
        if ($this->family_id === null) {
            return collect();
        }

        return StockExit::query()
            ->where('family_id', $this->family_id)
            ->with('stockEntry.masterItem')
            ->latest('release_date')
            ->limit(5)
            ->get();
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
    public function daysRemainingForSelectedEntry(): ?float
    {
        $entry = $this->stock_entry_id
            ? $this->availableEntries->firstWhere('id', $this->stock_entry_id)
            : null;

        if ($entry === null) {
            return null;
        }

        return app(StockProjectionService::class)->daysRemaining($entry->masterItem, $this->warehouses->pluck('id')->all());
    }

    public function selectFamily(int $familyId): void
    {
        $this->family_id = $familyId;
        $this->familySearch = '';
    }

    public function clearFamily(): void
    {
        $this->family_id = null;
    }

    public function register(RegisterStockExitAction $action): void
    {
        $this->validate([
            'family_id' => ['required', 'integer', 'exists:families,id'],
            'stock_entry_id' => ['required', 'integer', 'exists:stock_entries,id'],
            'quantity_released' => ['required', 'integer', 'min:1'],
            'exit_reason' => ['required', 'in:donation,emergency_assistance,subsidized_sale,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $entry = StockEntry::findOrFail($this->stock_entry_id);

        try {
            $action->handle([
                'stock_entry_id' => $entry->id,
                'warehouse_id' => $entry->warehouse_id,
                'family_id' => $this->family_id,
                'quantity_released' => $this->quantity_released,
                'exit_reason' => $this->exit_reason,
                'received_by_name' => $this->selectedFamily?->head_full_name,
                'notes' => $this->notes !== '' ? $this->notes : null,
            ], auth()->user());
        } catch (AuthorizationException|InsufficientStockException|ExpiredStockException $e) {
            $this->addError('stock_entry_id', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Entrega registrada. El inventario ya quedó actualizado.'));

        $this->reset(['family_id', 'familySearch', 'stock_entry_id', 'notes']);
        $this->quantity_released = 1;
        $this->exit_reason = 'emergency_assistance';
    }
}; ?>

<section class="w-full max-w-2xl">
    <flux:heading size="xl">{{ __('Registrar entrega') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Entrega física de insumos a un hogar beneficiario ya registrado en el censo.') }}</flux:subheading>

    <div class="card-brutal p-6 md:p-8 space-y-4">
        @if (! $this->selectedFamily)
            <flux:field>
                <flux:label>{{ __('Buscar hogar beneficiario') }}</flux:label>
                <flux:description>{{ __('Escribe el nombre del jefe de hogar registrado en el censo (mínimo 3 letras).') }}</flux:description>
                <flux:input wire:model.live.debounce.400ms="familySearch" :placeholder="__('Ej. María Gómez')" icon="magnifying-glass" />
                <flux:error name="family_id" />
            </flux:field>

            @if ($this->familyResults->isNotEmpty())
                <div class="card-brutal divide-y divide-line">
                    @foreach ($this->familyResults as $family)
                        <button
                            type="button"
                            wire:click="selectFamily({{ $family->id }})"
                            wire:key="family-{{ $family->id }}"
                            class="w-full text-left px-4 py-3 hover:bg-surface-2 transition-colors"
                        >
                            <p class="text-ink font-medium">{{ $family->head_full_name }}</p>
                            <p class="text-xs text-muted">{{ $family->neighborhood ?? $family->address }} — {{ __('Hogar de :size personas', ['size' => $family->household_size]) }}</p>
                        </button>
                    @endforeach
                </div>
            @elseif (mb_strlen(trim($familySearch)) >= 3)
                <p class="text-muted text-sm">{{ __('No encontramos ningún hogar con ese nombre.') }}</p>
            @endif
        @else
            <div class="card-brutal p-4 flex items-start justify-between gap-4 bg-surface-2">
                <div>
                    <p class="font-display font-bold text-ink">{{ $this->selectedFamily->head_full_name }}</p>
                    <p class="text-xs text-muted">{{ $this->selectedFamily->neighborhood ?? $this->selectedFamily->address }} — {{ __('Hogar de :size personas', ['size' => $this->selectedFamily->household_size]) }}</p>
                </div>
                <flux:button size="sm" icon="x-mark" wire:click="clearFamily">{{ __('Cambiar') }}</flux:button>
            </div>

            @if ($this->recentDeliveries->isNotEmpty())
                <flux:callout variant="secondary" icon="clock">
                    <flux:callout.heading>{{ __('Entregas recientes a este hogar') }}</flux:callout.heading>
                    <flux:callout.text>
                        @foreach ($this->recentDeliveries as $delivery)
                            {{ $delivery->stockEntry->masterItem->name }} ({{ $delivery->quantity_released }} {{ $delivery->stockEntry->masterItem->unit_of_measure }}) — {{ $delivery->release_date->format('d/m/Y') }}<br>
                        @endforeach
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:error name="stock_entry_id" />

            @if ($this->availableEntries->isEmpty())
                <flux:callout variant="secondary">{{ __('No hay lotes con existencias disponibles en tus bodegas asignadas.') }}</flux:callout>
            @else
                <flux:field>
                    <flux:label>{{ __('Insumo a entregar') }}</flux:label>
                    <flux:select wire:model.live="stock_entry_id">
                        <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                        @foreach ($this->availableEntries as $entry)
                            <flux:select.option value="{{ $entry->id }}">
                                {{ $entry->warehouse->name }} — {{ $entry->masterItem->name }}
                                ({{ $entry->availableQuantity() }} {{ $entry->masterItem->unit_of_measure }} disponibles{{ $entry->expiry_date ? ', vence '.$entry->expiry_date->format('d/m/Y') : '' }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @if ($this->daysRemainingForSelectedEntry !== null)
                        <flux:description>
                            {{ __('A este ritmo de entregas, quedan ~:days días de existencias de este ítem.', ['days' => $this->daysRemainingForSelectedEntry]) }}
                        </flux:description>
                    @endif
                </flux:field>

                <flux:input wire:model="quantity_released" :label="__('Cantidad a entregar')" type="number" min="1" />

                <flux:field>
                    <flux:label>{{ __('Motivo de la entrega') }}</flux:label>
                    <flux:select wire:model="exit_reason">
                        @foreach ([StockExitReason::EmergencyAssistance, StockExitReason::Donation, StockExitReason::SubsidizedSale, StockExitReason::Other] as $reason)
                            <flux:select.option value="{{ $reason->value }}">{{ $reason->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:textarea wire:model="notes" :label="__('Notas (opcional)')" />

                <div class="flex justify-end pt-2">
                    <flux:button variant="primary" wire:click="register" wire:loading.attr="disabled" wire:target="register">
                        {{ __('Guardar entrega') }}
                    </flux:button>
                </div>
            @endif
        @endif
    </div>
</section>
