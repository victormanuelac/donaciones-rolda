<?php

use App\Actions\MasterItems\ApproveMasterItemAction;
use App\Actions\MasterItems\ConsolidateMasterItemAction;
use App\Actions\MasterItems\RejectMasterItemAction;
use App\Enums\MasterItemStatus;
use App\Models\Category;
use App\Models\MasterItem;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ítems pendientes de aprobación')] class extends Component {
    /** @var array<int, string> */
    public array $name = [];

    /** @var array<int, int> */
    public array $categoryId = [];

    /** @var array<int, string> */
    public array $unitOfMeasure = [];

    /** @var array<int, string> */
    public array $rejectionReason = [];

    /** @var array<int, int|null> */
    public array $consolidateInto = [];

    public function mount(): void
    {
        $this->authorize('viewPendingQueue', MasterItem::class);

        foreach ($this->pendingItems as $item) {
            $this->name[$item->id] = $item->name;
            $this->categoryId[$item->id] = $item->category_id;
            $this->unitOfMeasure[$item->id] = $item->unit_of_measure;
        }
    }

    #[Computed]
    public function pendingItems()
    {
        return MasterItem::query()
            ->with(['category', 'createdBy'])
            ->where('status', MasterItemStatus::UnderReview)
            ->oldest()
            ->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::where('is_active', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function approvedItems()
    {
        return MasterItem::where('status', MasterItemStatus::Approved)->orderBy('name')->get();
    }

    public function approve(int $itemId, ApproveMasterItemAction $action): void
    {
        $item = MasterItem::findOrFail($itemId);

        $this->authorize('approve', $item);

        $action->handle($item, auth()->user(), [
            'name' => $this->name[$itemId] ?? $item->name,
            'category_id' => $this->categoryId[$itemId] ?? $item->category_id,
            'unit_of_measure' => $this->unitOfMeasure[$itemId] ?? $item->unit_of_measure,
        ]);

        Flux::toast(variant: 'success', text: __(':name aprobado.', ['name' => $item->name]));
    }

    public function reject(int $itemId, RejectMasterItemAction $action): void
    {
        $item = MasterItem::findOrFail($itemId);

        $this->authorize('reject', $item);

        $reason = trim($this->rejectionReason[$itemId] ?? '');

        if ($reason === '') {
            $this->addError("rejectionReason.{$itemId}", __('Escribe un motivo para rechazar el ítem.'));

            return;
        }

        $action->handle($item, auth()->user(), $reason);

        Flux::toast(variant: 'danger', text: __(':name rechazado.', ['name' => $item->name]));
    }

    public function consolidate(int $itemId, ConsolidateMasterItemAction $action): void
    {
        $item = MasterItem::findOrFail($itemId);

        $this->authorize('consolidate', $item);

        $targetId = $this->consolidateInto[$itemId] ?? null;

        if (! $targetId) {
            $this->addError("consolidateInto.{$itemId}", __('Selecciona el ítem con el que se debe consolidar.'));

            return;
        }

        $target = MasterItem::findOrFail($targetId);

        $action->handle($item, $target, auth()->user());

        Flux::toast(variant: 'success', text: __(':name consolidado con :target.', ['name' => $item->name, 'target' => $target->name]));
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl">{{ __('Ítems pendientes de aprobación') }}</flux:heading>
    <flux:subheading class="mb-6">
        {{ __('Aprueba, rechaza o consolida los ítems que los operadores solicitaron desde el Kardex.') }}
    </flux:subheading>

    <div class="card-brutal overflow-hidden">
        @if ($this->pendingItems->isEmpty())
            <div class="p-10 text-center">
                <p class="font-display text-lg font-bold text-ink">{{ __('No hay ítems pendientes') }}</p>
                <p class="text-muted text-sm mt-1">{{ __('Todas las solicitudes ya fueron revisadas.') }}</p>
            </div>
        @else
            <div class="divide-y divide-line">
                @foreach ($this->pendingItems as $item)
                    <div wire:key="item-{{ $item->id }}" class="p-4 space-y-3">
                        <p class="text-xs text-muted">
                            {{ __('Solicitado por :name, :time', ['name' => $item->createdBy?->name ?? __('desconocido'), 'time' => $item->created_at?->diffForHumans()]) }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <flux:input wire:model="name.{{ $item->id }}" :label="__('Nombre')" />

                            <flux:field>
                                <flux:label>{{ __('Categoría') }}</flux:label>
                                <flux:select wire:model="categoryId.{{ $item->id }}">
                                    @foreach ($this->categories as $category)
                                        <flux:select.option :value="$category->id" :selected="($this->categoryId[$item->id] ?? $item->category_id) === $category->id">
                                            {{ $category->name }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>

                            <flux:input wire:model="unitOfMeasure.{{ $item->id }}" :label="__('Unidad de medida')" />
                        </div>

                        @if ($item->description)
                            <p class="text-sm text-muted">{{ $item->description }}</p>
                        @endif

                        <div class="flex flex-wrap items-end gap-3 pt-2">
                            <flux:button size="sm" variant="primary" wire:click="approve({{ $item->id }})">
                                {{ __('Aprobar') }}
                            </flux:button>

                            <div class="flex items-end gap-2">
                                <flux:field class="mb-0">
                                    <flux:select wire:model="consolidateInto.{{ $item->id }}" size="sm" :placeholder="__('Consolidar con...')">
                                        @foreach ($this->approvedItems as $approved)
                                            <flux:select.option :value="$approved->id">{{ $approved->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="consolidateInto.{{ $item->id }}" />
                                </flux:field>
                                <flux:button size="sm" wire:click="consolidate({{ $item->id }})">
                                    {{ __('Consolidar') }}
                                </flux:button>
                            </div>

                            <div class="flex items-end gap-2">
                                <flux:field class="mb-0">
                                    <flux:input wire:model="rejectionReason.{{ $item->id }}" size="sm" :placeholder="__('Motivo de rechazo')" />
                                    <flux:error name="rejectionReason.{{ $item->id }}" />
                                </flux:field>
                                <flux:button size="sm" variant="danger" wire:click="reject({{ $item->id }})">
                                    {{ __('Rechazar') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
