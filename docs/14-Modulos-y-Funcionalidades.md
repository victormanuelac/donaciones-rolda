# 📦 Módulos y Funcionalidades — Donaciones Rolda

**Versión:** 1.0 — Consolidado a partir de la especificación vigente
**Fecha:** Agosto 2026
**Fuentes:** `Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md` (estructura de 7 módulos vigente), `archive/01-Especificaciones-Tecnicas-Expandidas.md` (detalle funcional/endpoints de Módulos 1-5, v1), `11-Modulo-7-Beneficiarios-Estadisticas-Inteligentes.md` (Módulo 7 completo), `03-Funciones-Adicionales-Propuestas.md` (Fase II), `02-Modelo-Datos-MER-DDL.md` (DDL de referencia), y el Roadmap en Notion (horas estimadas por módulo).

> Este documento reúne, en un solo lugar, la definición de **todos los módulos planteados de la aplicación**: qué resuelve cada uno, para quién, qué hace exactamente, y con qué endpoints/pantallas se construye. Es la referencia funcional; el detalle de implementación técnica (DDL, diagramas de flujo, arquitectura AWS) vive en los documentos numerados 02–13 y en la subpágina "🏗️ Arquitectura y Especificación Técnica" de Notion.

---

## Resumen de módulos

| # | Módulo | Objetivo en una línea | Esfuerzo estimado |
|---|---|---|---|
| 1 | Portal Público de Búsqueda | Ciudadanos buscan insumos disponibles sin autenticarse | 22h |
| 2 | Autenticación y Roles | Gestiona usuarios, permisos y acceso por rol | 14h |
| 3 | Gestión de Inventarios | Operadores registran entradas/salidas de stock, offline-first | 22h |
| 4 | Control Maestro de Ítems | Admin aprueba ítems nuevos y consolida duplicados del catálogo | 13h |
| 5 | Alertas y Auditoría | Notificaciones, trazabilidad completa y reportes operacionales | 12h |
| 6 | Entregas y Seguimiento | Registra la entrega física a beneficiarios con comprobante y cierra el ciclo con el Módulo 7 | 23h |
| 7 | Beneficiarios + Estadísticas Inteligentes | Prioriza beneficiarios por vulnerabilidad y recomienda qué entregarles | 38.5h |
| 8 | Mapa Colaborativo y Multimedia | Mapa en tiempo real con evidencia multimedia y QR, reutilizando la app voluntaria Mapa Emergencia | ~10-12h |
| 9 | Búsqueda Geolocalizada | Muestra primero los insumos más cercanos al ciudadano | 3.5h |
| 10 | Sugerencias Inteligentes de Búsqueda | Sugiere insumos relacionados según historial de búsqueda | 4h |
| 11 | Reportes Exportables (PDF/Excel) | Genera reportes de stock/entregas/vencimientos con un clic | 6.5h |
| 12 | Dashboard de Operaciones en Tiempo Real | Vista única con semáforo agregado, gráficos y alertas críticas | 8.5h |
| 13 | Trazabilidad por QR/Barcode | Código QR por lote, escaneable en entrada/salida/entrega | 8h |

El desglose de horas por equipo (Backend/Frontend/QA) y el checklist ejecutable de cada módulo vive en la base de datos **Product Roadmap** de Notion; este documento es la referencia de **qué** hace cada módulo, no el tracking de **cuándo/quién** lo construye.

### Actores del sistema

| Rol | Quién es | Módulos que usa principalmente |
|---|---|---|
| **Ciudadano** (público, sin login) | Cualquier persona que necesita un insumo | Módulos 1, 8, 9 |
| **Operador de campo** (`operator`) | Voluntario/trabajador social en bodega o terreno | Módulos 2, 3, 6, 7, 8, 10, 13 |
| **Coordinador** (`coordinator`) | Líder comunitario / ONG que supervisa operadores | Módulos 2, 4, 5, 6, 7, 8, 10, 11, 12 |
| **Administrador** (`admin`) | Municipalidad / coordinador central del sistema | Todos, especialmente 2, 4, 5, 11 |
| **Médico** (`doctor`) | Puesto de salud, valida derivaciones y protocolos | Módulo 7 |
| **Donante** (`donor`) | Persona/empresa que aporta recursos | Módulos 1, 7 (ve impacto), 12 |
| **Municipalidad** (`municipal`) | Autoridad local, ve inteligencia agregada | Módulos 7, 11, 12 |

> ⚠️ **Nota de consistencia:** el DDL original (`02-Modelo-Datos-MER-DDL.md`) todavía define los roles como `admin / acopio_operator / field_leader` (3 roles, versión inicial). La especificación vigente y el Módulo 7 ya operan con el modelo de 6 roles de la tabla anterior. Falta actualizar el `ENUM` de la tabla `users` para reflejarlo — tarea pendiente dentro del Módulo 2.

---

## Módulo 1: Portal Público de Búsqueda

**Objetivo:** que un ciudadano busque insumos disponibles sin necesidad de autenticarse, y pueda contactar a quien los tiene sin exponer datos sensibles.

### Funcionalidades

- Búsqueda por palabra clave con sugerencias en tiempo real (full-text)
- Filtros por zona geográfica, categoría y disponibilidad
- Semáforo dinámico de disponibilidad (🟢 Alta / 🟡 Media / 🔴 Baja / ⚫ Agotado)
- Mapa interactivo de bodegas (Leaflet.js + OpenStreetMap) con cálculo de distancia (Haversine)
- Modal de contacto protegido con Cloudflare Turnstile (antibot, sin exponer teléfono directamente)
- Deep-link a WhatsApp pre-redactado tras validar el captcha
- **(Módulo 7)** Búsqueda enriquecida: cuando aplica, muestra demanda agregada y anónima ("67 beneficiarios necesitan este insumo") y nivel de urgencia, sin exponer ningún dato personal — ver subpágina "Módulo 7" para el detalle de este cruce

### Endpoints REST

| Endpoint | Función |
|---|---|
| `GET /api/public/search` | Buscar insumos (`searchItems()`) |
| `GET /api/public/warehouses` | Listar bodegas |
| `POST /api/public/contact-unlock` | Validar Turnstile y desbloquear datos de contacto |

### Pantallas

- Página principal de búsqueda, tarjetas de resultados, mapa interactivo, modal de contacto (Turnstile)

---

## Módulo 2: Autenticación y Roles

**Objetivo:** gestionar el acceso al sistema — quién puede entrar, con qué permisos, y cómo se aprueban nuevos operadores.

### Funcionalidades

- Login con email + contraseña (Laravel Sanctum: sesión web + tokens API)
- Registro híbrido: manual o con código de organización (fast-path para voluntarios de una ONG conocida)
- Panel de aprobación de usuarios pendientes (admin)
- Gestión de permisos por organización
- Cambio de contraseña y recuperación de cuenta
- Auditoría de login (IP, hora, dispositivo)
- Control de acceso basado en roles (RBAC) para los 6 roles del sistema (ver tabla de actores arriba)

### Endpoints REST

| Endpoint | Función |
|---|---|
| `POST /api/auth/login` | Iniciar sesión |
| `POST /api/auth/register` | Registro de nuevo usuario (queda `pending`) |
| `POST /api/auth/logout` | Cerrar sesión |
| `GET /api/auth/me` | Datos del usuario autenticado |
| `POST /api/admin/users/approve/{id}` | Aprobar usuario pendiente y asignarle rol |
| `PATCH /api/auth/change-password` | Cambiar contraseña |

### Pantallas

- Login, registro (con/sin código de organización), panel de aprobaciones, gestión de usuarios activos

---

## Módulo 3: Gestión de Inventarios

**Objetivo:** que los operadores de campo registren entradas y salidas de insumos, incluso sin conexión a internet.

### Funcionalidades

**Registro de entrada**
- Selección de insumo (búsqueda + sugerencias del catálogo maestro)
- Opción de solicitar un ítem nuevo si no existe (deriva al Módulo 4)
- Cantidad, unidad de medida, número de lote, fecha de vencimiento
- Marcado de cadena de frío si aplica
- Foto de la entrada (opcional, mejora trazabilidad)
- Guardado offline-first: si hay conexión se envía directo a la API; si no, se guarda en IndexedDB (Dexie.js) y sincroniza al reconectar, con reintentos exponenciales (hasta 3x) y cola visible al operador

**Confirmación de recepción**
- Notificación cuando llega un insumo esperado
- Confirmación manual de llegada física → cambia `stock_entries.status` de `pending_arrival` a `available`
- Registro de daños o discrepancias

**Vista de bodega**
- Dashboard con stock actual por bodega, filtros por categoría/estado/vencimiento próximo
- Alertas de baja existencia

### Endpoints REST

| Endpoint | Función |
|---|---|
| `POST /api/stock/entries` | Crear entrada de stock |
| `PATCH /api/stock/entry/{id}/confirm` | Confirmar llegada física |
| `GET /api/stock/warehouse/{id}` | Ver stock de una bodega |
| `POST /api/stock/entries/request-new-item` | Solicitar ítem nuevo (→ Módulo 4) |

### Pantallas

- Formulario de entrada, dashboard de bodega, indicador de estado de sincronización offline

---

## Módulo 4: Control Maestro de Ítems

**Objetivo:** mantener el catálogo de insumos limpio — aprobar ítems nuevos propuestos por operadores y evitar duplicados.

### Funcionalidades

- Cola de ítems en revisión (`under_review`) con contexto: quién lo solicitó, desde qué bodega
- Edición de nombre, categoría y unidad de medida antes de aprobar
- Consolidación: marcar un ítem como duplicado y vincularlo al existente
- Aprobación → cambia a `approved`, habilita el stock asociado y notifica al operador
- Rechazo con motivo obligatorio
- Auditoría de todos los cambios sobre el catálogo maestro

### Endpoints REST

| Endpoint | Función |
|---|---|
| `GET /api/admin/master-items/pending` | Listar ítems en revisión |
| `PATCH /api/admin/master-items/{id}/approve` | Aprobar ítem |
| `PATCH /api/admin/master-items/{id}/consolidate` | Marcar como duplicado |
| `PATCH /api/admin/master-items/{id}/reject` | Rechazar con motivo |

### Pantallas

- Cola de revisión, detalle de ítem con historial de solicitudes, catálogo aprobado

---

## Módulo 5: Alertas y Auditoría

**Objetivo:** dar visibilidad y trazabilidad completa de lo que pasa en el sistema — para operación diaria y para cumplimiento LSPP.

### Funcionalidades

**Centro de notificaciones**
- Notificaciones internas por rol
- Alertas críticas: ítem próximo a vencer, bodega sin operador, stock bajo
- Historial de notificaciones por usuario

**Auditoría de accesos**
- Registro de toda transacción sensible en `audit_logs` (quién, qué acción, sobre qué tabla, valor anterior/nuevo, cuándo)
- Filtros por rango de fechas, usuario, tipo de acción
- Exportación para auditoría externa (cumplimiento LSPP — ver subpágina "Cumplimiento LSPP y Seguridad")

**Reportes operacionales**
- Trazabilidad completa de un insumo (entrada → salida)
- Vencimientos próximos (7 / 14 / 30 días)
- Stock actual por bodega y categoría
- Actividad y último acceso por usuario
- Exportación a CSV (Excel) y PDF (manifiestos, certificados)

### Endpoints REST

| Endpoint | Función |
|---|---|
| `GET /api/notifications` | Notificaciones del usuario actual |
| `GET /api/audit-logs` | Historial de auditoría (admin) |
| `GET /api/reports/traceability` | Reporte de trazabilidad |
| `GET /api/reports/expiring` | Ítems próximos a vencer |
| `GET /api/reports/stock` | Estado actual de stock |
| `POST /api/reports/export` | Exportar en CSV/PDF |

### Pantallas

- Centro de alertas, log de auditoría (admin), panel de reportes con gráficos

---

## Módulo 6: Entregas y Seguimiento

**Objetivo:** registrar en campo qué se le entregó realmente a un beneficiario, con evidencia, y conectar esa entrega con el resto del sistema (stock, historial médico, impacto al donante).

> Este módulo reemplaza y amplía lo que en la especificación v1 era la sección "Registro de Salida" del Módulo 3: en la versión vigente se independiza como módulo propio porque ahora se integra directamente con el Módulo 7 (beneficiarios).

### Funcionalidades

- Selección del insumo disponible y cantidad a despachar
- Vinculación de la salida a un beneficiario (nombre, zona, contacto) o a un motivo (donativo, venta subsidiada, emergencia)
- Confirmación con firma digital y/o foto de comprobante
- Funciona offline: se guarda en IndexedDB y sincroniza al reconectar (mismo mecanismo que el Módulo 3)
- Al sincronizar, el backend automáticamente:
  - Crea el registro de `stock_exits` (auditoría) y descuenta stock
  - Crea un `care_history` (historial de atención del beneficiario)
  - Marca como `FULFILLED` las recomendaciones pendientes del Módulo 7 que correspondan a los ítems entregados
  - Invalida caché de estadísticas (Redis)
  - Dispara alerta `RECOMMENDATION_FULFILLED` al donante correspondiente ("Tu donación llegó a...")
- Historial de atenciones visible en la ficha del beneficiario (Módulo 7)
- Seguimiento automático: marca de `follow_up_required` si el caso necesita revisión posterior

### Endpoints REST

| Endpoint | Función |
|---|---|
| `POST /api/stock/exits` | Registrar salida/entrega (crea `StockExit`) |
| `POST /api/stock-exits` (offline sync) | Sincroniza entregas guardadas en IndexedDB |

### Pantallas

- Formulario de confirmación de entrega (checklist de ítems + firma/foto), timeline de historial de atenciones dentro de la ficha de beneficiario

---

## Módulo 7: Beneficiarios + Estadísticas Inteligentes

**Objetivo:** identificar automáticamente quién es más vulnerable, recomendar qué necesita basado en evidencia médica/nutricional, y medir el impacto real de cada entrega.

> Es el módulo más grande del sistema (38.5h estimadas) y el diferenciador principal frente a un sistema de inventario tradicional. Diseño técnico completo en la subpágina/documento dedicado "Módulo 7". A continuación, el resumen funcional.

### Componentes

**1. Ficha individual de beneficiario** — datos demográficos (del censo), score de vulnerabilidad en vivo, recomendaciones activas, historial de atenciones, derivaciones a salud.

**2. Motor de scoring ponderado (0-100)** — calcula automáticamente, cada vez que se registra o actualiza un beneficiario, un puntaje compuesto por 4 factores:

| Factor | Puntos | Ejemplos que suman |
|---|---|---|
| Demográfico | 0-30 | Edad < 5 años (+18), embarazo (+12, +8 si trimestre ≥7), edad ≥ 60 (+12) |
| Salud | 0-30 | Enfermedad crónica (+8 c/u), síntomas actuales (+2 c/u), sin revisión médica >90 días (+6) |
| Nutricional | 0-20 | Menor de 5 (+10), desnutrición confirmada (+15), familia numerosa (+5) |
| Social | 0-20 | Sin hogar (+20), monoparental (+8), desempleo (+6), discapacidad (+7) |

Prioridad resultante: 🔴 **CRÍTICO** (≥70) · 🟡 **PRIORITARIO** (40-69) · 🟢 **NORMAL** (<40)

**3. Motor de recomendaciones** — cruza el perfil del beneficiario con una base de protocolos (WHO, ICBF, salud local, municipal) y genera automáticamente una lista de ítems recomendados, verificando disponibilidad de stock por bodega y evitando duplicados.

**4. Dashboards por rol (6 variantes)** — Operador (lista de críticos + ficha rápida), Coordinador (agregados en vivo + brechas de stock), Médico (derivaciones pendientes + validación de protocolos), Donante (impacto de sus aportes), Municipalidad (inteligencia estratégica y proyección de presupuesto), Admin (auditoría + configuración).

**5. Sistema de alertas automáticas (7 tipos)** — `CRITICAL_SCORE_UPDATED`, `STOCK_SHORTAGE`, `RECOMMENDATION_FULFILLED`, `EXPIRY_SOON`, `FOLLOW_UP_NEEDED`, `REFERRAL_PENDING`, `SYMPTOM_SEVERITY` — despachadas multi-canal (push, SMS, WhatsApp, email, dashboard en vivo) según severidad y rol.

**6. Caché de estadísticas** — agregados globales/por familia/por bodega en Redis con TTL adaptativo, para que los dashboards no golpeen la base de datos en cada carga.

### Modelo de datos (8 tablas nuevas)

`beneficiaries`, `vulnerability_scores` (histórico), `protocol_recommendations` (base de protocolos), `beneficiary_recommendations` (personalizadas), `care_history` (entregas), `health_referrals` (derivaciones), `alerts`, `statistics_cache`. DDL completo en la subpágina "Módulo 7" y en `docs/archive` (versión original).

### Integración con otros módulos

- **↔ Módulo 1:** enriquece la búsqueda pública con demanda agregada anónima
- **↔ Módulo 3:** detecta automáticamente brechas de stock vs. demanda (Model Observer sobre `StockEntry`)
- **↔ Módulo 6:** toda entrega confirmada actualiza recomendaciones y dispara la notificación de impacto al donante

---

## Módulo 8: Mapa Colaborativo y Multimedia

**Objetivo:** ampliar el Módulo 1 con un mapa colaborativo en tiempo real, evidencia multimedia y códigos QR para compartir puntos de ayuda.

> **Origen:** `09-Analisis-Integracion-Apps-Existentes.md` (Opción 1B "Mapa Colaborativo"), evaluando la app voluntaria **Mapa Emergencia** (Artefacto Films) para reutilización de código. El propio documento lo diagrama como módulo independiente ("Módulo 8: Multimedia + QR, NUEVO, desde Mapa") dentro de su arquitectura de MVP mejorado — no reemplaza al Módulo 1, lo complementa. La otra app evaluada, Censo Roldanillo, no genera un módulo adicional: su funcionalidad de registro de familias es la que dio origen al Módulo 7 (Beneficiarios), ya documentado arriba.

### Funcionalidades

- Mapa interactivo en tiempo real de puntos de ayuda/bodegas (Leaflet.js), con filtros por urgencia y tipo de ayuda
- Creación/edición de puntos por operadores/coordinadores: ubicación (tap o GPS), nombre, urgencia (Crítica/Urgente/Normal), tipo de ayuda que falta/sobra, necesidad de voluntarios, contacto responsable
- Confirmación de vigencia: botón "Sigo aquí, esto está vigente" que refresca el timestamp y evita mostrar información obsoleta
- Reportar incorrección: marca un punto como no verificado y mantiene historial de disputas
- Carga de multimedia: fotos/videos como evidencia de la situación de cada punto
- Compartir: link público a un punto específico, embed en redes sociales, código QR
- Offline-first: mapa base en caché, cambios guardados localmente y sincronizados al reconectar (mismo patrón que Módulos 3 y 6)

### Endpoints REST

| Endpoint | Función |
|---|---|
| `GET /api/public/map-data` | Puntos georreferenciados con semáforo/urgencia |
| `POST /api/map/points` | Crear punto |
| `PATCH /api/map/points/{id}` | Editar punto / confirmar vigencia |
| `POST /api/map/points/{id}/report` | Reportar incorrección |
| `POST /api/map/points/{id}/media` | Subir foto/video |

### Pantallas

Vista de mapa (complementa las tarjetas del Módulo 1), modal de creación/edición de punto, visor de multimedia, generador de código QR para compartir.

### Esfuerzo estimado

~10-12h con reutilización directa de código de Mapa Emergencia (5-6h de adaptación); ~15-18h si se construye desde cero. Ver `09-Analisis-Integracion-Apps-Existentes.md` y `09B-Resumen-Integracion-RAPIDO.md` para el desglose completo.

---

## Módulo 9: Búsqueda Geolocalizada

**Objetivo:** que el ciudadano vea primero los insumos disponibles más cercanos a su ubicación.

> Prioridad 🔴 Alta — dentro del alcance del MVP, no en backlog. Fuente: `03-Funciones-Adicionales-Propuestas.md` (Función 1).

### Funcionalidades

- Captura de geolocalización del ciudadano (Geolocation API, opcional/con permiso)
- Cálculo de distancia a cada bodega con stock disponible (fórmula de Haversine)
- Resultados de búsqueda del Módulo 1 ordenados por cercanía

**Esfuerzo:** 3.5h · **ROI esperado:** -50% tiempo de búsqueda, +30% satisfacción del ciudadano.

---

## Módulo 10: Sugerencias Inteligentes de Búsqueda

**Objetivo:** sugerir insumos relacionados según el historial de búsquedas del usuario, para que un operador o líder comunitario aproveche un solo viaje para varias necesidades.

> Prioridad 🟡 Media — dentro del alcance del MVP; requiere que exista historial de búsquedas acumulado para dar buenas sugerencias. Fuente: `03-Funciones-Adicionales-Propuestas.md` (Función 2).

### Funcionalidades

- Registro del historial de búsqueda por usuario (Redis)
- Motor de sugerencias por categoría relacionada (`SearchSuggestionService`)
- Sugerencias mostradas junto a los resultados de búsqueda (Módulo 1)

**Esfuerzo:** 4h · **ROI esperado:** -30% viajes de abastecimiento, +25% eficiencia logística.

---

## Módulo 11: Reportes Exportables (PDF/Excel)

**Objetivo:** que admin/coordinador generen reportes de stock, trazabilidad y vencimientos con un clic, sin tocar SQL ni depender de un programador.

> Prioridad 🔴 Alta — dentro del alcance del MVP; se apoya directamente en el Módulo 5 (Alertas y Auditoría). Fuente: `03-Funciones-Adicionales-Propuestas.md` (Función 3).

### Funcionalidades

- Plantillas de reporte: stock por bodega, entregas de los últimos 7 días, ítems próximos a vencer
- Exportación a PDF (`barryvdh/laravel-dompdf`) y Excel (`maatwebsite/excel`)
- Extiende los endpoints `GET /api/reports/*` y `POST /api/reports/export` ya definidos en el Módulo 5

**Esfuerzo:** 6.5h · **ROI esperado:** -80% tiempo en reportes manuales, -95% errores humanos.

---

## Módulo 12: Dashboard de Operaciones en Tiempo Real

**Objetivo:** una pantalla única para que el coordinador vea de un vistazo el estado general del sistema y tome decisiones rápidas en crisis.

> Prioridad 🟡 Media — dentro del alcance del MVP; complementa (no reemplaza) los dashboards por rol del Módulo 7. Fuente: `03-Funciones-Adicionales-Propuestas.md` (Función 4).

### Funcionalidades

- Total de insumos en el sistema, semáforo agregado de disponibilidad
- Gráfico de entradas vs. salidas de las últimas 24h (Chart.js)
- Alertas críticas visibles (vencimientos próximos, bodegas sin operador)
- Actualización en vivo vía WebSocket (Laravel Reverb) o polling cada 30s
- Mapa mini de bodegas y su estado (se apoya en el Módulo 8)

**Esfuerzo:** 8.5h · **ROI esperado:** -70% tiempo de toma de decisiones, comunicación con donantes automatizada.

---

## Módulo 13: Trazabilidad por QR/Barcode

**Objetivo:** que cada lote de insumo tenga un código QR único, escaneable en entrada, salida y entrega, para trazabilidad y prueba de cadena de custodia.

> Prioridad 🔴 Alta — dentro del alcance del MVP; es la extensión natural del Módulo 3 (entradas) y Módulo 6 (entregas). Fuente: `03-Funciones-Adicionales-Propuestas.md` (Función 5).

### Funcionalidades

- Generación de QR al registrar una entrada (`simple-qrcode`), con `entry_id`, bodega, ítem, cantidad y vencimiento codificados
- Escaneo en campo con lector móvil (`quasar.dev`) para procesar entradas/salidas automáticamente
- Trazabilidad visual: escanear el QR de un lote muestra su historial completo (quién donó, cuándo llegó, quién lo entregó)
- QR visible en el comprobante de entrega al beneficiario (Módulo 6), como prueba física

**Esfuerzo:** 8h · **ROI esperado:** -90% confirmaciones manuales, +80% probabilidad de detectar falsificaciones.

---

## Fuera del alcance de este documento (para más adelante)

Estas tres iniciativas quedaron identificadas en la documentación pero **no** se elevaron a módulo — son de mayor alcance (requieren decisiones de negocio o inversión adicional) y se revisarán después del lanzamiento:

- **Integración con Telegram/WhatsApp Business API** — los canales de notificación ya están desacoplados (Laravel Notification Channels) y listos para activar cuando se decida
- **Escalamiento a 2-3 municipalidades adicionales** — depende de la arquitectura multi-tenant y del presupuesto ampliado
- **Apps móviles nativas** — solo si la PWA demuestra ser insuficiente en producción

---

## Capacidad base no numerada: administración de bodegas y organizaciones

La especificación v1 incluía un **Módulo 6 "Administración de Bodegas y Organizaciones"** (CRUD de bodegas, asignación de operadores, horarios, capacidad máxima) que en la especificación v2 vigente no aparece como módulo numerado independiente — ese número (6) fue reasignado a "Entregas y Seguimiento" al integrarse el Módulo 7.

Esta funcionalidad sigue siendo necesaria (existen las tablas `warehouses` y `organizations` en el modelo de datos), pero en el plan de entrega actual se resuelve como **datos semilla configurados directamente en el despliegue** (`Día 2: Seed initial data — categories, zones, warehouses`), no como una pantalla de autoservicio para el MVP. Si el proyecto escala a múltiples municipalidades (Fase II+), esta administración sí debería promoverse a un módulo propio con:

- CRUD de bodegas (crear, editar, eliminar)
- Asignación de operadores a bodega
- Configuración de horarios de atención y contactos de emergencia
- Capacidad máxima de almacenamiento
- Endpoints de referencia: `GET/POST /api/warehouses`, `PATCH /api/warehouses/{id}`, `POST /api/warehouses/{id}/assign-operator`

---

## Próximos pasos sugeridos

- [ ] Actualizar el `ENUM` de roles en `users` (DDL) para reflejar los 6 roles vigentes, no solo los 3 originales
- [ ] Decidir si "Administración de Bodegas y Organizaciones" se promueve a módulo propio antes de escalar a más de una bodega/municipalidad
- [ ] Registrar los Módulos 8-13 en el Product Roadmap de Notion (hoy solo están en este catálogo, no tienen checklist/horas por equipo asignadas ahí)
- [ ] Contactar a los creadores de Censo Roldanillo y Mapa Emergencia antes de reutilizar su código (cortesía + verificar licencia), según recomienda `09-Analisis-Integracion-Apps-Existentes.md`
- [ ] Mantener este documento sincronizado si cambia el alcance de algún módulo en el Roadmap de Notion
