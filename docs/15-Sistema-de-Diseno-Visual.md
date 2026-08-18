# 🎨 Sistema de Diseño Visual — "Stark Dim"

**Versión:** 1.0
**Fecha:** 18 de agosto de 2026
**Origen:** `docs/fixflow-stark-dim.html` — mockup de referencia de otro producto ("FixFlow", gestión de mantenimiento técnico). Se adopta su **lenguaje visual** (paleta, tipografía, componentes, sombras) como estándar normativo de Donaciones Rolda; no se adopta su marca, copy ni dominio funcional.

> Este documento es la fuente de verdad del diseño visual. Cualquier pantalla nueva o modificada debe seguir estos tokens y componentes. Si una pantalla necesita algo que no está aquí, se extiende este documento antes de improvisar en el código.

---

## 1. Principio rector: rendimiento primero

Este es un sistema para un municipio en emergencia, no una app de escritorio con banda ancha garantizada. Todo el sistema — y **el módulo de censo en particular**, que se opera en campo con conexión intermitente u offline — se diseña bajo estas reglas, en este orden de prioridad:

1. **Cero dependencias de red para lo crítico.** Fuentes autoalojadas (ver §2), sin CDNs de terceros bloqueantes. El censo debe poder pintarse y funcionar completamente offline tras la primera carga (Service Worker cachea CSS + fuentes + JS del wizard).
2. **CSS por encima de JS.** Las sombras duras, los estados hover/active y las transiciones de este sistema son 100% CSS (custom properties + utilidades de Tailwind). No se introducen librerías de animación. Alpine.js (ya en el stack) solo para estado de interfaz, nunca para efectos visuales que CSS ya resuelve.
3. **Sin librerías pesadas nuevas.** Nada de charting libraries de terceros si un `<svg>`/CSS simple alcanza; Leaflet (ya en el stack) se carga perezosamente solo en pantallas que muestran mapa, nunca en el wizard de censo salvo en el paso que necesita el pin manual de GPS.
4. **Tailwind purgado.** v4 ya sólo compila las clases detectadas vía `@source` en `resources/css/app.css` — no agregar hojas de estilo adicionales sueltas ni frameworks CSS extra.
5. **Formularios largos (censo) sin reflow costoso.** Wizard multi-paso: un paso visible a la vez (no todo el DOM del formulario montado y oculto con `display:none` masivo), listas de ítems repetibles (integrantes del hogar) sin animaciones de entrada/salida.

---

## 2. Tipografía

| Uso | Familia | Pesos |
|---|---|---|
| Títulos, labels, botones, badges, nav, valores KPI | **Space Grotesk** | 500, 600, 700, 800 |
| Texto de cuerpo, párrafos, inputs | **Figtree** | 400, 500, 600 |

- Autoalojadas vía `laravel-vite-plugin/fonts` (`bunny()`), igual que ya hacía el starter kit con Instrument Sans — **no** `<link>` a Google Fonts. Esto es lo que permite que el censo funcione sin red tras el primer load.
- Títulos: `letter-spacing: -0.02em` a `-0.04em` según tamaño (más ajustado cuanto más grande).
- Labels/badges/nav: mayúsculas, `letter-spacing: 0.03em–0.1em`, siempre en Space Grotesk 700.

---

## 3. Paleta — solo modo oscuro ("Stark Dim")

La app fuerza `class="dark"` en `<html>` desde el arranque del starter kit — este sistema **no define variante clara**. Si más adelante se necesita un modo claro (p. ej. legibilidad en exteriores bajo sol directo para operadores de campo — riesgo real dado el caso de uso), es una decisión de producto aparte, no una tarea de CSS incremental.

| Token | Valor | Uso |
|---|---|---|
| `--color-canvas` | `#18181A` (Zinc 900 cálido) | Fondo de página |
| `--color-surface` | `#27272A` (Zinc 800) | Tarjetas, sidebar, navbar |
| `--color-surface-2` | `#3F3F46` (Zinc 700) | Headers de tabla, hover, inputs de búsqueda inline |
| `--color-line` | `#52525B` (Zinc 600) | Bordes (siempre 2px, nunca 1px salvo separadores de fila) |
| `--color-ink` | `#E4E4E7` (Zinc 200) | Texto principal |
| `--color-muted` | `#A1A1AA` (Zinc 400) | Texto secundario, labels |
| `--color-primary` | `#EA580C` (Orange 600) | Marca, acciones primarias, foco |
| `--color-primary-dim` | `#C2410C` (Orange 700) | Hover/estado presionado de primario |
| `--color-secondary` | `#65A30D` (Lime 600) | Éxito, completado, delta positivo |
| `--color-warn` | `#F59E0B` (Amber 500) | Advertencia/acento — **no** amarillo puro, deslumbra en pantallas de campo |
| `--color-danger` | `#E11D48` (Rose 600) | Crítico, delta negativo, prioridad alta |
| `--color-info` | `#3B82F6` (Blue 500) | Dato neutro (ej. KPI informativo) |

Un color = un significado. `primary` es siempre acción/marca, nunca "peligro"; `danger` es siempre crítico (coincide con 🔴 CRÍTICO del scoring del Módulo 7 — reutilizar, no inventar un rojo nuevo).

---

## 4. Sombras y bordes — el sello "neobrutalista"

Sin blur, sin degradados de sombra — offset sólido en negro puro. Es lo que le da identidad al sistema y es barato de renderizar (una sola `box-shadow` sólida, sin `filter: blur()`).

| Token | Valor | Uso |
|---|---|---|
| `--shadow-brutal` | `3px 3px 0 0 #000` | Tarjetas, inputs en foco |
| `--shadow-brutal-lg` | `5px 5px 0 0 #000` | Tarjetas en hover, modales |
| `--shadow-brutal-btn` | `4px 4px 0 0 #000` | Botones |
| `--radius-brutal` | `6px` | Radio único del sistema — no usar `rounded-lg`/`rounded-xl` de Tailwind por defecto |

Interacción estándar de botón/tarjeta clicable: `translateY(-2px)` + sombra crece 1-2px en `:hover`; `translateY(1px)` + sombra casi desaparece en `:active`. Da feedback táctil sin JS ni librerías de animación.

---

## 5. Componentes

### 5.1 Botones
- **Primary** (`bg-primary`, texto blanco, borde `--color-canvas`): acción principal de la pantalla (una sola por vista).
- **Secondary** (`bg-surface-2`, borde `--color-line`): acciones secundarias, cancelar.
- **Accent** (`bg-warn`, texto `--color-canvas`): acción urgente/destacada (ej. "Crear y marcar urgente"). Úsalo con moderación — pierde fuerza si aparece en cada pantalla.
- Todos: Space Grotesk 700, mayúsculas, `letter-spacing: 0.02em`, `--shadow-brutal-btn`, radio `--radius-brutal`.

### 5.2 Tarjetas / KPI
- Contenedor `.card`: `bg-surface`, borde 2px `--color-line`, `--shadow-brutal`, header interno `bg-surface-2` con divisor.
- KPI card: barra superior de 3px con el color semántico (`primary`/`secondary`/`warn`/`info`), label en mayúsculas pequeño, valor grande en Space Grotesk 800.

### 5.3 Tablas
- Encabezados en `bg-surface-2`, mayúsculas, `--color-muted`.
- Filas separadas por borde 1px (no 2px — el 2px se reserva para contenedores/tarjetas, si no la tabla se ve sobrecargada).
- Hover de fila: tinte blanco muy sutil (`rgba(255,255,255,0.02)`), nada de sombra ni transform (listas largas — pensar en la tabla de beneficiarios del censo, que puede tener cientos de filas).

### 5.4 Badges y estado
- `badge-open` (azul info), `badge-progress` (ámbar warn), `badge-done` (lime secondary), `badge-urgent`/`badge-critical` (naranja primary / rojo danger).
- Punto de prioridad (`.dot`): 8px, sin borde, color semántico — usar para prioridad de orden/tarea; para **prioridad de vulnerabilidad del Módulo 7** reutilizar los mismos tres niveles (🔴 Crítico = danger, 🟡 Prioritario = warn, 🟢 Normal = secondary) en vez de inventar una cuarta paleta.

### 5.5 Formularios
- Input: `bg-canvas`, borde 2px `--color-line`, `--shadow-brutal` sutil (2px 2px), en foco borde `--color-primary` + sombra del mismo color.
- Label: Space Grotesk 700, mayúsculas, `--color-muted`, 11px.
- **Wizard de censo:** un `<flux:card>` por paso, barra de progreso simple (no librería), botones "Atrás/Siguiente" siempre visibles al fondo del viewport en móvil (el operador censa con el teléfono en mano, no debe hacer scroll para avanzar).

### 5.6 Navegación
- Navbar superior: `bg-surface`, borde inferior 2px, logo con leve rotación (`-1deg` a `-2deg`, detalle de marca, no aplicar a nada más).
- Sidebar: `bg-surface`, borde 2px, ítem activo con borde+sombra del color primario (`box-shadow: 2px 2px 0 0 var(--color-primary)`).
- Donaciones Rolda ya usa layout de sidebar persistente en desktop (no navbar+sidebar combinados como en el mockup de origen) — se mantiene esa estructura de navegación ya implementada con Flux Sidebar, solo se retema visualmente. Cambiar la arquitectura de navegación es una decisión aparte, no parte de este ajuste de diseño.

### 5.7 Login / pantallas de autenticación
- Fondo con grilla decorativa sutil (repeating-linear-gradient 40px, opacidad 0.15) — puramente CSS, sin imagen.
- Tarjeta centrada, borde 3px, `--shadow-brutal-lg` ampliada (7px 7px), radio 10px (única excepción al radio de 6px, por jerarquía visual del punto de entrada al sistema).

---

## 6. Cómo se implementa (mapeo técnico)

- Los tokens de esta tabla viven como Tailwind v4 `@theme` en `resources/css/app.css` (`--color-canvas`, `--color-primary`, `--shadow-brutal`, `--radius-brutal`, etc.) — generan utilidades reales (`bg-canvas`, `shadow-brutal`, `rounded-brutal`) usables directo en Blade.
- Las variables internas de Flux UI (`--color-accent`, `--color-accent-content`, `--color-accent-foreground`) se re-apuntan a la paleta naranja de marca — así los componentes `flux:*` (botones, foco de inputs, badge del logo) heredan el sistema sin tener que sobreescribir cada componente uno por uno.
- Donde Flux no expone la personalización necesaria (sombras duras, bordes de 2px), se agregan clases Tailwind directamente en el `class=""` de cada invocación de componente en las vistas — **no** se editan los stubs internos de `vendor/livewire/flux` (frágil ante actualizaciones del paquete).

## 7. Estado de adopción en las vistas existentes

| Vista | Estado |
|---|---|
| `resources/css/app.css` (tokens base) | ✅ Aplicado |
| `vite.config.js` (fuentes autoalojadas) | ✅ Aplicado |
| `welcome.blade.php` | ✅ Reescrita |
| `layouts/app/sidebar.blade.php` (shell autenticado) | ✅ Aplicado |
| `layouts/auth/simple.blade.php` (login/registro/reset) | ✅ Aplicado |
| `dashboard.blade.php` | ✅ Aplicado |
| Páginas de `settings/*` (perfil, apariencia, seguridad) | ⏳ Heredan el tema global vía Flux automáticamente; revisar visualmente cuando se retomen (no tienen clases hardcodeadas que choquen con los tokens nuevos) |
| Módulos 1-13 (aún no construidos) | Deben nacer ya usando este sistema — no hay reskin pendiente porque no existe UI todavía |

Cuando se implemente el wizard de censo (Módulo 7, tarea "Wizard de censo multi-paso" en Engineering Tasks), debe construirse directamente sobre §5.5 y §1 — no como una pantalla aparte a re-diseñar después.
