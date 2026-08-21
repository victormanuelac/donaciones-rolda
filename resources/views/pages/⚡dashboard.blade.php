<?php

use App\Enums\CensusPriorityLevel;
use App\Enums\MasterItemStatus;
use App\Enums\UserStatus;
use App\Models\CensusEntry;
use App\Models\Family;
use App\Models\MasterItem;
use App\Models\StockEntry;
use App\Models\StockExit;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Panel de inicio. Cada tarjeta sale de un `count()` agregado en SQL — nada de
 * cargar colecciones para contarlas en PHP, que es justamente el problema que
 * tiene /kardex (ver docs/17-Auditoria-Frontend.md, hallazgo A-2).
 *
 * Las secciones se muestran según lo que el rol puede ver, para no filtrar
 * volúmenes de censo a roles sin acceso a beneficiarios.
 */
new #[Title('Panel')] class extends Component {
    /**
     * @return array{bodegas: int, lotes: int, entregas: int}
     */
    #[Computed]
    public function inventario(): array
    {
        $warehouseIds = auth()->user()->assignableWarehouses()->pluck('id');

        return [
            'bodegas' => Warehouse::where('is_active', true)->whereIn('id', $warehouseIds)->count(),
            'lotes' => StockEntry::where('status', 'available')->whereIn('warehouse_id', $warehouseIds)->count(),
            'entregas' => StockExit::whereIn('warehouse_id', $warehouseIds)
                ->where('release_date', '>=', now()->subDays(30))
                ->count(),
        ];
    }

    /**
     * @return array{hogares: int, personas: int, prioritarios: int}
     */
    #[Computed]
    public function censo(): array
    {
        return [
            'hogares' => Family::count(),
            'personas' => (int) Family::sum('household_size'),
            'prioritarios' => CensusEntry::whereIn('priority_level', [
                CensusPriorityLevel::Critico->value,
                CensusPriorityLevel::Alto->value,
            ])->distinct('family_id')->count('family_id'),
        ];
    }

    /**
     * @return array{usuarios: int, items: int}
     */
    #[Computed]
    public function pendientes(): array
    {
        return [
            'usuarios' => User::where('status', UserStatus::Pending)->count(),
            'items' => MasterItem::where('status', MasterItemStatus::UnderReview)->count(),
        ];
    }
}; ?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Panel') }}</flux:heading>
        <flux:subheading>{{ __('Resumen de la operación al día de hoy.') }}</flux:subheading>
    </div>

    @if (auth()->user()->canManageStock())
        <div>
            <h2 class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Inventario') }}</h2>
            <div class="grid auto-rows-min gap-4 sm:grid-cols-3">
                <a href="{{ route('admin.warehouses.index') }}" @cannot('viewAny', App\Models\Warehouse::class) tabindex="-1" aria-disabled="true" @endcannot
                   class="card-brutal is-interactive p-5 relative overflow-hidden block">
                    <div class="absolute top-0 left-0 right-0 h-[3px] bg-secondary"></div>
                    <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Bodegas activas') }}</div>
                    <div class="font-display text-3xl font-extrabold text-ink">{{ $this->inventario['bodegas'] }}</div>
                </a>

                <a href="{{ route('kardex.index') }}" wire:navigate class="card-brutal is-interactive p-5 relative overflow-hidden block">
                    <div class="absolute top-0 left-0 right-0 h-[3px] bg-primary"></div>
                    <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Lotes disponibles') }}</div>
                    <div class="font-display text-3xl font-extrabold text-ink">{{ $this->inventario['lotes'] }}</div>
                </a>

                <a href="{{ route('deliveries.index') }}" wire:navigate class="card-brutal is-interactive p-5 relative overflow-hidden block">
                    <div class="absolute top-0 left-0 right-0 h-[3px] bg-info"></div>
                    <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Entregas (30 días)') }}</div>
                    <div class="font-display text-3xl font-extrabold text-ink">{{ $this->inventario['entregas'] }}</div>
                </a>
            </div>
        </div>
    @endif

    @if (auth()->user()->canManageBeneficiaries() || auth()->user()->canSurveyCensus())
        <div>
            <h2 class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Censo de hogares') }}</h2>
            <div class="grid auto-rows-min gap-4 sm:grid-cols-3">
                <div class="card-brutal p-5 relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-[3px] bg-secondary"></div>
                    <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Hogares censados') }}</div>
                    <div class="font-display text-3xl font-extrabold text-ink">{{ $this->censo['hogares'] }}</div>
                </div>

                <div class="card-brutal p-5 relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-[3px] bg-info"></div>
                    <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Personas cubiertas') }}</div>
                    <div class="font-display text-3xl font-extrabold text-ink">{{ $this->censo['personas'] }}</div>
                </div>

                <div class="card-brutal p-5 relative overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-[3px] bg-danger"></div>
                    <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Prioridad alta o crítica') }}</div>
                    <div class="font-display text-3xl font-extrabold text-ink">{{ $this->censo['prioritarios'] }}</div>
                </div>
            </div>
        </div>
    @endif

    @if (auth()->user()->isAdmin())
        <div>
            <h2 class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Pendientes de tu aprobación') }}</h2>
            <div class="grid auto-rows-min gap-4 sm:grid-cols-2">
                <a href="{{ route('admin.users.pending') }}" wire:navigate class="card-brutal is-interactive p-5 relative overflow-hidden block">
                    <div class="absolute top-0 left-0 right-0 h-[3px] bg-warn"></div>
                    <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Usuarios por aprobar') }}</div>
                    <div class="font-display text-3xl font-extrabold text-ink">{{ $this->pendientes['usuarios'] }}</div>
                </a>

                <a href="{{ route('admin.items.pending') }}" wire:navigate class="card-brutal is-interactive p-5 relative overflow-hidden block">
                    <div class="absolute top-0 left-0 right-0 h-[3px] bg-warn"></div>
                    <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Ítems por revisar') }}</div>
                    <div class="font-display text-3xl font-extrabold text-ink">{{ $this->pendientes['items'] }}</div>
                </a>
            </div>
        </div>
    @endif

    @if (! auth()->user()->canManageStock() && ! auth()->user()->canManageBeneficiaries() && ! auth()->user()->canSurveyCensus() && ! auth()->user()->isAdmin())
        <div class="card-brutal p-10 text-center">
            <p class="font-display text-lg font-bold text-ink mb-1">{{ __('Tu rol todavía no tiene módulos asignados') }}</p>
            <p class="text-muted text-sm">{{ __('Escribe a un administrador si necesitas acceso a inventario, censo o entregas.') }}</p>
        </div>
    @endif
</section>
