<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Bienvenido') }} - {{ config('app.name', 'Donaciones Rolda') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased">
        <div class="fixed inset-0 grid-decoration"></div>

        <header class="relative z-10 flex items-center justify-between px-6 py-4 border-b-2 border-line bg-surface">
            <span class="font-display text-xl font-extrabold uppercase tracking-tight text-primary" style="transform:rotate(-1deg)">
                {{ config('app.name', 'Donaciones Rolda') }}
            </span>
            <nav class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" wire:navigate
                        class="btn-brutal bg-primary text-white border-canvas px-5 py-2 text-sm">
                        {{ __('Ir al panel') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" wire:navigate
                        class="btn-brutal bg-surface-2 text-ink border-line px-5 py-2 text-sm">
                        {{ __('Iniciar sesión') }}
                    </a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" wire:navigate
                            class="btn-brutal bg-primary text-white border-canvas px-5 py-2 text-sm">
                            {{ __('Registrarme') }}
                        </a>
                    @endif
                @endauth
            </nav>
        </header>

        <main class="relative z-10 flex min-h-[calc(100vh-73px)] items-center justify-center px-6 py-16">
            <div class="card-brutal max-w-xl w-full p-8 md:p-10">
                <h1 class="font-display text-3xl md:text-4xl font-extrabold tracking-tight text-ink mb-3">
                    {{ __('Donaciones Rolda') }}
                </h1>
                <p class="text-muted text-base leading-relaxed mb-8">
                    {{ __('Plataforma para rastrear y gestionar la disponibilidad de medicamentos, insumos médicos, alimentos y herramientas durante emergencias locales en Roldanillo.') }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" wire:navigate
                        class="btn-brutal bg-primary text-white border-canvas px-6 py-3 text-sm">
                        → {{ __('Acceder al sistema') }}
                    </a>
                </div>
            </div>
        </main>
    </body>
</html>
