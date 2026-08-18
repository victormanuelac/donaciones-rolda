<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="card-brutal p-5 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-primary"></div>
                <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Módulos activos') }}</div>
                <div class="font-display text-3xl font-extrabold text-ink">0</div>
            </div>
            <div class="card-brutal p-5 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-secondary"></div>
                <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Bodegas registradas') }}</div>
                <div class="font-display text-3xl font-extrabold text-ink">0</div>
            </div>
            <div class="card-brutal p-5 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-[3px] bg-warn"></div>
                <div class="font-display text-[11px] font-bold uppercase tracking-wider text-muted mb-2">{{ __('Beneficiarios censados') }}</div>
                <div class="font-display text-3xl font-extrabold text-ink">0</div>
            </div>
        </div>
        <div class="card-brutal flex-1 flex items-center justify-center p-10 text-center">
            <div>
                <p class="font-display text-lg font-bold text-ink mb-1">{{ __('Todavía no hay módulos operativos') }}</p>
                <p class="text-muted text-sm">{{ __('El panel se irá poblando a medida que se construyan los módulos del roadmap.') }}</p>
            </div>
        </div>
    </div>
</x-layouts::app>
