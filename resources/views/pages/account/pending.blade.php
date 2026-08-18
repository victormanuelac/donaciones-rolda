<x-layouts::auth.simple :title="__('Cuenta pendiente de aprobación')">
    <div class="flex w-full flex-col gap-4 text-center">
        <flux:heading size="lg">{{ __('Tu cuenta está pendiente de aprobación') }}</flux:heading>
        <flux:subheading>
            {{ __('Un administrador debe aprobar tu registro antes de que puedas usar el sistema. Te avisaremos por correo cuando quede activa.') }}
        </flux:subheading>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <flux:button as="button" type="submit" variant="ghost" class="w-full">
                {{ __('Cerrar sesión') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth.simple>
