<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
<script>
    {{-- El sistema de diseño "Stark Dim" (docs/15-Sistema-de-Diseno-Visual.md) es solo modo oscuro.
         @fluxAppearance por defecto usa 'system' y respeta prefers-color-scheme del navegador/SO,
         lo que hace que Flux quite la clase `dark` en sistemas/navegadores en modo claro y deje
         los componentes flux:* (heading, button, etc.) casi invisibles sobre nuestro fondo oscuro
         fijo. Se fuerza 'dark' explícitamente hasta que exista una variante clara real. --}}
    window.Flux.applyAppearance('dark');
</script>
