<?php

use App\Actions\MasterItems\RequestNewMasterItemAction;
use App\Enums\MasterItemStatus;
use App\Models\Category;
use App\Models\MasterItem;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Registrar entrada — Kardex')] class extends Component {
    public bool $showRequestItemModal = false;

    public string $requestName = '';

    public ?int $requestCategoryId = null;

    public string $requestUnitOfMeasure = '';

    public ?string $requestDescription = null;

    #[Computed]
    public function warehouses()
    {
        return auth()->user()->assignableWarehouses();
    }

    #[Computed]
    public function categories()
    {
        return Category::with(['masterItems' => fn ($query) => $query->where('status', MasterItemStatus::Approved)->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function openRequestItemModal(): void
    {
        $this->authorize('request', MasterItem::class);

        $this->reset(['requestName', 'requestCategoryId', 'requestUnitOfMeasure', 'requestDescription']);
        $this->resetValidation();
        $this->showRequestItemModal = true;
    }

    public function requestNewItem(RequestNewMasterItemAction $action): void
    {
        $this->authorize('request', MasterItem::class);

        $data = $this->validate([
            'requestName' => MasterItem::nameRules(),
            'requestCategoryId' => ['required', 'integer', 'exists:categories,id'],
            'requestUnitOfMeasure' => ['required', 'string', 'max:30'],
            'requestDescription' => ['nullable', 'string', 'max:500'],
        ], [
            'requestName.regex' => __('El nombre no puede contener los signos < ni >.'),
        ]);

        $action->handle([
            'name' => $data['requestName'],
            'category_id' => $data['requestCategoryId'],
            'unit_of_measure' => $data['requestUnitOfMeasure'],
            'description' => $data['requestDescription'],
        ], auth()->user());

        $this->showRequestItemModal = false;

        Flux::toast(variant: 'success', text: __('Solicitud enviada. Un admin la revisará antes de que puedas usarla.'));
    }
}; ?>

<section class="w-full max-w-2xl" x-data="stockEntryForm()" x-cloak>
    <flux:heading size="xl">{{ __('Registrar entrada de stock') }}</flux:heading>
    <flux:subheading class="mb-6">{{ __('Registra el insumo apenas llega físicamente a la bodega. Funciona sin conexión.') }}</flux:subheading>

    <div x-show="submitted" x-cloak class="card-brutal p-8 text-center">
        <template x-if="queuedOffline">
            <div>
                <flux:heading size="lg">{{ __('Guardado en este dispositivo') }}</flux:heading>
                <p class="text-muted mt-2">{{ __('No hay conexión ahora mismo. Se sincronizará automáticamente en cuanto vuelvas a tener señal.') }}</p>
            </div>
        </template>
        <template x-if="!queuedOffline">
            <div>
                <flux:heading size="lg">{{ __('Entrada registrada') }}</flux:heading>
                <p class="text-muted mt-2">{{ __('El stock ya está disponible en la bodega.') }}</p>
            </div>
        </template>
        <div class="flex gap-3 justify-center mt-6">
            <flux:button variant="primary" x-on:click="startNew()">{{ __('Registrar otra entrada') }}</flux:button>
            <flux:button :href="route('kardex.index')" wire:navigate>{{ __('Ver Kardex') }}</flux:button>
        </div>
    </div>

    <div x-show="!submitted" x-cloak class="card-brutal p-6 md:p-8 space-y-4">
        <flux:callout x-show="errorMessage" x-cloak variant="danger" x-text="errorMessage"></flux:callout>

        <flux:field>
            <flux:label>{{ __('Bodega') }}</flux:label>
            <flux:select x-model="warehouse_id">
                <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                @foreach ($this->warehouses as $warehouse)
                    <flux:select.option value="{{ $warehouse->id }}">{{ $warehouse->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Ítem') }}</flux:label>
            <flux:select x-model="master_item_id">
                <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                @foreach ($this->categories as $category)
                    @foreach ($category->masterItems as $item)
                        <flux:select.option value="{{ $item->id }}">{{ $category->name }} — {{ $item->name }} ({{ $item->unit_of_measure }})</flux:select.option>
                    @endforeach
                @endforeach
            </flux:select>
            <p class="text-xs text-muted mt-1">
                {{ __('¿No encuentras el ítem?') }}
                <flux:link href="#" wire:click.prevent="openRequestItemModal">{{ __('Solicitar uno nuevo.') }}</flux:link>
            </p>
        </flux:field>

        <flux:input x-model="quantity" :label="__('Cantidad')" type="number" min="1" />
        <flux:input x-model="lot_number" :label="__('Número de lote (opcional)')" />
        <flux:input x-model="expiry_date" :label="__('Fecha de vencimiento (opcional)')" type="date" />
        <flux:input x-model="notes" :label="__('Notas (opcional)')" />

        <div class="flex justify-end pt-2">
            <flux:button variant="primary" x-on:click="submit()" x-bind:disabled="submitting || !warehouse_id || !master_item_id || !quantity">
                <span x-show="!submitting">{{ __('Guardar entrada') }}</span>
                <span x-show="submitting" x-cloak>{{ __('Guardando...') }}</span>
            </flux:button>
        </div>
    </div>

    <flux:modal wire:model="showRequestItemModal" class="max-w-md">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Solicitar ítem nuevo') }}</flux:heading>
            <p class="text-sm text-muted">{{ __('Queda en revisión hasta que un admin lo apruebe. No podrás usarlo en esta entrada todavía.') }}</p>

            <div class="space-y-4">
                <flux:input wire:model="requestName" :label="__('Nombre del ítem')" />

                <flux:field>
                    <flux:label>{{ __('Categoría') }}</flux:label>
                    <flux:select wire:model="requestCategoryId">
                        <flux:select.option value="">{{ __('Selecciona...') }}</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:input wire:model="requestUnitOfMeasure" :label="__('Unidad de medida')" :placeholder="__('Ej. cajas, kg, unidades')" />
                <flux:textarea wire:model="requestDescription" :label="__('Notas para quien lo revise (opcional)')" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:button wire:click="$set('showRequestItemModal', false)">{{ __('Cancelar') }}</flux:button>
                <flux:button variant="primary" wire:click="requestNewItem">{{ __('Enviar solicitud') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
