<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('Buscar insumos') }} - {{ config('app.name', 'Donaciones Rolda') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
        <script>window.Flux.applyAppearance('dark');</script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased">
        <div class="fixed inset-0 grid-decoration"></div>

        <header class="relative z-10 flex items-center justify-between px-6 py-4 border-b-2 border-line bg-surface">
            <a href="{{ route('home') }}" class="font-display text-xl font-extrabold uppercase tracking-tight text-primary" style="transform:rotate(-1deg)">
                {{ config('app.name', 'Donaciones Rolda') }}
            </a>
            <nav class="flex items-center gap-3">
                @auth
                    <flux:button :href="route('dashboard')" variant="primary">{{ __('Ir al panel') }}</flux:button>
                @else
                    <flux:button :href="route('login')">{{ __('Iniciar sesión') }}</flux:button>
                @endauth
            </nav>
        </header>

        <main class="relative z-10 max-w-6xl mx-auto px-6 py-10" x-data="publicSearch()">
            <div class="mb-8">
                <h1 class="font-display text-3xl font-extrabold tracking-tight text-ink mb-2">{{ __('Buscar insumos disponibles') }}</h1>
                <p class="text-muted">{{ __('Encuentra medicinas, alimentos, insumos médicos y herramientas disponibles en los centros de acopio de Roldanillo.') }}</p>
            </div>

            <div class="card-brutal p-4 md:p-6 mb-6 space-y-4">
                <flux:input x-model="query" x-on:input="debouncedSearch()" :placeholder="__('¿Qué insumo buscas? Ej. antibiótico, arroz, gasas...')" icon="magnifying-glass" />

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <flux:select x-model="categoryId" x-on:change="runSearch()">
                        <flux:select.option value="">{{ __('Todas las categorías') }}</flux:select.option>
                        @foreach ($categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select x-model="zoneId" x-on:change="runSearch()">
                        <flux:select.option value="">{{ __('Todas las zonas') }}</flux:select.option>
                        @foreach ($zones as $zone)
                            <flux:select.option value="{{ $zone->id }}">{{ $zone->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div>
                        <flux:button x-show="!userLocation" x-on:click="useMyLocation()" x-bind:disabled="locating" icon="map-pin" class="w-full">
                            <span x-show="!locating">{{ __('Usar mi ubicación') }}</span>
                            <span x-show="locating" x-cloak>{{ __('Ubicando...') }}</span>
                        </flux:button>
                        <flux:button x-show="userLocation" x-cloak x-on:click="clearLocation()" icon="x-mark" class="w-full">
                            {{ __('Quitar ubicación') }}
                        </flux:button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-4">
                    <p x-show="loading" x-cloak class="text-muted text-sm">{{ __('Buscando...') }}</p>

                    <p x-show="searched && !loading && results.length === 0" x-cloak class="text-muted text-sm">
                        {{ __('No encontramos insumos disponibles con esos criterios. Intenta con otra palabra o quita algún filtro.') }}
                    </p>

                    <template x-for="item in results" :key="item.master_item_id + '-' + item.warehouse_id">
                        <div class="card-brutal p-4 flex items-start justify-between gap-4">
                            <div>
                                <p class="font-display font-bold text-ink" x-text="item.item_name"></p>
                                <p class="text-xs text-muted" x-text="item.category_name"></p>
                                <p class="text-sm text-muted mt-1">
                                    <span x-text="item.warehouse_name"></span>
                                    <template x-if="item.zone_name"> — <span x-text="item.zone_name"></span></template>
                                    <template x-if="item.distance_km !== null"> (<span x-text="item.distance_km"></span> km)</template>
                                </p>
                                <template x-if="item.expiry_date">
                                    <p class="text-xs text-muted mt-1">{{ __('Vence') }}: <span x-text="item.expiry_date"></span></p>
                                </template>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-2xl" x-text="item.availability_emoji"></p>
                                <p class="text-xs text-muted" x-text="item.availability_label"></p>
                                <flux:button size="sm" class="mt-2" x-on:click="openContact(item.warehouse_id, item.warehouse_name)">{{ __('Contactar') }}</flux:button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="lg:col-span-1">
                    <div class="card-brutal p-2 sticky top-4" x-data="publicResultsMap()">
                        <div x-ref="mapContainer" class="h-96 rounded-lg"></div>
                    </div>
                </div>
            </div>

            <flux:modal name="contact-unlock-modal" class="max-w-md" x-on:close="turnstileToken = ''; contactResult = null">
                <div class="space-y-6">
                    <flux:heading size="lg">{{ __('Contactar') }} <span x-text="selectedWarehouseName"></span></flux:heading>

                    <template x-if="!contactResult">
                        <div class="space-y-4">
                            <p class="text-sm text-muted">{{ __('Para proteger a nuestras bodegas de spam, confirma que eres una persona antes de ver el contacto.') }}</p>

                            <flux:callout x-show="contactErrorMessage" x-cloak variant="danger" x-text="contactErrorMessage"></flux:callout>

                            <div x-ref="turnstileContainer" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>

                            <div class="flex justify-end gap-3">
                                <flux:button x-on:click="closeContact()">{{ __('Cancelar') }}</flux:button>
                                <flux:button variant="primary" x-on:click="submitContactUnlock()" x-bind:disabled="contactSubmitting || !turnstileToken">
                                    <span x-show="!contactSubmitting">{{ __('Ver contacto') }}</span>
                                    <span x-show="contactSubmitting" x-cloak>{{ __('Verificando...') }}</span>
                                </flux:button>
                            </div>
                        </div>
                    </template>

                    <template x-if="contactResult">
                        <div class="space-y-4">
                            <p class="text-ink"><strong>{{ __('Contacto') }}:</strong> <span x-text="contactResult.contact_person_name"></span></p>
                            <p class="text-ink"><strong>{{ __('Teléfono') }}:</strong> <span x-text="contactResult.contact_phone"></span></p>

                            <div class="flex justify-end gap-3">
                                <flux:button x-on:click="closeContact()">{{ __('Cerrar') }}</flux:button>
                                <flux:button variant="primary" x-on:click="window.open(contactResult.whatsapp_url, '_blank', 'noopener')">
                                    {{ __('Escribir por WhatsApp') }}
                                </flux:button>
                            </div>
                        </div>
                    </template>
                </div>
            </flux:modal>
        </main>

        @fluxScripts
    </body>
</html>
