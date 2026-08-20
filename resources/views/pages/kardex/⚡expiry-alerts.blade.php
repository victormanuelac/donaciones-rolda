<?php

use App\Enums\ExpiryAlertType;
use App\Enums\StockExitReason;
use App\Models\ExpiryAlert;
use App\Models\StockExit;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vencimientos — Kardex')] class extends Component {
    public bool $showResolveModal = false;

    public ?int $resolvingId = null;

    public string $resolution_action = 'used';

    public ?string $resolution_notes = null;

    #[Computed]
    public function warehouses()
    {
        return auth()->user()->assignableWarehouses();
    }

    #[Computed]
    public function alerts()
    {
        return ExpiryAlert::query()
            ->with(['stockEntry.masterItem', 'stockEntry.warehouse'])
            ->whereNull('resolved_at')
            ->whereHas('stockEntry', fn ($query) => $query->whereIn('warehouse_id', $this->warehouses->pluck('id')))
            ->get()
            ->sortBy(fn (ExpiryAlert $alert) => $alert->stockEntry->expiry_date);
    }

    public function openResolve(int $alertId): void
    {
        $this->resolvingId = $alertId;
        $this->resolution_action = 'used';
        $this->resolution_notes = null;
        $this->showResolveModal = true;
    }

    public function resolve(): void
    {
        $this->validate([
            'resolution_action' => ['required', 'in:used,discarded,returned'],
            'resolution_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $alert = ExpiryAlert::findOrFail($this->resolvingId);
        $entry = $alert->stockEntry;

        if (auth()->user()->cannot('manageStock', $entry->warehouse)) {
            abort(403);
        }

        DB::transaction(function () use ($alert, $entry) {
            // "Se descartó" es un movimiento real de inventario, no solo una nota: se
            // registra como salida para que el disponible del lote quede correcto.
            if ($this->resolution_action === 'discarded') {
                $available = $entry->availableQuantity();

                if ($available > 0) {
                    StockExit::create([
                        'stock_entry_id' => $entry->id,
                        'warehouse_id' => $entry->warehouse_id,
                        'released_by_user_id' => auth()->id(),
                        'exit_reason' => StockExitReason::ExpiredDiscard,
                        'quantity_released' => $available,
                        'release_date' => now(),
                        'notes' => $this->resolution_notes,
                    ]);
                }
            }

            $alert->update([
                'resolved_at' => now(),
                'resolution_action' => $this->resolution_action,
                'resolution_notes' => $this->resolution_notes,
            ]);
        });

        $this->showResolveModal = false;

        Flux::toast(variant: 'success', text: __('Alerta resuelta.'));
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl">{{ __('Vencimientos — Kardex') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Lotes que están por vencer o ya vencieron en tus bodegas asignadas.') }}</flux:subheading>

    <div class="card-brutal overflow-hidden">
        @if ($this->alerts->isEmpty())
            <div class="p-10 text-center">
                <p class="font-display text-lg font-bold text-ink">{{ __('Sin alertas activas') }}</p>
                <p class="text-muted text-sm mt-1">{{ __('No hay lotes por vencer en los próximos 30 días.') }}</p>
            </div>
        @else
            <table class="w-full">
                <thead>
                    <tr class="bg-surface-2 border-b-2 border-line">
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Ítem') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Bodega') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Vence') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Alerta') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->alerts as $alert)
                        <tr wire:key="alert-{{ $alert->id }}" class="border-b border-line last:border-b-0">
                            <td class="px-4 py-3 text-ink">{{ $alert->stockEntry->masterItem->name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $alert->stockEntry->warehouse->name }}</td>
                            <td class="px-4 py-3 text-muted">{{ $alert->stockEntry->expiry_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                <flux:badge :color="$alert->alert_type === ExpiryAlertType::Expired ? 'red' : 'amber'">
                                    {{ $alert->alert_type->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <flux:button size="sm" wire:click="openResolve({{ $alert->id }})">{{ __('Resolver') }}</flux:button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <flux:modal name="resolve-alert-modal" class="max-w-md" wire:model="showResolveModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Resolver alerta de vencimiento') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('¿Qué se hizo con el lote?') }}</flux:label>
                <flux:select wire:model="resolution_action">
                    <flux:select.option value="used">{{ __('Se usó / entregó a tiempo') }}</flux:select.option>
                    <flux:select.option value="discarded">{{ __('Se descartó') }}</flux:select.option>
                    <flux:select.option value="returned">{{ __('Se devolvió al donante/proveedor') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:textarea wire:model="resolution_notes" :label="__('Notas (opcional)')" />

            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('showResolveModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="resolve">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
