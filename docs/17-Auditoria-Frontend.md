# 🔍 Auditoría Técnica Frontend — Donaciones Rolda

**Fecha:** 20 de agosto de 2026
**Rama auditada:** `test` (commit `e0ec103`, tras la fusión de los PRs 1-18)
**Alcance:** 22 vistas Blade, 11 módulos JavaScript, Service Worker, cola offline (IndexedDB/Dexie) y sistema de tokens CSS.

**Ejes evaluados:** funcionalidad y navegación, integridad de datos (BD ↔ front), consistencia visual/accesibilidad, rendimiento y consumo de recursos, responsividad multiplataforma y capacidades offline-first.

> ## ⚠️ Método y límites de este documento
>
> Los hallazgos marcados **[verificado]** se comprobaron **ejecutando código** (`tinker`, conteo real de consultas contra la BD sembrada, `grep` sobre el CSS ya compilado en `public/build/`). Los marcados **[inferido]** se derivan de lectura de código sin ejecución.
>
> **No se midieron Core Web Vitals reales.** No se corrió Lighthouse ni un navegador real contra la instancia de pruebas. Las cifras de rendimiento de este informe son **conteos de consultas SQL medidos**, no LCP/INP/CLS observados. Cualquier número de CWV aquí sería inventado, por eso no se incluye ninguno. Si se necesita esa medición, debe hacerse aparte contra el ambiente EC2 desplegado.
>
> Este documento es un **diagnóstico**, no un registro de cambios aplicados: al momento de escribirlo ninguno de los hallazgos había sido corregido.

> ## 📌 Estado de las correcciones
>
> | Bloque | Estado | Detalle |
> |---|---|---|
> | **Bloque 0** | ✅ **Aplicado** (21-ago-2026) | C-1, C-3 y A-5 cerrados. **C-2 solo en su parte de código**: falta servir el ambiente EC2 por HTTPS, que es tarea de infraestructura. |
> | **Bloque 1** | ✅ **Aplicado** (21-ago-2026) | A-2, A-3 y M-8 cerrados. `/kardex` pasó de 1.346 consultas a 13 con 616 lotes. |
> | Bloque 2 | ⬜ Abierto | C-4, A-1, M-2, M-3, M-4 (y la parte de infraestructura de C-2, de la que dependen) |
> | Bloque 3 | ⬜ Abierto | A-4, M-1, M-5, M-6, M-7, B-1, B-2, B-3 |
>
> Cada hallazgo corregido lleva la marca ✅ en su encabezado de la sección 3. Los que no la llevan siguen abiertos.

---

## 1. Diagnóstico Ejecutivo

| Eje | Puntuación | Lectura |
| :--- | :---: | :--- |
| **Funcionalidad y navegación** | **62 / 100** | Los flujos están completos y probados (244 tests en verde), pero hay tres modos de fallo duro no cubiertos por la suite porque son de navegador, no de servidor. |
| **UX / Consistencia visual** | **58 / 100** | El sistema de diseño "Stark Dim" está aplicado con rigor y coherencia real. Lo que falla es lo que rodea al diseño: estados de carga inexistentes, destello de contenido oculto, mezcla de idiomas. |
| **Rendimiento** | **35 / 100** | N+1 anidado en la pantalla operativa central. Es el eje más débil por un margen amplio. |
| **Soporte Offline** | **45 / 100** | La cola IndexedDB está bien diseñada conceptualmente, pero la app no es instalable como PWA, no hay indicador de red, no hay descarte de elementos irrecuperables y guarda PII en claro. |

### Bloqueadores críticos

1. ✅ **XSS almacenado en el portal público** — el popup del mapa interpolaba nombres de ítem/bodega sin escapar. Vector de ejecución en el navegador de ciudadanos anónimos. *(Corregido, Bloque 0.)*
2. 🟡 **El ambiente EC2 corre sobre HTTP plano** → sin *secure context*: el Service Worker no se registra, `crypto.randomUUID()` no existe y **los formularios de campo se cuelgan al enviar** sin mensaje de error. *(El cuelgue está corregido; falta el HTTPS.)*
3. ✅ **Falta la regla `[x-cloak]{display:none}`** — 28 elementos que deberían estar ocultos eran visibles en el primer pintado de 8 vistas. *(Corregido, Bloque 0.)*
4. **PII de censo sin cifrar en IndexedDB** — documentos, teléfonos y coordenadas GPS en claro en el dispositivo de campo, contra la propia matriz LSPP del proyecto (`08-Matriz-Compliance-Privacy-LSPP.md`).
5. ✅ **N+1 anidado en `/kardex`** — 73 consultas SQL con solo 16 lotes en la base sembrada. *(Corregido, Bloque 1.)*
6. ✅ **El dashboard post-login es un placeholder** que mostraba `0` en todos sus indicadores y el texto "todavía no hay módulos operativos", contradiciendo la base de datos. *(Corregido, Bloque 0.)*

---

## 2. Matriz de Auditoría por Vista / Módulo

| Vista / Módulo | Estado Funcional | Mapeo de Datos (BD/Front) | Rendimiento & Carga | Adaptabilidad Móvil | Soporte Offline |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Portal público** (`/`) | ⚠️ **Fallo silencioso de red**: si `fetch` lanza, no hay `catch` → muestra "No encontramos insumos" cuando el problema real es la conexión | ✅ XSS corregido — el popup se arma con nodos del DOM | ⚠️ Sin `AbortController`: respuestas fuera de orden pisan resultados nuevos | ✅ Buena (`grid-cols-1 lg:grid-cols-3`); ⚠️ `autofocus` abre el teclado al entrar | ➖ N/A por diseño (requiere red) |
| **Login / Registro / Auth** | ✅ OK | ✅ OK | ✅ Ligero | ✅ OK | ➖ N/A |
| **Dashboard** | ✅ Corregido — conteos reales por rol | ✅ Corregido — agregados en SQL | ✅ Trivial | ✅ OK | ➖ |
| **Kardex — índice** | ✅ OK | ✅ Corregido — `isExpiringSoon()` con signo explícito | ✅ Corregido — 13 consultas constantes, paginado de a 25 | 🔴 Tabla de 7 columnas dentro de `overflow-hidden` → **se recorta, no scrollea** | ➖ |
| **Kardex — entrada / salida / traslado** | ✅ Cuelgue corregido (respaldo de UUID); ⚠️ "Solicitar ítem nuevo" (Livewire) muere en silencio sin red, en una pantalla rotulada "funciona sin conexión" | ⚠️ `quantity` viaja como cadena; la guarda `!quantity` no bloquea `"0"` | ✅ Ligero | ✅ `max-w-2xl` correcto | ✅ **Lo mejor de la app**: cola Dexie + reintento; 🔴 payload sin cifrar |
| **Kardex — ficha / conteo / vencimientos** | ✅ OK | ✅ OK | ✅ Corregido — saldo precargado | 🔴 Tabla recortada (`overflow-hidden`) | ➖ |
| **Censo (wizard 6 pasos)** | ✅ Mismo cuelgue corregido; ✅ fallback GPS → pin manual bien resuelto | ⚠️ Payload plano, sin validación de cliente entre pasos | ✅ Fuga de memoria corregida (`destroy()`) | ✅ Diseñado para móvil | ✅ Cola + `syncNow()`; 🔴 **documentos y teléfonos en claro en IndexedDB** |
| **Beneficiarios — índice** | ✅ OK, paginado (20) | ✅ Correcto | ⚠️ Sin `wire:loading` en 3 filtros `.live` → congelación muda | 🔴 Tabla de 6 columnas recortada | ➖ N/A (PII, decisión correcta) |
| **Beneficiarios — detalle / perfil** | ✅ OK | ⚠️ `chronicConditions`/`currentSymptoms` **sin regla de validación** → texto ilimitado hacia una columna TEXT | ⚠️ `save()` = 2 `update()` + `refresh()` + `fresh()` **sin transacción**: si falla el motor de recomendaciones, el score queda persistido y las recomendaciones no | ✅ Formulario de una columna, correcto | ➖ N/A (correcto) |
| **Entregas — registro** | ✅ OK, con `wire:loading` (1 de las solo 4 vistas que lo tienen) | ✅ Correcto | ✅ Corregido — saldo precargado en una consulta | ✅ OK | ➖ N/A (correcto y documentado) |
| **Entregas — listado** | ✅ OK | ✅ OK | ✅ Corregido | 🔴 Tabla recortada | ➖ |
| **Admin — usuarios / ítems / bodegas** | ⚠️ Sin `wire:loading` ni `disabled`: doble clic en "Enviar solicitud" crea **dos solicitudes duplicadas** | ✅ Correcto | ✅ Volumen bajo | 🔴 Tabla recortada; ⚠️ botones `size="sm"` bajo el mínimo táctil de 48px | ➖ |
| **Ajustes / 2FA** | ✅ OK (starter kit, buena accesibilidad: `aria-expanded`, `aria-controls`, `role="list"`) | ✅ OK | ✅ OK | ✅ OK | ➖ |

---

## 3. Hallazgos Críticos y Puntos de Quiebre

### ✅ [Crítica] C-1 · XSS almacenado en el portal público — **[verificado — CORREGIDO]**

**Archivo:** `resources/js/public/results-map.js:57`

```js
.bindPopup(`<strong>${warehouse.name}</strong><br>${itemsList}`);
```

`bindPopup()` interpreta la cadena recibida como HTML. `warehouse_name` e `item_name` llegan crudos desde `app/Http/Controllers/Public/SearchController.php` (líneas 106 y 137).

**Por qué importa ahora:** desde el Módulo 4 recién construido, **cualquier operador puede crear el nombre de un ítem** mediante "Solicitar ítem nuevo". Un nombre como `<img src=x onerror=...>` que pase la aprobación se ejecuta en el navegador de **todo ciudadano anónimo** que use el mapa del portal público.

La asimetría lo confirma: las mismas cadenas **sí** se escapan en las tarjetas de resultados (`x-text`); únicamente el popup del mapa era vulnerable.

> **✅ Corregido (21-ago-2026).** El popup se arma con nodos del DOM y `textContent`. Como defensa en profundidad, `MasterItem::nameRules()` rechaza `<` y `>` en los dos puntos de entrada del campo: la solicitud desde el Kardex y la corrección del admin al aprobar (que no validaba nada). Cubierto por `tests/Feature/MasterItems/ItemNameSanitizationTest.php` y por los casos QA 10.4 y 10.5.

### 🟡 [Crítica] C-2 · Sin *secure context*, la capa offline no existe y los formularios se cuelgan — **[verificado — CORREGIDO A MEDIAS]**

`README.md:165` fija `APP_URL=http://<IP-pública>` para el ambiente EC2. En un contexto no seguro (HTTP sobre IP, que no sea `localhost`):

- **`navigator.serviceWorker.register()` falla**, y el `.catch()` de `resources/js/app.js:27` **la silencia por completo**. Sin Service Worker no hay caché del shell: la aplicación entera es inutilizable sin red.
- **`crypto.randomUUID()` es `undefined`.** En `resources/js/kardex/entry-form.js:40` y `resources/js/census/wizard.js:180`, `buildPayload()` se invoca **fuera del bloque `try`**, así que la excepción escapa, el `finally` nunca se ejecuta y **`submitting` queda en `true` para siempre**: el botón se congela en "Guardando…", sin mensaje de error, sin encolar nada offline, y la captura se pierde.
- **`navigator.geolocation` queda bloqueado.** El censo cae correctamente al pin manual sobre el mapa (bien resuelto); el portal público simplemente no responde al botón "Usar mi ubicación".

**Nada de esto lo detecta la suite Pest:** son fallos de navegador, no de servidor.

> **🟡 Corregido a medias (21-ago-2026).** Lo que era código ya está: `newClientUuid()` (`resources/js/offline/uuid.js`) da respaldo cuando `crypto.randomUUID` no existe, el payload se arma dentro del `try` en los 4 formularios de campo, y el fallo del Service Worker deja de silenciarse.
>
> **Sigue abierto lo importante:** servir el ambiente EC2 por **HTTPS**. Es tarea de infraestructura, no de código. Sin eso, el Service Worker, la Cache API y la Geolocation API siguen deshabilitados por más que la app ya no se cuelgue — y además **bloquea el hallazgo C-4**, porque cifrar la PII con WebCrypto también exige contexto seguro.

### ✅ [Crítica] C-3 · Falta la regla `[x-cloak]` — **[verificado — CORREGIDO]**

Confirmado ausente en `resources/css/app.css`, en `vendor/livewire/flux/dist/flux.css` y **en el CSS ya compilado en `public/build/assets/`**. Alpine no inyecta esa regla; es responsabilidad del proyecto declararla.

Efecto en **8 vistas / 28 elementos**: al entrar a `/kardex/entradas` el usuario veía simultáneamente el panel de éxito *"Entrada registrada"*, el formulario, el callout de error vacío y el texto *"Guardando…"* — todo junto — hasta que Alpine arrancaba y los ocultaba. En el portal público destellaban a la vez "Buscando…", "No encontramos insumos" y el botón de ubicación.

> **✅ Corregido (21-ago-2026).** Regla declarada en `resources/css/app.css`, verificada en el CSS compilado (`[x-cloak]{display:none!important}`). Caso QA 10.3.

### 🔴 [Crítica] C-4 · PII de censo en claro en IndexedDB — **[verificado]**

`resources/js/offline/queue.js:22` persiste el payload tal cual llega. Ese payload incluye `head_document_number`, `phone`, el `document_number` de cada integrante, nombres completos y coordenadas GPS — **exactamente los campos que CLAUDE.md exige cifrar en AES-256**.

En el servidor sí se cifran; en el dispositivo de campo (compartido, susceptible de robo o extravío, sin MDM) quedan legibles vía DevTools, y **permanecen indefinidamente** si el envío nunca prospera (ver A-1). Es una brecha frente a la Ley 1581/2012 en el punto exacto donde el proyecto declara cumplimiento.

### 🔴 [Alta] A-1 · La cola offline reintenta indefinidamente, sin descarte ni aviso — **[verificado]**

`resources/js/offline/sync.js:63-65`: si el servidor responde con `status !== 'ok'` por un rechazo de negocio (lote duplicado, ítem inválido), el elemento se cuenta como `failed` pero **nunca se elimina ni se marca**. Se reintenta en cada carga de página y en cada evento `online`, para siempre. El encuestador nunca se entera de que su captura fue rechazada ni por qué.

Además no hay guarda de concurrencia: `watchConnectivity()` puede disparar un `flushQueue()` mientras otro sigue en vuelo.

### ✅ [Alta] A-2 · N+1 anidado en `/kardex` — **[verificado, medido — CORREGIDO]**

`StockEntry::availableQuantity()` (`app/Models/StockEntry.php:106`) ejecuta `$this->exits()->sum(...)`: **una consulta por lote, siempre, sin posibilidad de eager loading tal como está escrito**. `MasterItem::totalAvailableQuantity()` lo llama en bucle → N×M. La vista lo invoca **dos veces** (en la propiedad computada y otra vez en el callout de la línea 104).

Medición real sobre la base de datos sembrada:

```
lotes: 16 · ítems: 11  →  73 consultas para pintar /kardex una sola vez
```

Sin paginación y con `wire:model.live` en el filtro de bodega, todo esto se repetía íntegro en cada cambio de filtro.

> **✅ Corregido (21-ago-2026).** Tres cambios: el scope `StockEntry::withAvailableQuantity()` precarga lo despachado en la misma consulta (opt-in: las acciones de escritura siguen leyendo el saldo fresco dentro de su transacción); `KardexAlertsService` resuelve los tres avisos con un agregado SQL cada uno en vez de recorrer ítems; y la tabla se pagina de a 25.
>
> Medición al mismo volumen, con 616 lotes y 131 ítems:
>
> | | Consultas | Tiempo |
> |---|---:|---:|
> | Antes | 1.346 | 1.732 ms |
> | Después | **13** | **134 ms** |
>
> El conteo ya no depende del volumen: son 13 consultas tanto con 16 lotes como con 616. `tests/Feature/Kardex/KardexQueryPerformanceTest.php` lo fija como prueba de regresión y verifica además que los agregados dan exactamente lo mismo que el cálculo lote por lote que reemplazan.
>
> **Nota sobre la recomendación original de cachear en Redis con TTL 5m: se descartó a propósito.** Con los agregados ya resueltos, el ahorro sería marginal y el costo alto — los avisos de stock quedarían obsoletos justo después de registrar una entrada o una salida, que es cuando se consultan.

### ✅ [Alta] A-3 · Toda fecha de vencimiento futura se pinta como urgente — **[verificado — CORREGIDO]**

`resources/views/pages/kardex/⚡index.blade.php:171`:

```php
$entry->expiry_date->isPast() || $entry->expiry_date->diffInDays(now()) <= 30
```

En Carbon 3 el resultado de `diffInDays()` es **con signo**:

```
expiry en 200 días → diffInDays(now()) = -199.99  →  se pinta rojo: SÍ
```

Todos los lotes con vencimiento futuro salen en rojo y negrita. La señal visual de urgencia queda convertida en 100% ruido.

El proyecto ya usa la forma correcta en `app/Console/Commands/Kardex/UpdateStockEntryStatuses.php:57` (`diffInDays($fecha, false)`), así que era un desliz aislado en una vista, no un malentendido de fondo.

> **✅ Corregido (21-ago-2026).** La lógica se movió a `StockEntry::isExpiringSoon()`, con el signo explícito y el umbral configurable. `tests/Feature/Kardex/StockEntryExpiryTest.php` fija los bordes (30 y 31 días, ya vencido, sin fecha) para que no vuelva.

### 🔴 [Alta] A-4 · Ninguna tabla es usable en móvil — **[verificado]**

Las **10** tablas de datos del proyecto van dentro de `<div class="card-brutal overflow-hidden">`. `overflow-hidden` **recorta**; no genera scroll. Por debajo de 480px las columnas de la derecha (incluido el botón de acción de cada fila) quedan inalcanzables, sin scroll horizontal ni vista alternativa en tarjetas.

Afecta a Kardex, Beneficiarios, Entregas, Admin y Vencimientos.

### ✅ [Alta] A-5 · El dashboard contradice la base de datos — **[verificado — CORREGIDO]**

`resources/views/dashboard.blade.php` tenía `0` literal y hardcodeado en las tres tarjetas de indicadores ("Módulos activos", "Bodegas registradas", "Beneficiarios censados") y el texto *"Todavía no hay módulos operativos"* — con 6 módulos construidos, bodegas sembradas y hogares censados presentes en la BD. Es la primera pantalla que ve cualquiera de los 6 roles tras iniciar sesión.

> **✅ Corregido (21-ago-2026).** Ahora es una página Livewire (`resources/views/pages/⚡dashboard.blade.php`) con conteos agregados en SQL, y las secciones filtradas por lo que el rol puede ver. Cubierto por `tests/Feature/DashboardTest.php` y los casos QA 10.1 y 10.2.

### 🟡 [Media] M-1 · Estados de carga prácticamente ausentes

Solo **4 de 22** vistas usan `wire:loading`. Cero skeletons en todo el proyecto. Cada filtro `.live` (Kardex, y los 3 de Beneficiarios) congela la interfaz en silencio durante el roundtrip al servidor, sin ninguna indicación de actividad.

### 🟡 [Media] M-2 · No es una PWA instalable — **[verificado]**

No existe `manifest.json`, ni `<link rel="manifest">`, ni `theme-color`, ni `apple-mobile-web-app-capable`. La aplicación se documenta como PWA offline-first pero no puede añadirse a la pantalla de inicio ni arrancar en modo standalone.

### 🟡 [Media] M-3 · Sin indicador de estado de red ni contador de pendientes

No hay listener del evento `offline` en ninguna parte, ni banner de estado. `pendingCount()` está exportado en `resources/js/offline/queue.js:39` y **no se usa en ningún lado**: no existe forma de que el encuestador sepa que tiene capturas sin enviar. Además `navigator.onLine` devuelve `true` con WiFi conectado pero sin salida a internet, un escenario habitual en campo.

### 🟡 [Media] M-4 · Service Worker demasiado ingenuo

`public/sw.js` cachea **toda** respuesta GET exitosa, incluidas páginas autenticadas con PII de censo, sin tope de tamaño, sin evento `install`/precache, sin `skipWaiting`/`clients.claim` y sin página de respaldo offline (navegar a una ruta nunca visitada devuelve `undefined` a `respondWith`, produciendo un error de red crudo). El caché sobrevive al cierre de sesión.

### 🟡 [Media] M-5 · Envío duplicado en modales Livewire

`requestNewItem` y los botones de aprobar/rechazar del panel de administración no tienen `wire:loading.attr="disabled"`. Un doble clic genera dos solicitudes duplicadas en la cola de revisión.

### 🟡 [Media] M-6 · Turnstile se duplica al cerrar con ESC

`resources/views/pages/public/search.blade.php:158` define `x-on:close`, que limpia el token pero **no llama a `window.turnstile.remove()`** (eso solo ocurre dentro de `closeContact()`). Cerrar el modal con ESC o clic en el backdrop y volver a abrirlo apila un segundo widget de captcha en el mismo contenedor.

### 🟡 [Media] M-7 · Carrera de resultados en el buscador público

`runSearch()` no usa `AbortController` ni una guarda de secuencia: con el debounce de 350ms y una red lenta, la respuesta de una búsqueda anterior puede pisar la de una posterior.

### ✅ [Media] M-8 · Fugas de memoria en los mapas Leaflet — **CORREGIDO**

Ni `resources/js/public/results-map.js` ni `resources/js/census/map.js` destruían la instancia del mapa ni retiraban sus listeners (`window.addEventListener` dentro de `init()`, `map.on('click')`). El censo es alcanzable mediante `wire:navigate`, así que cada ida y vuelta a esa pantalla dejaba en memoria un mapa completo con sus capas de tiles.

> **✅ Corregido (21-ago-2026).** Ambos componentes Alpine implementan `destroy()`: retiran el listener global y llaman a `map.remove()`.

### 🟢 [Baja] B-1 · Interfaz mezclada inglés/español

No existe el directorio `lang/`. Con `APP_LOCALE=es`, `__()` devuelve la clave literal: el sidebar muestra **"Platform"**, **"Dashboard"**, **"Settings"** y **"Log out"** en medio de una interfaz íntegramente en español.

### 🟢 [Baja] B-2 · Landmarks y navegación por teclado

El layout autenticado no tiene `<main>` ni *skip link* — hay que tabular todo el sidebar en cada página. Las celdas `<th>` no declaran `scope="col"`. (El módulo de ajustes heredado del starter kit, en cambio, está correctamente anotado.)

### 🟢 [Baja] B-3 · Áreas táctiles

22 botones `size="sm"` en las columnas de acción de las tablas quedan por debajo del mínimo recomendado de 48×48px para uso táctil.

### 🟢 [Baja] B-4 · `nearestZoneLabel` sin distancia máxima

Alguien que busque desde Cali recibe como etiqueta el nombre de un barrio de Roldanillo. Conviene un umbral (~15km) y caer a "Tu ubicación" por encima de él.

---

## 4. Plan de Acción y Mejoras Recomendadas

### Bloque 0 — ✅ Aplicado el 21-ago-2026 (salvo el TLS)

1. **C-1 · Cerrar el XSS.** Construir el popup vía DOM, no por concatenación de cadenas:

   ```js
   const el = document.createElement('div');
   const title = document.createElement('strong');
   title.textContent = warehouse.name;
   el.append(title);
   warehouse.items.forEach((item) => {
       el.append(document.createElement('br'));
       el.append(document.createTextNode(`${item.availability_emoji} ${item.item_name}`));
   });
   marker.bindPopup(el);
   ```

   Complementar con validación del campo `name` en `RequestNewMasterItemAction` y `ApproveMasterItemAction`.

2. **C-3 · Añadir la regla que falta** a `resources/css/app.css`. Una línea que arregla 8 vistas:

   ```css
   [x-cloak] { display: none !important; }
   ```

3. **C-2 · Poner TLS en EC2** (Caddy o nginx + Let's Encrypt delante de Sail; `docs/13-Diagramas-Arquitectura.md` ya contempla Cloudflare para producción). Mientras tanto, blindar el código de todos modos: mover `buildPayload()` **dentro** del `try` y dar respaldo a la generación de UUID:

   ```js
   const uuid = crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
   ```

   Y dejar de silenciar el fallo del Service Worker: registrar el error y mostrar un aviso de "modo sin conexión no disponible".

4. **A-5 · Cablear el dashboard** a consultas reales (bodegas activas, hogares censados, lotes disponibles, alertas abiertas) o retirarlo del flujo hasta que exista.

### Bloque 1 — ✅ Aplicado el 21-ago-2026

5. **A-2 · Eliminar el N+1.** Usar un agregado en lugar de una consulta por fila:

   ```php
   StockEntry::withSum('exits as released_total', 'quantity_released')
   ```

   y que `availableQuantity()` use `released_total` cuando esté cargado. Reescribir `totalAvailableQuantity()` como agregado SQL único. Paginar `/kardex` (25 filas) y calcular los tres callouts de alerta en **una** consulta agregada, cacheada en Redis con el prefijo `donaciones:` que ya define la arquitectura (`kardex:alerts:{warehouse}`, TTL 5m). Objetivo: pasar de 73 consultas a menos de 10.

6. **M-8 · Liberar los mapas.** Devolver la instancia desde `initFallbackMap()` y añadir `destroy()` en los componentes Alpine:

   ```js
   destroy() {
       window.removeEventListener('public-search:results-updated', this.onResults);
       this.map?.remove();
   }
   ```

7. **A-3 · Corregir el signo** de Carbon y extraer la lógica a un método del modelo (`StockEntry::isExpiringSoon()`), con un test que fije el comportamiento para que no reaparezca.

### Bloque 2 — Offline e IndexedDB (~3 días) — ⬜ abierto

8. **C-4 · Cifrar el payload antes de encolarlo.** Usar WebCrypto (AES-GCM) con una clave derivada de la sesión del encuestador y guardar solo el ciphertext en Dexie. Requiere *secure context*, así que **depende del punto 3** — otra razón para que TLS sea prioridad.
9. **A-1 · Reintentos acotados.** Añadir `attempts` y `last_error` al esquema de `sync_queue` (subir a `db.version(2)`), mover el elemento a `status: 'failed'` tras N intentos o ante un rechazo de negocio, y exponer una bandeja de "capturas rechazadas" con el motivo y opción de editar y reenviar. Añadir un mutex (`let flushing = false`) para impedir flushes concurrentes.
10. **M-3 · Indicador global de red y de cola.** Componente Alpine persistente en el layout: escucha `online`/`offline`, consulta `pendingCount()` y muestra `● Sin conexión — 3 capturas pendientes`. Sustituir la confianza en `navigator.onLine` por un ping ligero a `/up`.
11. **M-2 · Manifest y meta tags.** Crear `public/manifest.json` con iconos maskable, `theme-color: #18181A`, `display: standalone` y `start_url: /kardex`.
12. **M-4 · Endurecer el Service Worker.** Evento `install` con precache del shell, `skipWaiting`/`clients.claim`, **excluir del caché las rutas autenticadas con PII** (`/censo/*`, `/beneficiarios/*`) — cachear solo el HTML del formulario vacío, nunca respuestas con datos —, limpiar el caché al cerrar sesión y añadir una página de respaldo offline.

### Bloque 3 — UI/UX y accesibilidad (~2 días) — ⬜ abierto

13. **A-4 · Envolver las 10 tablas** en `overflow-x-auto` y valorar una variante de tarjetas apiladas por debajo del breakpoint `sm:` para Kardex y Beneficiarios, que son las que se consultan en campo.
14. **M-1 · Estados de carga.** `wire:loading.class="opacity-50"` en los contenedores de tabla y `wire:loading.attr="disabled"` en todos los botones de acción — resuelve de paso **M-5**.
15. **M-6 / M-7 · Turnstile y carrera de resultados.** Unificar la limpieza del widget en un único `closeContact()` invocado también desde `x-on:close`; añadir `AbortController` a `runSearch()` y un `catch` que distinga "sin conexión" de "sin resultados".
16. **B-1 · Crear `lang/es/`** (o traducir directamente en el Blade las 4 claves heredadas del starter kit).
17. **B-2 / B-3 · Accesibilidad.** `<main id="contenido">` + skip link en `resources/views/layouts/app/sidebar.blade.php`, `scope="col"` en las `<th>`, y subir los botones de acción de tabla a `size="base"` en breakpoints móviles.

---

## 5. Conclusión

El código tiene una calidad de fondo notable: las decisiones de arquitectura están documentadas donde se toman, la cola offline es conceptualmente correcta, el sistema de diseño se aplica con disciplina y los 244 tests de la suite son reales y pasan.

**Ninguno de los hallazgos críticos es un problema de diseño de la aplicación.** Todos son huecos en la capa navegador — contexto seguro, CSS de Alpine, escapado en el DOM, ciclo de vida de objetos JS —, precisamente donde la suite Pest no llega.

Ese es el hilo conductor del informe completo, y sugiere la contramedida estructural: **el proyecto necesita cobertura de navegador** (Pest v4 browser testing o Laravel Dusk), aunque sea sobre 5 flujos críticos. Las tres criticidades más graves habrían salido en la primera ejecución.

### Orden de ataque sugerido

| Prioridad | Bloque | Esfuerzo | Cierra |
|---|---|---|---|
| ~~1~~ ✅ | Bloque 0 | ~1 día | C-1, C-3, A-5 cerrados; C-2 solo en código (falta el HTTPS del ambiente) |
| ~~2~~ ✅ | Bloque 1 | ~2 días | A-2, A-3, M-8 cerrados |
| 3 | Bloque 2 | ~3 días | C-2 (completo), C-4, A-1, M-2, M-3, M-4 |
| 4 | Bloque 3 | ~2 días | A-4, M-1, M-5, M-6, M-7, B-1, B-2, B-3 |

El Bloque 0 era el único urgente y ya está aplicado: cerró el XSS del portal público, que era el único hallazgo con exposición a usuarios anónimos.

Con el Bloque 1 también aplicado, la acción pendiente de mayor palanca es **poner HTTPS en el ambiente EC2**: no es código, cierra lo que queda de C-2 y desbloquea el Bloque 2 completo (cifrar la PII de la cola offline necesita WebCrypto, que exige contexto seguro).
