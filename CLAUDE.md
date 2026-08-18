# CLAUDE.md

Este archivo guía a Claude Code (claude.ai/code) al trabajar en este repositorio.

## Idioma

Responde siempre en español: explicaciones, mensajes de commit y de PR, y cualquier texto dirigido al usuario. El contenido nuevo (documentos, nombres de tablas/campos de dominio, etc.) también va en español salvo los identificadores técnicos (nombres de tablas, columnas, clases, endpoints), que siguen la convención en inglés ya usada en las specs (`stock_entries`, `ScoringEngine`, `GET /api/public/search`, etc.).

## Qué es este repositorio

**Donaciones Rolda** es una plataforma para rastrear y gestionar la disponibilidad de medicamentos, insumos médicos, alimentos y herramientas durante emergencias locales o catástrofes, pensada inicialmente para el municipio de Roldanillo (Colombia).

**Estado actual: este repositorio contiene únicamente documentación de diseño, negocio y arquitectura (`docs/*.md`) — todavía no existe código de aplicación (no hay `composer.json`, `app/`, ni proyecto Laravel inicializado).** Cuando se empiece a escribir código, se hará conforme a la arquitectura descrita en "Arquitectura técnica planeada" más abajo; no asumas convenciones de otro proyecto Laravel salvo que estén documentadas aquí o en `docs/`.

El roadmap operativo día a día (módulos, funciones, checklists por equipo) se gestiona en **Notion** ("Kit de desarrollo de productos de software" — Product Roadmap + Engineering Tasks). Este repo es la fuente de las decisiones de diseño/negocio; Notion es la fuente de verdad operativa. Hay herramientas MCP de Notion habilitadas en `.claude/settings.local.json` para leer/escribir ahí.

## ⚠️ Cifras y decisiones vigentes — lee esto antes que cualquier documento de `docs/`

La documentación se escribió en varias pasadas el mismo día (17-ago-2026) y llegó a contener tres narrativas de costo/timeline contradictorias, más un diseño duplicado del módulo de beneficiarios. Antes de citar una cifra, un timeline o un diseño de `docs/`, verifica cuál es la versión vigente:

| Decisión | Vigente | Superado / no usar |
|---|---|---|
| Presupuesto (3 meses) | **$8,256 USD** (incl. margen de contingencia 200%) — `ANALISIS-COSTOS-REALES.md` + `OPCIONES-DE-FINANCIAMIENTO.md` | $1,748 (`06-Estimacion-Costos-3Meses.md`), $1,548/$1,210 (`05-Analisis-Infraestructura-AWS.md`), $6,050 (`archive/`, obsoleto) |
| Timeline / lanzamiento | **7 días, lanzamiento 23 de agosto de 2026** — `00-Presentacion-Ejecutiva-Donaciones-Rolda.md` | 11 días / 2 de septiembre (`07-Plan-Entrega-MVP.md`, spec técnica v2); 30 de julio (obsoleto) |
| Diseño del módulo de Beneficiarios | **`11-Modulo-7-Beneficiarios-Estadisticas-Inteligentes.md`** — scoring 0-100 en 3 niveles, 8 tablas | `archive/10-Modulo-Estadisticas-Census-Priorization.md` (esquema incompatible, descartado) |
| Especificación técnica base | **`Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md`** (integra Módulo 7) | `archive/01-Especificaciones-Tecnicas-Expandidas.md` (v1, pre-Módulo 7) |

El desglose de esfuerzo por módulo/equipo más granular sigue siendo el de `07-Plan-Entrega-MVP.md` + `11-Modulo-7-...md` (base numérica del roadmap en Notion), aunque su calendario de 11 días no es el compromiso público vigente (7 días). Ver el índice completo en `docs/00-INDICE.md`, que es la puerta de entrada oficial a toda la documentación.

## Cómo moverte en la documentación

- `docs/00-INDICE.md` — punto de entrada; mapa completo de documentos por tema, con la tabla de vigencia arriba.
- `docs/00-Presentacion-Ejecutiva-Donaciones-Rolda.md` + `Presentacion-Visual-Donaciones-Rolda.html` — para audiencia no técnica (autoridades, ONG, donantes).
- `docs/14-Modulos-y-Funcionalidades.md` — catálogo funcional de referencia: objetivo, endpoints y pantallas de cada módulo.
- `docs/Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md` — spec técnica vigente (stack, arquitectura, 7 módulos núcleo).
- `docs/02-Modelo-Datos-MER-DDL.md` + `docs/11-Modulo-7-...md` — modelo de datos (ver nota de inconsistencia abajo, están sin fusionar).
- `docs/08-Matriz-Compliance-Privacy-LSPP.md` — cumplimiento Ley 1581/2012 (Colombia): PII, retención, derechos del titular, brechas.
- `docs/12-Diagramas-Flujo-Detallados.md`, `docs/13-Diagramas-Arquitectura.md`, `docs/04-Diagramas-Flujos-Modulos.md` — diagramas (ver mapa en `docs/INDICE-DIAGRAMAS.md`).
- `docs/archive/` — documentos superados, conservados por trazabilidad; cada uno indica en su encabezado qué lo reemplaza. No los uses como fuente de cifras o diseño vigente.

Al editar o crear documentos en `docs/`, sigue el patrón ya establecido: encabezado con nota de vigencia si el documento fue superado parcialmente, y actualiza `docs/00-INDICE.md` si agregas o reemplazas un documento.

## Arquitectura técnica planeada (para cuando se implemente el código)

Esto es lo que las specs definen como objetivo — todavía no implementado. Al empezar a codear, sigue esto salvo decisión explícita en contra.

### Stack

| Componente | Tecnología |
|---|---|
| Backend | Laravel 11/12 (PHP 8.3+) |
| Relacional | MySQL 8.0 / MariaDB 10.11 |
| Cache / Queue | Redis 7.0 |
| Frontend | Blade + Livewire 3.x + Alpine.js (sin SPA pesado) |
| Estilos | Tailwind CSS 3.x |
| Offline (PWA) | IndexedDB vía Dexie.js + Service Worker |
| Antibot | Cloudflare Turnstile |
| Mapas | Leaflet.js + OpenStreetMap |
| Tiempo real | Laravel Reverb / Redis (o Pusher) |

Filosofía "Clean Laravel": controladores delgados + Action classes de responsabilidad única para la lógica de negocio, FormRequests dedicados para validación, Enums tipados en PHP (`StockStatus`, `TrafficLightSeverity`, `UserRole`, `PriorityLevel`, `AlertType`), DTOs entre API y capa de sincronización offline.

### Arquitectura general

Monolito Laravel (no microservicios) con frontend server-rendered (Blade + Livewire + Alpine), más una capa PWA offline-first (IndexedDB/Dexie + Service Worker) para el operador de campo sin conexión estable. Comunicación HTTPS/REST/JSON + WebSocket para tiempo real. Persistencia mixta: MySQL (ACID) + Redis (cache/queue/sesiones, prefijo de claves `donaciones:`, ej. `stats:global:v2` TTL 1h, `semaforo:item:{id}` TTL 5m) + IndexedDB en cliente (`stock_entries_pending`, `sync_queue` con `uuid`, `cache_beneficiaries`).

### Los 13 módulos (7 núcleo + 6 adicionales)

| # | Módulo | Objetivo |
|---|---|---|
| 1 | Portal Público de Búsqueda | Ciudadanos buscan insumos sin autenticarse |
| 2 | Autenticación y Roles | Usuarios, permisos y acceso por rol |
| 3 | Gestión de Inventarios | Operadores registran entradas/salidas offline-first |
| 4 | Control Maestro de Ítems | Admin aprueba ítems nuevos y consolida duplicados |
| 5 | Alertas y Auditoría | Notificaciones, trazabilidad, reportes |
| 6 | Entregas y Seguimiento | Entrega física a beneficiarios; cierra el ciclo con M7 |
| 7 | Beneficiarios + Estadísticas Inteligentes | Prioriza por vulnerabilidad y recomienda entregas |
| 8-13 | Mapa Colaborativo, Búsqueda Geolocalizada, Sugerencias Inteligentes, Reportes Exportables, Dashboard Tiempo Real, Trazabilidad QR/Barcode | Funciones adicionales de `03-Funciones-Adicionales-Propuestas.md` |

M1-M6 son la base operativa (inventario → entrega). M7 se integra transversalmente: enriquece M1 (búsqueda con demanda agregada anónima, sin nombres, por LSPP), reacciona a M3 (Model Observer sobre `StockEntry` detecta brecha stock vs demanda) y se dispara al confirmarse una entrega en M6 (marca recomendaciones `FULFILLED`, actualiza `care_history`, notifica al donante).

**6 roles:** `operator` (campo), `coordinator`, `admin`, `doctor`, `donor`, `municipal` — más el ciudadano anónimo sin login. Autoriza por Policies/Gates (ej. `BeneficiaryPolicy`), no solo por rol global.

### Modelo de datos — dos documentos sin fusionar todavía

`docs/02-Modelo-Datos-MER-DDL.md` (12 tablas de M1-6: `geographic_zones`, `organizations`, `users`, `warehouses`, `categories`, `master_items`, `stock_entries`, `stock_exits`, `audit_logs`, `internal_notifications`, `warehouse_assignments`, `expiry_alerts`) y `docs/11-Modulo-7-...md` (8 tablas de M7: `beneficiaries`, `vulnerability_scores`, `protocol_recommendations`, `beneficiary_recommendations`, `care_history`, `health_referrals`, `alerts`, `statistics_cache`) **no están fusionados en un único DDL**. Si implementas migraciones, tenlo en cuenta y no asumas que uno de los dos documentos por sí solo describe el esquema completo.

**Inconsistencia conocida y sin resolver:** el DDL de `02-Modelo-Datos-MER-DDL.md` define `users.role ENUM('admin', 'acopio_operator', 'field_leader')` (3 roles) mientras que la spec vigente y el Módulo 7 ya operan con 6 roles (`operator/coordinator/admin/doctor/donor/municipal`). Actualizar ese enum es una tarea pendiente dentro del Módulo 2, no algo ya resuelto.

**Campos PII a encriptar a nivel de aplicación (AES-256, cast `Encrypted::class`):** `users.document_id`, `users.phone`, `users.email`, `warehouses.contact_phone`, `stock_exits.received_by_name`. Trátalo como requisito desde el primer commit que toque estas tablas, no como mejora futura.

### Motor de scoring de vulnerabilidad (Módulo 7)

Score ponderado 0-100 sobre 4 factores — demográfico (0-30), salud (0-30), nutricional (0-20), social (0-20) — que determina 3 niveles de prioridad: ≥70 crítico, 40-69 prioritario, <40 normal. Ver `ScoringEngine` (`App\Services\VulnerabilityScoring`) y `RecommendationService` (`App\Services\RecommendationEngine`) documentados con código de ejemplo en `docs/11-Modulo-7-...md`. Si se modifica la lógica de puntaje, hazlo ahí y en el código a la vez — es el criterio de priorización de todo el sistema, no un detalle cosmético.

### Cumplimiento LSPP (Ley 1581/2012, Colombia) — reglas de negocio obligatorias

- Municipalidad de Roldanillo es responsable legal del tratamiento; Donaciones Rolda es encargado técnico (requiere NDA con desarrolladores).
- Derecho al olvido (`anonymize_user($user_id)`): reemplaza PII por "USUARIO_ANONIMO", mantiene `audit_logs` pero anonimiza `user_id`. No aplica si hay entregas pendientes o proceso DNPD activo.
- Retención: usuario inactivo 12 meses → auto-anonimizar; `audit_logs` archivados → 2 años; logs de cumplimiento (Glacier) → 7 años; búsquedas públicas anónimas → 90 días. Job diario `DataRetentionCleanupJob` a las 02:00 UTC.
- Brechas de seguridad: notificación obligatoria a DNPD y afectados dentro de 72 horas (24h si es crítica, >1000 registros).
- Encriptación TLS 1.3 en tránsito, AES-256 en reposo, KMS para llaves; `.env` nunca en git, rotación de secretos cada 90 días.

No implementes manejo de datos de beneficiarios/usuarios sin considerar estas reglas — están detalladas con endpoints y plazos exactos en `docs/08-Matriz-Compliance-Privacy-LSPP.md`.

### Despliegue e infraestructura

AWS: Cloudflare (CDN/WAF/DDoS) → ALB → ECS Fargate (API + queue workers, autoscaling 2-4 tasks) → RDS Aurora MySQL Multi-AZ + ElastiCache Redis, en VPC con subnets públicas/privadas por capa. Detalle completo (security groups, alarms, DR) en `docs/13-Diagramas-Arquitectura.md` y `docs/05-Analisis-Infraestructura-AWS.md` (cifra de costo de este último desactualizada, ver tabla de vigencia).

CI/CD planeado: GitHub Actions — `tests.yml` (Pint, PHPUnit/Pest, PHPStan, cobertura a Codecov) y `deploy.yml` (build Docker → ECR → ECS → smoke test → rollback automático si falla el health check). Git flow: feature branch → PR con review → `develop` → staging → `release/vX.Y.Z` → `main`.

## Contradicciones conocidas sin resolver (transparencia)

- `02-Modelo-Datos-MER-DDL.md` no define los roles `coordinator/doctor/donor/municipal` que sí usa la spec vigente y el Módulo 7 — falta fusionar el DDL de roles.
- El esfuerzo del Módulo 7 se cita como "donado ($0)" en la línea comercial y como "$2,100-$2,250 a $50/h" en `archive/RESUMEN-ACTUALIZACION-MODULO-7.md` — esta última cifra no está vigente.

No asumas que estas contradicciones ya fueron resueltas al usar los documentos; si tu tarea depende de una de ellas, señálalo en vez de elegir una versión en silencio.
