# 🧪 Plan de Pruebas QA — Funcionalidades en construcción

**Alcance de esta versión:** Módulo 2 (Autenticación y Roles), el Formulario de Encuestas — Censo de Hogares (Fase 1 de triaje + registro de integrantes) y Módulo 3 (Kardex/Inventario de centros de acopio). Se actualiza a medida que se agregan módulos nuevos.

Este documento es para quien haga de **QA**: cada caso trae los pasos exactos a ejecutar y la respuesta que debes esperar. Marca **Cumple** o **No cumple** en cada caso y anota cualquier diferencia en Observaciones — no interpretes, compara el resultado real contra el esperado tal como está escrito.

---

## 1. Antes de empezar

### 1.1 Ambiente a probar

Usa el ambiente de pruebas en EC2 (ver `README.md`, sección 5) si ya está desplegado; si no, un entorno local con Sail sirve igual (`sail up -d`, ver `README.md` sección 2). Anota aquí qué ambiente usaste, con fecha y commit/rama:

| Campo | Valor |
|---|---|
| Ambiente (local / EC2 pruebas) | |
| URL | |
| Rama / commit | |
| Fecha de la prueba | |
| Persona que probó | |

### 1.2 Cuentas necesarias

| Cuenta | Cómo obtenerla |
|---|---|
| Admin | `admin@donaciones-rolda.test` / `AdminRolda#2026` (seed de `DatabaseSeeder`, ver `README.md` sección 3). Solo existe si el ambiente corrió `sail artisan migrate:fresh --seed`. |
| Operador | Regístrala tú mismo en el Caso 2.1 y apruébala como admin en el Caso 2.3 — no viene precargada. |
| Donante (o cualquier rol sin acceso de campo) | Regístrala igual que la de operador; apruébala como admin asignándole el rol **Donante**. Se usa para confirmar que el RBAC bloquea correctamente. |

**Para los casos de Kardex (sección 4):** el operador solo puede registrar entradas/salidas en las bodegas que tiene asignadas — hoy esa asignación no tiene pantalla propia, así que pídele a alguien de desarrollo que la cree por ti (`WarehouseAssignment::create(['user_id' => ..., 'warehouse_id' => ...])` vía `sail artisan tinker`, o revisa si ya corrió el seed de bodegas de ejemplo `KardexDemoSeeder` — "Bodega Centro" y "Bodega Guayabal"). Un Admin o Coordinador no necesita esta asignación: opera cualquier bodega activa.

### 1.3 Cómo marcar cada caso

- **Cumple**: el resultado real coincide exactamente con el "Resultado esperado".
- **No cumple**: no coincide, aunque sea en un detalle (texto distinto, no redirige, error en consola, etc.). Escribe qué pasó realmente en Observaciones.
- Si un caso depende de que otro haya cumplido antes (por ejemplo, necesitas la cuenta de operador aprobada del Caso 2.3 para probar el módulo de encuestas), respeta el orden de la tabla.

---

## 2. Módulo 2 — Autenticación y Roles

### Caso 2.1 — Registro de un nuevo usuario

| Paso | Acción |
|---|---|
| 1 | Abre la URL del ambiente en una ventana de incógnito (sin sesión iniciada). |
| 2 | Ve a la pantalla de registro (`/register`). |
| 3 | Completa nombre, un correo que no exista todavía, y una contraseña. Envía el formulario. |

**Resultado esperado:** la cuenta se crea y quedas autenticado, pero en vez del dashboard normal ves una pantalla de "cuenta pendiente de aprobación" (ruta `/account/pending`) — no puedes navegar al resto de la aplicación todavía.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 2.2 — Un usuario pendiente no puede usar el sistema

| Paso | Acción |
|---|---|
| 1 | Con la sesión del usuario recién registrado (Caso 2.1) todavía activa, intenta ir directo a `/dashboard` escribiendo la URL. |

**Resultado esperado:** te devuelve a `/account/pending`, no te deja ver el dashboard. Desde esa pantalla sí puedes cerrar sesión.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 2.3 — Panel de aprobación: aprobar un usuario

| Paso | Acción |
|---|---|
| 1 | Cierra la sesión del usuario pendiente e inicia sesión como Admin. |
| 2 | Ve a **Administración → Usuarios pendientes** en el menú lateral. |
| 3 | Verifica que el usuario del Caso 2.1 aparece en la lista. |
| 4 | En la columna "Rol a asignar", selecciona **Operador de campo**. |
| 5 | Haz clic en **Aprobar**. |

**Resultado esperado:** aparece un mensaje de confirmación (toast verde) con el nombre del usuario y el rol asignado. El usuario desaparece de la lista de pendientes.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 2.4 — El usuario aprobado ya puede entrar

| Paso | Acción |
|---|---|
| 1 | Cierra sesión de Admin. Inicia sesión con el correo/contraseña del usuario del Caso 2.1. |

**Resultado esperado:** entras directo al dashboard normal, ya no aparece la pantalla de "pendiente". En el menú lateral ves la opción **Censo de hogares** (porque su rol es Operador).

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 2.5 — Panel de aprobación: rechazar un usuario

| Paso | Acción |
|---|---|
| 1 | Regístrate con una segunda cuenta nueva (correo distinto), ciérrale la sesión. |
| 2 | Como Admin, ve a **Usuarios pendientes** y haz clic en **Rechazar** para esa cuenta. Confirma el diálogo de confirmación. |
| 3 | Intenta iniciar sesión con las credenciales de esa cuenta rechazada. |

**Resultado esperado:** el usuario desaparece de la lista de pendientes al rechazarlo. Al intentar iniciar sesión después, las credenciales siguen siendo válidas (la contraseña es correcta) pero la cuenta no queda activa — no debe llevarte al dashboard normal.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 2.6 — Login con contraseña incorrecta

| Paso | Acción |
|---|---|
| 1 | En la pantalla de login, usa el correo del Admin con una contraseña incorrecta. |

**Resultado esperado:** mensaje de error de credenciales inválidas, no te deja entrar.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 2.7 — RBAC: un rol sin permiso no puede entrar a una pantalla de otro rol

| Paso | Acción |
|---|---|
| 1 | Registra y aprueba (como Admin) una tercera cuenta con rol **Donante**. |
| 2 | Inicia sesión con esa cuenta de Donante. |
| 3 | Intenta ir directo a `/admin/usuarios-pendientes` escribiendo la URL. |
| 4 | Intenta ir directo a `/censo/nuevo` escribiendo la URL. |

**Resultado esperado:** en ambos casos (paso 3 y 4) el sistema responde con acceso denegado (error 403) o una página de error — no debe mostrarte el contenido de esas pantallas. Tampoco deben aparecer esos enlaces en el menú lateral de esta cuenta.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 2.8 — Auditoría de accesos

Este caso requiere acceso a la base de datos (pídeselo a alguien de desarrollo si no lo tienes) o a los logs de la aplicación.

| Paso | Acción |
|---|---|
| 1 | Inicia sesión con cualquier cuenta activa. |
| 2 | Pide que alguien de desarrollo consulte la tabla `audit_logs` filtrando por tu usuario y `action = 'user_login'`, ordenado por fecha descendente. |

**Resultado esperado:** existe un registro nuevo con la fecha/hora de tu inicio de sesión, tu dirección IP y el user agent del navegador que usaste.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

---

## 3. Formulario de Encuestas — Censo de Hogares (Fase 1)

Usa la cuenta de **Operador** aprobada en el Caso 2.3/2.4 para todos los casos de esta sección, salvo que se indique lo contrario.

### Caso 3.1 — Acceso al formulario según el rol

| Paso | Acción |
|---|---|
| 1 | Con la cuenta de Operador, verifica que **Censo de hogares** aparece en el menú lateral y que al hacer clic carga el formulario (`/censo/nuevo`). |
| 2 | Cierra sesión, entra con la cuenta de Donante del Caso 2.7 y verifica que esa opción **no** aparece en el menú, y que escribir la URL a mano da error de acceso denegado. |

**Resultado esperado:** Operador entra sin problema; Donante no ve la opción ni puede acceder por URL directa.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.2 — Consentimiento bloqueante: rechazo

| Paso | Acción |
|---|---|
| 1 | En el formulario (paso 1, "Consentimiento informado"), haz clic en **No autoriza**. |
| 2 | Observa qué pasa con el botón "Continuar". |

**Resultado esperado:** el botón "Continuar" queda deshabilitado (no puedes avanzar) mientras la respuesta sea "No autoriza". No debe pedirte ningún dato personal después de esto.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.3 — Consentimiento: aceptación y avance normal

| Paso | Acción |
|---|---|
| 1 | Haz clic en **Sí autoriza**. |
| 2 | Completa "¿Autoriza el registro de menores?", nombre de quien autoriza y parentesco. |
| 3 | Haz clic en **Continuar**. |

**Resultado esperado:** avanzas al paso 2 ("Ubicación y jefe de hogar") sin errores.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.4 — Captura de GPS

| Paso | Acción |
|---|---|
| 1 | En el paso 2, haz clic en **Capturar GPS**. |
| 2 | Si el navegador pide permiso de ubicación, acéptalo. |

**Resultado esperado:** aparece el texto "Ubicación capturada" junto con un valor de precisión en metros. Si rechazas el permiso o tu dispositivo no tiene GPS, en vez de ese texto debe aparecer un mapa para marcar el punto manualmente (ver Caso 3.5).

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.5 — Mapa manual cuando falla el GPS

| Paso | Acción |
|---|---|
| 1 | Deniega el permiso de ubicación del navegador (o pruébalo en un dispositivo/navegador sin GPS) y haz clic en **Capturar GPS**. |
| 2 | Verifica que aparece un mapa centrado en Roldanillo. |
| 3 | Haz clic en cualquier punto del mapa. |

**Resultado esperado:** aparece un mapa navegable (con calles, de OpenStreetMap). Al hacer clic, se coloca un marcador en ese punto y el estado pasa a "ubicación capturada" con los datos de esa posición.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.6 — Completar hogar, vivienda e integrantes

| Paso | Acción |
|---|---|
| 1 | Completa el resto del paso 2 (jefe de hogar) y avanza al paso 3 (vivienda y servicios). Completa todos los campos obligatorios y avanza. |
| 2 | En el paso 4 ("Composición del hogar"), llena los conteos y haz clic en **Agregar integrante** dos veces. |
| 3 | Llena los datos de cada integrante agregado. Haz clic en **Quitar** en uno de ellos. |

**Resultado esperado:** cada clic en "Agregar integrante" suma una tarjeta nueva con sus propios campos; "Quitar" elimina solo esa tarjeta y no afecta las demás. Puedes seguir al paso 5 sin errores.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.7 — Pregunta condicional de seguridad alimentaria (rCSI)

| Paso | Acción |
|---|---|
| 1 | En el paso 5, en "¿Cuántas comidas consumió el hogar ayer?" escribe un número **mayor a 2** (ej. 3). Observa si aparecen las preguntas de rCSI. |
| 2 | Cambia el valor a **2 o menos** (ej. 1). Observa de nuevo. |

**Resultado esperado:** con un valor mayor a 2 las preguntas adicionales de rCSI (comer alimentos menos preferidos, pedir prestado, etc.) están ocultas. Con 2 o menos, esas preguntas aparecen.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.8 — Límite de 3 necesidades prioritarias

| Paso | Acción |
|---|---|
| 1 | En "Las tres necesidades más urgentes del hogar", marca 3 opciones cualquiera. |
| 2 | Intenta marcar una cuarta opción. |

**Resultado esperado:** las opciones no marcadas quedan deshabilitadas (no se pueden seleccionar) mientras haya 3 marcadas. Al desmarcar una, las demás vuelven a estar disponibles.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.9 — Envío exitoso con conexión

| Paso | Acción |
|---|---|
| 1 | Completa el paso 6 (cierre) con datos válidos. |
| 2 | Haz clic en **Guardar captura**, con conexión a internet normal. |

**Resultado esperado:** el botón muestra "Guardando..." brevemente y luego aparece la pantalla "Captura registrada". No debe aparecer el mensaje de "Guardado en este dispositivo" (ese es solo para cuando no hay conexión, ver Caso 3.10).

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.10 — Envío sin conexión y sincronización posterior

| Paso | Acción |
|---|---|
| 1 | Antes de enviar, corta la conexión a internet del dispositivo (modo avión, o desconecta el wifi) — puedes hacerlo también desde las herramientas de desarrollador del navegador, pestaña Network/Red, seleccionando "Offline". |
| 2 | Llena una captura completa y haz clic en **Guardar captura**. |
| 3 | Verifica el mensaje que aparece. |
| 4 | Reconecta el dispositivo a internet. Espera unos segundos sin recargar la página. |
| 5 | Pide a alguien de desarrollo que confirme en la base de datos que la captura llegó (tabla `census_entries`, por fecha/hora aproximada). |

**Resultado esperado:** en el paso 3 aparece "Guardado en este dispositivo" (no "Captura registrada"). Al reconectar (paso 4) no hace falta ninguna acción manual — la sincronización es automática. En el paso 5, la captura debe existir en la base de datos con los datos que llenaste.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.11 — El formulario carga sin conexión (offline-first)

| Paso | Acción |
|---|---|
| 1 | Con conexión normal, visita `/censo/nuevo` al menos una vez (para que el navegador guarde la página en caché). |
| 2 | Corta la conexión a internet. |
| 3 | Recarga la página (F5) o vuelve a entrar a `/censo/nuevo`. |

**Resultado esperado:** la página carga igual, sin pantalla de error de "sin conexión" del navegador. Puedes seguir llenando el formulario con normalidad (se guardará localmente como en el Caso 3.10 al enviarlo).

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 3.12 — Prioridad crítica automática (bandera roja)

Este caso valida que el índice de vulnerabilidad prioriza correctamente los casos más urgentes. Requiere acceso a la base de datos o que alguien de desarrollo confirme el resultado.

| Paso | Acción |
|---|---|
| 1 | Llena una captura nueva. En el paso 4, pon al menos 1 en "Menores de 5 años". En el paso 5, en "¿Dónde está durmiendo el hogar ahora?" selecciona **A la intemperie**. Completa el resto con cualquier valor válido y envía. |
| 2 | Pide a desarrollo que consulte esa captura en `census_entries` — columnas `priority_level` y `red_flags`. |

**Resultado esperado:** `priority_level` debe ser `critico`, sin importar los demás valores que hayas llenado, y `red_flags` debe incluir algo relacionado con "sin techo con menor o gestante".

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

---

## 4. Módulo 3 — Kardex / Inventario de centros de acopio

Usa la cuenta de **Operador** con una bodega asignada (ver sección 1.2) para los casos 4.1 a 4.7. Usa Admin o Coordinador para el caso 4.8.

### Caso 4.1 — Acceso al Kardex según el rol

| Paso | Acción |
|---|---|
| 1 | Con la cuenta de Operador, verifica que **Kardex** aparece en el menú lateral y que al hacer clic carga el listado (`/kardex`). |
| 2 | Cierra sesión, entra con la cuenta de Donante y verifica que esa opción **no** aparece en el menú, y que escribir `/kardex`, `/kardex/entrada` o `/kardex/salida` a mano da error de acceso denegado. |

**Resultado esperado:** Operador entra sin problema a las tres pantallas; Donante no ve la opción ni puede acceder por URL directa a ninguna.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.2 — Registrar una entrada de stock

| Paso | Acción |
|---|---|
| 1 | Desde el Kardex, haz clic en **Registrar entrada**. |
| 2 | Selecciona la bodega, un ítem del catálogo, cantidad (ej. 50), un número de lote y una fecha de vencimiento futura. |
| 3 | Haz clic en **Guardar entrada**. |

**Resultado esperado:** aparece la pantalla "Entrada registrada". Si vuelves al listado del Kardex (`/kardex`), el ítem aparece con la cantidad disponible que registraste.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.3 — Una bodega no asignada no aparece como opción

| Paso | Acción |
|---|---|
| 1 | Pide a desarrollo que cree una bodega nueva **sin** asignártela. |
| 2 | Entra a **Registrar entrada** y revisa el desplegable de bodegas. |

**Resultado esperado:** la bodega nueva (sin asignación) no aparece en el desplegable — solo ves las bodegas que tienes asignadas.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.4 — Registrar una salida y ver el descuento del inventario

| Paso | Acción |
|---|---|
| 1 | Anota la cantidad disponible del ítem que registraste en el Caso 4.2 (revisa `/kardex`). |
| 2 | Ve a **Registrar salida**, selecciona ese mismo lote, indica una cantidad menor a la disponible (ej. 10), elige un motivo de salida y guarda. |
| 3 | Vuelve al listado del Kardex. |

**Resultado esperado:** la cantidad disponible del lote bajó exactamente en la cantidad que despachaste (si tenía 50 y despachaste 10, ahora debe mostrar 40).

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.5 — No se puede despachar más de lo disponible

| Paso | Acción |
|---|---|
| 1 | En **Registrar salida**, selecciona un lote y escribe una cantidad **mayor** a la disponible mostrada junto al lote. |
| 2 | Haz clic en **Guardar salida**. |

**Resultado esperado:** aparece un mensaje de error indicando que el stock es insuficiente (con el número de unidades disponibles). No se descuenta nada ni se crea la salida.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.6 — Envío sin conexión y sincronización posterior

| Paso | Acción |
|---|---|
| 1 | Corta la conexión a internet (modo avión, o pestaña Network/Red del navegador en "Offline"). |
| 2 | Registra una entrada nueva (o una salida) y haz clic en guardar. |
| 3 | Verifica el mensaje que aparece. |
| 4 | Reconecta el dispositivo. Espera unos segundos sin recargar la página. |
| 5 | Pide a desarrollo que confirme en la base de datos que la entrada/salida llegó. |

**Resultado esperado:** en el paso 3 aparece "Guardado en este dispositivo". Al reconectar, no hace falta ninguna acción manual — se sincroniza sola. En el paso 5, el registro debe existir en la base de datos.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.7 — Vencimientos próximos se resaltan en el listado

| Paso | Acción |
|---|---|
| 1 | Registra una entrada con fecha de vencimiento dentro de los próximos 30 días. |
| 2 | Ve al listado del Kardex (`/kardex`). |

**Resultado esperado:** la fecha de vencimiento de ese lote aparece resaltada (en rojo/negrita) para diferenciarla de lotes sin vencimiento próximo.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.8 — Admin/Coordinador operan cualquier bodega activa

| Paso | Acción |
|---|---|
| 1 | Inicia sesión como Admin (o Coordinador). |
| 2 | Entra a **Registrar entrada** y revisa el desplegable de bodegas. |

**Resultado esperado:** aparecen **todas** las bodegas activas del sistema, no solo las que tendría asignadas como si fuera operador — Admin y Coordinador no necesitan asignación previa.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

---

## 5. Resumen de resultados

| # | Caso | Cumple | No cumple |
|---|---|---|---|
| 2.1 | Registro de usuario | ☐ | ☐ |
| 2.2 | Usuario pendiente bloqueado | ☐ | ☐ |
| 2.3 | Aprobar usuario | ☐ | ☐ |
| 2.4 | Usuario aprobado puede entrar | ☐ | ☐ |
| 2.5 | Rechazar usuario | ☐ | ☐ |
| 2.6 | Login fallido | ☐ | ☐ |
| 2.7 | RBAC bloquea otros roles | ☐ | ☐ |
| 2.8 | Auditoría de accesos | ☐ | ☐ |
| 3.1 | Acceso al formulario por rol | ☐ | ☐ |
| 3.2 | Consentimiento — rechazo bloquea | ☐ | ☐ |
| 3.3 | Consentimiento — aceptación avanza | ☐ | ☐ |
| 3.4 | Captura de GPS | ☐ | ☐ |
| 3.5 | Mapa manual de respaldo | ☐ | ☐ |
| 3.6 | Vivienda + integrantes | ☐ | ☐ |
| 3.7 | Condicional rCSI | ☐ | ☐ |
| 3.8 | Límite de 3 necesidades | ☐ | ☐ |
| 3.9 | Envío en línea | ☐ | ☐ |
| 3.10 | Envío offline + sincronización | ☐ | ☐ |
| 3.11 | Formulario carga sin conexión | ☐ | ☐ |
| 3.12 | Prioridad crítica automática | ☐ | ☐ |
| 4.1 | Acceso al Kardex por rol | ☐ | ☐ |
| 4.2 | Registrar entrada de stock | ☐ | ☐ |
| 4.3 | Bodega no asignada no aparece | ☐ | ☐ |
| 4.4 | Registrar salida y descuento | ☐ | ☐ |
| 4.5 | No despacha más de lo disponible | ☐ | ☐ |
| 4.6 | Envío offline + sincronización | ☐ | ☐ |
| 4.7 | Vencimientos próximos resaltados | ☐ | ☐ |
| 4.8 | Admin/Coordinador sin restricción de bodega | ☐ | ☐ |

**Total cumple:** ___ / 28

## 6. Hallazgos (bugs encontrados)

Para cada caso marcado "No cumple", documenta aquí con el mismo número de caso:

| # de caso | Qué esperabas | Qué pasó realmente | Captura de pantalla / video (si aplica) |
|---|---|---|---|
| | | | |

---

**Firma de quien hizo la prueba:** ___________________ **Fecha:** ___________
