<?php

use App\Models\GeographicZone;
use App\Models\Warehouse;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Bodegas / Centros de Acopio')] class extends Component {
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $address = '';

    public ?int $geographic_zone_id = null;

    public string $contact_person_name = '';

    public string $contact_phone = '';

    public ?string $contact_email = null;

    public ?int $max_capacity_units = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public ?string $notes = null;

    public function mount(): void
    {
        $this->authorize('manageCatalog', Warehouse::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:500'],
            'geographic_zone_id' => ['nullable', 'integer', 'exists:geographic_zones,id'],
            'contact_person_name' => ['required', 'string', 'max:150'],
            'contact_phone' => ['required', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'max_capacity_units' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    #[Computed]
    public function warehouses()
    {
        return Warehouse::query()->with('zone')->orderBy('name')->get();
    }

    #[Computed]
    public function zones()
    {
        return GeographicZone::orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'address', 'geographic_zone_id', 'contact_person_name', 'contact_phone', 'contact_email', 'max_capacity_units', 'latitude', 'longitude', 'notes']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $warehouseId): void
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        $this->editingId = $warehouse->id;
        $this->name = $warehouse->name;
        $this->address = $warehouse->address;
        $this->geographic_zone_id = $warehouse->geographic_zone_id;
        $this->contact_person_name = $warehouse->contact_person_name;
        $this->contact_phone = $warehouse->contact_phone;
        $this->contact_email = $warehouse->contact_email;
        $this->max_capacity_units = $warehouse->max_capacity_units;
        $this->latitude = $warehouse->latitude ? (float) $warehouse->latitude : null;
        $this->longitude = $warehouse->longitude ? (float) $warehouse->longitude : null;
        $this->notes = $warehouse->notes;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('manageCatalog', Warehouse::class);

        $data = $this->validate();

        if ($this->editingId) {
            Warehouse::findOrFail($this->editingId)->update($data);
            Flux::toast(variant: 'success', text: __('Bodega / Centro de Acopio actualizado.'));
        } else {
            Warehouse::create([...$data, 'is_active' => true]);
            Flux::toast(variant: 'success', text: __('Bodega / Centro de Acopio creado.'));
        }

        $this->showModal = false;
    }

    public function toggleActive(int $warehouseId): void
    {
        $this->authorize('manageCatalog', Warehouse::class);

        $warehouse = Warehouse::findOrFail($warehouseId);
        $warehouse->update(['is_active' => ! $warehouse->is_active]);

        Flux::toast(
            variant: 'success',
            text: $warehouse->is_active
                ? __(':name activada.', ['name' => $warehouse->name])
                : __(':name desactivada.', ['name' => $warehouse->name]),
        );
    }
}; ?>

<section class="w-full">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <flux:heading size="xl">{{ __('Bodegas / Centros de Acopio') }}</flux:heading>
            <flux:subheading>{{ __('Catálogo de bodegas donde operan las entradas y salidas del Kardex.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('Nueva bodega') }}</flux:button>
    </div>

    <div class="card-brutal overflow-hidden">
        @if ($this->warehouses->isEmpty())
            <div class="p-10 text-center">
                <p class="font-display text-lg font-bold text-ink">{{ __('Sin bodegas registradas') }}</p>
                <p class="text-muted text-sm mt-1">{{ __('Crea la primera bodega / centro de acopio para empezar a usar el Kardex.') }}</p>
            </div>
        @else
            <table class="w-full">
                <thead>
                    <tr class="bg-surface-2 border-b-2 border-line">
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Nombre') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Zona') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Contacto') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Ocupación') }}</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Estado') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->warehouses as $warehouse)
                        <tr wire:key="warehouse-{{ $warehouse->id }}" class="border-b border-line last:border-b-0">
                            <td class="px-4 py-3 text-ink">
                                {{ $warehouse->name }}
                                <span class="text-muted text-xs block">{{ $warehouse->address }}</span>
                            </td>
                            <td class="px-4 py-3 text-muted">{{ $warehouse->zone?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted">
                                {{ $warehouse->contact_person_name }}
                                <span class="block text-xs">{{ $warehouse->contact_phone }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($warehouse->max_capacity_units)
                                    <span @class(['font-bold' => $warehouse->isOverCapacity(), 'text-danger' => $warehouse->isOverCapacity(), 'text-muted' => ! $warehouse->isOverCapacity()])>
                                        {{ $warehouse->occupiedUnits() }} / {{ $warehouse->max_capacity_units }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge :color="$warehouse->is_active ? 'green' : 'zinc'">
                                    {{ $warehouse->is_active ? __('Activa') : __('Inactiva') }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2 justify-end">
                                    <flux:button size="sm" wire:click="openEdit({{ $warehouse->id }})">{{ __('Editar') }}</flux:button>
                                    <flux:button
                                        size="sm"
                                        :variant="$warehouse->is_active ? 'danger' : 'primary'"
                                        wire:click="toggleActive({{ $warehouse->id }})"
                                        wire:confirm="{{ $warehouse->is_active ? __('¿Desactivar :name?', ['name' => $warehouse->name]) : __('¿Reactivar :name?', ['name' => $warehouse->name]) }}"
                                    >
                                        {{ $warehouse->is_active ? __('Desactivar') : __('Activar') }}
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <flux:modal name="warehouse-form-modal" class="max-w-lg" wire:model="showModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Editar bodega / centro de acopio') : __('Nueva bodega / centro de acopio') }}</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Nombre')" />
                <flux:input wire:model="address" :label="__('Dirección')" />

                <flux:field>
                    <flux:label>{{ __('Zona geográfica (opcional)') }}</flux:label>
                    <flux:select wire:model="geographic_zone_id">
                        <flux:select.option value="">{{ __('Sin definir') }}</flux:select.option>
                        @foreach ($this->zones as $zone)
                            <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:input wire:model="contact_person_name" :label="__('Persona de contacto')" />
                <flux:input wire:model="contact_phone" :label="__('Teléfono de contacto')" type="tel" />
                <flux:input wire:model="contact_email" :label="__('Correo de contacto (opcional)')" type="email" />
                <flux:input wire:model="max_capacity_units" :label="__('Capacidad máxima (opcional)')" type="number" min="1" />

                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="latitude" :label="__('Latitud (opcional)')" type="number" step="any" />
                    <flux:input wire:model="longitude" :label="__('Longitud (opcional)')" type="number" step="any" />
                </div>

                <flux:textarea wire:model="notes" :label="__('Notas (opcional)')" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('showModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="save">{{ __('Guardar') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
