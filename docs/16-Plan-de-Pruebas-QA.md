# 🧪 Plan de Pruebas QA — Funcionalidades en construcción

**Alcance de esta versión:** Módulo 1 (Portal Público de Búsqueda, sin autenticación), Módulo 2 (Autenticación y Roles), el Formulario de Encuestas — Censo de Hogares (Fase 1 de triaje + registro de integrantes) y Módulo 3 (Kardex/Inventario de centros de acopio, incluido el catálogo administrable de Bodegas / Centros de Acopio, control de vencidos, FEFO, traslados entre bodegas, alertas de vencimiento y de stock mínimo). Se actualiza a medida que se agregan módulos nuevos.

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

### Caso 4.9 — Acceso al catálogo de bodegas según el rol

| Paso | Acción |
|---|---|
| 1 | Con la cuenta de Admin, verifica que **Bodegas / Centros de Acopio** aparece en el menú (grupo Administración) y que al hacer clic carga el listado (`/admin/bodegas`). |
| 2 | Cierra sesión, entra con la cuenta de Operador o Coordinador y verifica que esa opción **no** aparece en el menú, y que escribir `/admin/bodegas` a mano da error de acceso denegado. |

**Resultado esperado:** solo Admin ve la opción y puede entrar; cualquier otro rol (incluido Coordinador, que sí puede operar stock pero no administrar el catálogo) recibe acceso denegado.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.10 — Crear una bodega / centro de acopio

| Paso | Acción |
|---|---|
| 1 | Desde el listado, haz clic en **Nueva bodega**. |
| 2 | Completa nombre, dirección, persona de contacto y teléfono. Deja el resto opcional en blanco. Haz clic en **Guardar**. |

**Resultado esperado:** el modal se cierra, aparece un mensaje de confirmación, y la bodega nueva aparece en el listado con estado **Activa**.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.11 — Validación de campos obligatorios

| Paso | Acción |
|---|---|
| 1 | Haz clic en **Nueva bodega**, deja el nombre vacío y haz clic en **Guardar**. |

**Resultado esperado:** el formulario muestra errores de validación bajo los campos obligatorios vacíos (nombre, dirección, persona de contacto, teléfono) y no se crea ninguna bodega.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.12 — Editar una bodega existente

| Paso | Acción |
|---|---|
| 1 | En el listado, haz clic en **Editar** sobre cualquier bodega. |
| 2 | Verifica que el formulario carga con los datos actuales (incluido el teléfono, que se guarda encriptado en la base de datos pero debe verse en texto plano aquí). |
| 3 | Cambia el nombre y haz clic en **Guardar**. |

**Resultado esperado:** el formulario precarga correctamente todos los campos, incluido el teléfono. Tras guardar, el listado refleja el nombre nuevo.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 4.13 — Desactivar una bodega y su efecto en el Kardex

| Paso | Acción |
|---|---|
| 1 | Desactiva una bodega desde el listado (botón **Desactivar**, confirma el diálogo). |
| 2 | Verifica que su estado cambia a **Inactiva**. |
| 3 | Ve a **Registrar entrada** (o **Registrar salida**) con una cuenta que tenía esa bodega asignada. |

**Resultado esperado:** la bodega desactivada ya no aparece en el desplegable de bodegas de los formularios de Kardex. El botón en el listado ahora dice **Activar** y permite reactivarla.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

---

## 5. Mejoras del Kardex (vencidos, FEFO, traslados, alertas, stock mínimo)

Usa la cuenta de **Operador** con al menos dos bodegas asignadas para los casos 5.1 a 5.6 (pídele a desarrollo que te asigne una segunda bodega si solo tienes una).

### Caso 5.1 — No se puede despachar un lote vencido

| Paso | Acción |
|---|---|
| 1 | Pide a desarrollo que cree un lote con fecha de vencimiento pasada en una de tus bodegas (o espera a que uno de tus lotes venza). |
| 2 | Ve a **Registrar salida** y revisa el desplegable de lotes. |

**Resultado esperado:** el lote vencido **no aparece** en el desplegable — no hay forma de seleccionarlo para una entrega normal.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 5.2 — El lote sugerido es el que vence primero (FEFO)

| Paso | Acción |
|---|---|
| 1 | Con al menos dos lotes disponibles del mismo o distinto ítem, con fechas de vencimiento distintas, ve a **Registrar salida**. |
| 2 | Observa cuál lote aparece preseleccionado y marcado como "(Sugerido)" al abrir el formulario. |

**Resultado esperado:** el lote preseleccionado es el que tiene la fecha de vencimiento más próxima entre todos los disponibles (o el primero de la lista, si ninguno tiene vencimiento). El desplegable completo está ordenado de más próximo a vencer a menos.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 5.3 — Descartar un lote vencido con motivo de pérdida/daño

| Paso | Acción |
|---|---|
| 1 | Ve a **Registrar salida**, selecciona el lote vencido del Caso 5.1 — nota que ya no aparece en el desplegable normal. Pide a desarrollo que confirme que sí se puede registrar vía el motivo **Descarte por vencimiento** (esto valida la regla de negocio, aunque no lo puedas hacer desde el desplegable filtrado). |
| 2 | Alternativamente: registra una salida sobre un lote **no vencido** usando el motivo **Pérdida** o **Daño** y confirma que se guarda igual que una donación normal. |

**Resultado esperado:** los motivos "Pérdida", "Daño" y "Descarte por vencimiento" están disponibles en el desplegable de motivos y funcionan igual que una salida normal (descuentan del disponible).

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 5.4 — Trasladar stock entre bodegas

| Paso | Acción |
|---|---|
| 1 | Desde el Kardex, haz clic en **Trasladar**. |
| 2 | Selecciona un lote, elige una bodega destino distinta a la de origen, indica una cantidad menor a la disponible y guarda. |
| 3 | Ve al listado del Kardex y filtra por la bodega destino. |

**Resultado esperado:** aparece un lote nuevo en la bodega destino, con el mismo número de lote y fecha de vencimiento que el original, por la cantidad trasladada. En la bodega de origen, el disponible del lote original bajó exactamente esa cantidad.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 5.5 — Alertas de vencimiento y su resolución

| Paso | Acción |
|---|---|
| 1 | Pide a desarrollo que corra `sail artisan kardex:update-stock-entry-statuses` (o espera a la ejecución diaria programada) sobre un lote que vence en los próximos 30 días. |
| 2 | Ve a **Kardex → Vencimientos**. |
| 3 | Verifica que el lote aparece con una etiqueta de alerta (30/14/7 días o Vencido). |
| 4 | Haz clic en **Resolver**, elige "Se descartó" y guarda. |
| 5 | Verifica en el listado del Kardex que el disponible de ese lote quedó en 0. |

**Resultado esperado:** la alerta aparece correctamente clasificada por cercanía al vencimiento. Al resolverla como "Se descartó", se genera automáticamente una salida y el disponible del lote baja a 0 — no es solo una nota, mueve el inventario de verdad.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 5.6 — Alerta de stock mínimo

| Paso | Acción |
|---|---|
| 1 | Pide a desarrollo que configure un `reorder_point` en un ítem del catálogo (hoy no tiene pantalla propia, se hace por `tinker`). |
| 2 | Asegúrate de que el disponible total de ese ítem en tus bodegas esté por debajo de ese número (registra salidas si hace falta). |
| 3 | Ve al listado del Kardex. |

**Resultado esperado:** aparece un aviso amarillo en la parte superior ("Ítems bajo el punto de reorden") listando ese ítem con su cantidad disponible actual.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

---

## 6. Módulo 1 — Portal Público de Búsqueda

Todos los casos de esta sección se hacen **sin iniciar sesión** (ventana de incógnito). Necesitas al menos un insumo con existencias registrado desde el Kardex (usa el Caso 4.2 de este mismo documento para tener datos reales).

### Caso 6.1 — El buscador es lo primero que se ve al entrar

| Paso | Acción |
|---|---|
| 1 | Abre la URL raíz del ambiente en incógnito (ej. `https://tu-dominio/`, sin ninguna ruta adicional). |
| 2 | Observa qué es lo primero que aparece en pantalla, sin necesidad de hacer scroll. |
| 3 | Escribe parte del nombre de un insumo que sepas que tiene existencias (ej. "suero", "arroz"). |

**Resultado esperado:** la página carga sin pedir login y el buscador (título + campo de búsqueda grande) es lo primero visible, antes que cualquier otro contenido. A medida que escribes, aparecen tarjetas de resultados con el nombre del insumo, la bodega, la cantidad disponible y un semáforo de color (🟢🟡🔴). La ruta vieja `/buscar` debe redirigir automáticamente a `/`.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 6.1B — Vista dividida con información de la aplicación

| Paso | Acción |
|---|---|
| 1 | En la misma página, mira a la derecha de los resultados de búsqueda (o debajo, en pantallas angostas de celular). |

**Resultado esperado:** se ve un panel con una descripción breve de qué es Donaciones Rolda, dos cifras (insumos disponibles y centros de acopio activos) y un resumen de "cómo funciona". El mapa de bodegas aparece debajo de ese panel, no reemplaza la información.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 6.2 — El semáforo corresponde a la cantidad

| Paso | Acción |
|---|---|
| 1 | Busca un insumo con más de 20 unidades disponibles en una bodega. Anota el color del semáforo. |
| 2 | Busca uno con entre 6 y 20. Anota el color. |
| 3 | Busca uno con 5 o menos. Anota el color. |

**Resultado esperado:** más de 20 → 🟢 Alta; entre 6 y 20 → 🟡 Media; 5 o menos → 🔴 Baja. Un insumo totalmente agotado (0 disponible) no debe aparecer en los resultados.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 6.3 — Filtros por categoría y zona

| Paso | Acción |
|---|---|
| 1 | Sin escribir nada en el buscador, selecciona una categoría en el filtro. |
| 2 | Quita ese filtro y selecciona una zona. |

**Resultado esperado:** en ambos casos, los resultados se reducen a solo lo que coincide con el filtro elegido.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 6.4 — Mapa de resultados

| Paso | Acción |
|---|---|
| 1 | Con resultados visibles, revisa el mapa a la derecha. |
| 2 | Haz clic en un marcador. |

**Resultado esperado:** aparece un marcador por cada bodega con resultados. Al hacer clic, se abre un globo con el nombre de la bodega y la lista de insumos disponibles ahí (con su semáforo).

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 6.5 — Ordenar por cercanía con "Usar mi ubicación"

| Paso | Acción |
|---|---|
| 1 | Haz clic en **Usar mi ubicación** y acepta el permiso de ubicación del navegador. |
| 2 | Revisa el orden de los resultados y si aparece la distancia en km en cada tarjeta. |

**Resultado esperado:** cada tarjeta muestra la distancia aproximada en km, y los resultados quedan ordenados del más cercano al más lejano.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 6.6 — El teléfono de la bodega no se ve sin verificación

| Paso | Acción |
|---|---|
| 1 | Con resultados visibles, revisa las tarjetas y el mapa: busca si el número de teléfono de alguna bodega aparece en cualquier parte de la pantalla. |
| 2 | Haz clic en **Contactar** sobre cualquier resultado. |

**Resultado esperado:** en el paso 1, el teléfono **no debe verse en ningún lado** — ni en las tarjetas ni en el popup del mapa. En el paso 2 se abre un modal pidiendo completar una verificación (Cloudflare Turnstile, la casilla "No soy un robot") antes de mostrar cualquier dato de contacto.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

### Caso 6.7 — Desbloquear contacto y escribir por WhatsApp

| Paso | Acción |
|---|---|
| 1 | En el modal de contacto, completa la verificación de Turnstile. |
| 2 | Haz clic en **Ver contacto**. |
| 3 | Haz clic en **Escribir por WhatsApp**. |

**Resultado esperado:** tras verificar, aparece el nombre de la persona de contacto y el teléfono de la bodega. El botón de WhatsApp abre una pestaña nueva hacia `wa.me` con un mensaje ya redactado mencionando la bodega.

`Cumple ☐   No cumple ☐`
Observaciones: ___________________________________________

---

## 7. Resumen de resultados

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
| 4.9 | Acceso al catálogo de bodegas por rol | ☐ | ☐ |
| 4.10 | Crear bodega / centro de acopio | ☐ | ☐ |
| 4.11 | Validación de campos obligatorios | ☐ | ☐ |
| 4.12 | Editar bodega existente | ☐ | ☐ |
| 4.13 | Desactivar bodega y efecto en Kardex | ☐ | ☐ |
| 5.1 | No se despacha un lote vencido | ☐ | ☐ |
| 5.2 | Lote sugerido es FEFO | ☐ | ☐ |
| 5.3 | Descarte con motivo pérdida/daño | ☐ | ☐ |
| 5.4 | Traslado entre bodegas | ☐ | ☐ |
| 5.5 | Alertas de vencimiento y resolución | ☐ | ☐ |
| 5.6 | Alerta de stock mínimo | ☐ | ☐ |
| 6.1 | Buscador es lo primero que se ve | ☐ | ☐ |
| 6.1B | Vista dividida con info de la app | ☐ | ☐ |
| 6.2 | Semáforo según cantidad | ☐ | ☐ |
| 6.3 | Filtros por categoría y zona | ☐ | ☐ |
| 6.4 | Mapa de resultados | ☐ | ☐ |
| 6.5 | Ordenar por cercanía | ☐ | ☐ |
| 6.6 | Teléfono oculto sin verificación | ☐ | ☐ |
| 6.7 | Desbloquear contacto y WhatsApp | ☐ | ☐ |

**Total cumple:** ___ / 47

## 8. Hallazgos (bugs encontrados)

Para cada caso marcado "No cumple", documenta aquí con el mismo número de caso:

| # de caso | Qué esperabas | Qué pasó realmente | Captura de pantalla / video (si aplica) |
|---|---|---|---|
| | | | |

---

**Firma de quien hizo la prueba:** ___________________ **Fecha:** ___________
