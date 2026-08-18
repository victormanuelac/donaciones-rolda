<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased">
        <div class="relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="fixed inset-0 grid-decoration"></div>

            <div class="card-brutal relative z-10 w-full max-w-sm flex-col gap-2 p-8" style="border-width:3px;border-radius:10px;box-shadow:7px 7px 0 0 #000">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium mb-2" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-primary" />
                    </span>
                    <span class="font-display text-lg font-extrabold uppercase tracking-tight text-primary">{{ config('app.name', 'Donaciones Rolda') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
