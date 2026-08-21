<?php

use App\Actions\Users\ApproveUserAction;
use App\Actions\Users\RejectUserAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Usuarios pendientes de aprobación')] class extends Component {
    /** @var array<int, string> */
    public array $selectedRole = [];

    public function mount(): void
    {
        $this->authorize('viewPendingQueue', User::class);

        // El <select> nativo con wire:model no respeta el `selected` de Blade
        // cuando el valor no está en el array todavía — hay que precargar un
        // valor real por cada fila o el desplegable muestra una opción que no
        // coincide con el rol que en verdad se asignaría al aprobar.
        foreach ($this->pendingUsers as $pending) {
            $this->selectedRole[$pending->id] = UserRole::Operator->value;
        }
    }

    #[Computed]
    public function pendingUsers()
    {
        return User::query()
            ->where('status', UserStatus::Pending)
            ->oldest()
            ->get();
    }

    public function roleFor(int $userId): string
    {
        return $this->selectedRole[$userId] ?? UserRole::Operator->value;
    }

    public function approve(int $userId, ApproveUserAction $action): void
    {
        $target = User::findOrFail($userId);

        $this->authorize('approve', $target);

        $role = UserRole::from($this->roleFor($userId));

        $action->handle($target, $role);

        unset($this->selectedRole[$userId]);

        Flux::toast(variant: 'success', text: __(':name aprobado como :role.', ['name' => $target->name, 'role' => $role->label()]));
    }

    public function reject(int $userId, RejectUserAction $action): void
    {
        $target = User::findOrFail($userId);

        $this->authorize('reject', $target);

        $action->handle($target);

        Flux::toast(variant: 'danger', text: __(':name rechazado.', ['name' => $target->name]));
    }
}; ?>

<section class="w-full">
    <flux:heading size="xl">{{ __('Usuarios pendientes de aprobación') }}</flux:heading>
    <flux:subheading class="mb-6">
        {{ __('Aprueba o rechaza a quienes se registraron y todavía no tienen acceso al sistema.') }}
    </flux:subheading>

    <div class="card-brutal overflow-hidden">
        @if ($this->pendingUsers->isEmpty())
            <div class="p-10 text-center">
                <p class="font-display text-lg font-bold text-ink">{{ __('No hay usuarios pendientes') }}</p>
                <p class="text-muted text-sm mt-1">{{ __('Todos los registros ya fueron revisados.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px]">
                    <thead>
                        <tr class="bg-surface-2 border-b-2 border-line">
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Nombre') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Email') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Registrado') }}</th>
                            <th scope="col" class="text-left px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-muted font-display">{{ __('Rol a asignar') }}</th>
                            <th scope="col" class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->pendingUsers as $pending)
                            <tr wire:key="pending-{{ $pending->id }}" class="border-b border-line last:border-b-0">
                                <td class="px-4 py-3 text-ink">{{ $pending->name }}</td>
                                <td class="px-4 py-3 text-muted">{{ $pending->email }}</td>
                                <td class="px-4 py-3 text-muted">{{ $pending->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3">
                                    <flux:select wire:model="selectedRole.{{ $pending->id }}" size="sm">
                                        @foreach (UserRole::cases() as $role)
                                            <flux:select.option :value="$role->value" :selected="$this->roleFor($pending->id) === $role->value">
                                                {{ $role->label() }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-2 justify-end">
                                        <flux:button size="sm" variant="primary" wire:click="approve({{ $pending->id }})" wire:loading.attr="disabled" wire:target="approve({{ $pending->id }})">
                                            {{ __('Aprobar') }}
                                        </flux:button>
                                        <flux:button size="sm" variant="danger" wire:click="reject({{ $pending->id }})" wire:confirm="{{ __('¿Rechazar a :name?', ['name' => $pending->name]) }}">
                                            {{ __('Rechazar') }}
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
