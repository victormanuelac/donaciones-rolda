# CLAUDE.md

Este archivo guía a Claude Code (claude.ai/code) al trabajar en este repositorio.

## Idioma

Responde siempre en español: explicaciones, mensajes de commit y de PR, y cualquier texto dirigido al usuario. El contenido nuevo (documentos, nombres de tablas/campos de dominio, etc.) también va en español salvo los identificadores técnicos (nombres de tablas, columnas, clases, endpoints), que siguen la convención en inglés ya usada en las specs (`stock_entries`, `ScoringEngine`, `GET /api/public/search`, etc.).

## Qué es este repositorio

**Donaciones Rolda** es una plataforma para rastrear y gestionar la disponibilidad de medicamentos, insumos médicos, alimentos y herramientas durante emergencias locales o catástrofes, pensada inicialmente para el municipio de Roldanillo (Colombia).

**Estado actual: además de la documentación de diseño/negocio (`docs/*.md`), ya existe el proyecto Laravel de la aplicación, en la raíz de este mismo repositorio** (`app/`, `composer.json`, `routes/`, etc. — no en una subcarpeta). Se generó con `laravel new` el 18-ago-2026 usando el starter kit de Livewire; ver "Arquitectura técnica" abajo para el detalle de stack y las diferencias con la spec original que ya se resolvieron. Laravel Boost está instalado (`boost.json`, `.mcp.json`, skills en `.claude/skills/`) para asistencia de IA — sus reglas específicas de Laravel están en el bloque de guías de Boost al final de este archivo (se regenera automáticamente con `composer run post-update-cmd` / `php artisan boost:update`; no lo edites a mano). **Cuidado:** ese comando corre en cada `composer install`/`composer update` y busca el marcador de apertura de ese bloque en todo el archivo — si necesitas referirte a él en prosa, no escribas el tag literal, descríbelo con palabras (ya pasó una vez: una mención inline del tag truncó y borró el resto de este documento).

## ⚠️ Cifras y decisiones vigentes — lee esto antes que cualquier documento de `docs/`

La documentación se escribió en varias pasadas el mismo día (17-ago-2026) y llegó a contener tres narrativas de costo/timeline contradictorias, más un diseño duplicado del módulo de beneficiarios. Antes de citar una cifra, un timeline o un diseño de `docs/`, verifica cuál es la versión vigente:

| Decisión | Vigente | Superado / no usar |
|---|---|---|
| Presupuesto (3 meses) | **$8,256 USD** (incl. margen de contingencia 200%) — `ANALISIS-COSTOS-REALES.md` + `OPCIONES-DE-FINANCIAMIENTO.md` | $1,748 (`06-Estimacion-Costos-3Meses.md`), $1,548/$1,210 (`05-Analisis-Infraestructura-AWS.md`), $6,050 (`archive/`, obsoleto) |
| Timeline / lanzamiento | **7 días, lanzamiento 23 de agosto de 2026** — `00-Presentacion-Ejecutiva-Donaciones-Rolda.md` | 11 días / 2 de septiembre (`07-Plan-Entrega-MVP.md`, spec técnica v2); 30 de julio (obsoleto) |
| Diseño del módulo de Beneficiarios | **`11-Modulo-7-Beneficiarios-Estadisticas-Inteligentes.md`** — scoring 0-100 en 3 niveles, 8 tablas + censo georreferenciado (ver más abajo) | `archive/10-Modulo-Estadisticas-Census-Priorization.md` (esquema incompatible, descartado) |
| Especificación técnica base | **`Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md`** (integra Módulo 7) | `archive/01-Especificaciones-Tecnicas-Expandidas.md` (v1, pre-Módulo 7) |
| Diseño visual | **`docs/15-Sistema-de-Diseno-Visual.md`** ("Stark Dim", solo modo oscuro) — normativo para toda pantalla nueva | N/A, es la primera versión |

El desglose de esfuerzo por módulo/equipo más granular sigue siendo el de `07-Plan-Entrega-MVP.md` + `11-Modulo-7-...md` (base numérica del roadmap en Notion), aunque su calendario de 11 días no es el compromiso público vigente (7 días). Ver el índice completo en `docs/00-INDICE.md`, que es la puerta de entrada oficial a toda la documentación. El roadmap operativo día a día (hitos por módulo, prioridades, tareas de ingeniería) vive en Notion — bases de datos "Product Roadmap" y "Engineering Tasks" — no en este repo.

## Cómo moverte en la documentación

- `docs/00-INDICE.md` — punto de entrada; mapa completo de documentos por tema, con la tabla de vigencia arriba.
- `docs/00-Presentacion-Ejecutiva-Donaciones-Rolda.md` + `Presentacion-Visual-Donaciones-Rolda.html` — para audiencia no técnica (autoridades, ONG, donantes).
- `docs/14-Modulos-y-Funcionalidades.md` — catálogo funcional de referencia: objetivo, endpoints y pantallas de cada módulo.
- `docs/Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md` — spec técnica vigente (stack, arquitectura, 7 módulos núcleo).
- `docs/02-Modelo-Datos-MER-DDL.md` + `docs/11-Modulo-7-...md` — modelo de datos (ver nota de inconsistencia abajo, están sin fusionar).
- `docs/15-Sistema-de-Diseno-Visual.md` — estándar de diseño visual normativo (tokens, tipografía, componentes, reglas de rendimiento).
- `docs/08-Matriz-Compliance-Privacy-LSPP.md` — cumplimiento Ley 1581/2012 (Colombia): PII, retención, derechos del titular, brechas.
- `docs/12-Diagramas-Flujo-Detallados.md`, `docs/13-Diagramas-Arquitectura.md`, `docs/04-Diagramas-Flujos-Modulos.md` — diagramas (ver mapa en `docs/INDICE-DIAGRAMAS.md`). El flujo de Git documentado en `docs/13` (`develop` → `release/vX.Y.Z` → `main`) está superado — ver "Flujo de ramas y despliegue" más abajo.
- `docs/archive/` — documentos superados, conservados por trazabilidad; cada uno indica en su encabezado qué lo reemplaza. No los uses como fuente de cifras o diseño vigente.

Al editar o crear documentos en `docs/`, sigue el patrón ya establecido: encabezado con nota de vigencia si el documento fue superado parcialmente, y actualiza `docs/00-INDICE.md` si agregas o reemplazas un documento.

## Flujo de ramas y despliegue

```
feature/xxx  →  PR (squash-merge, firmado)  →  test  →  (CI/CD al ambiente de pruebas en EC2)  →  PR aprobado  →  main
```

- Toda rama nueva sale de `main` (`feature/...`, `fix/...`, `docs/...`).
- El PR se abre contra `test`, no contra `main`. Mergear a `test` dispara el pipeline hacia el ambiente de pruebas en EC2 (ver `README.md`, sección "Ambiente de pruebas").
- Solo después de validar en ese ambiente y con aprobación, se abre PR de `test` a `main` (producción).
- `main` y `test` tienen un ruleset real en GitHub (no es solo una convención documentada), **acotado a esas dos ramas** (no a todo el repo, para no bloquear force-push/amend en ramas feature de trabajo): PR obligatorio con ≥1 aprobación, **squash-merge only** (rechaza commits de merge, historia lineal) y **commits firmados** (SSH o GPG — la clave debe estar registrada en GitHub como *Signing Key*, no solo como *Authentication Key*) — ver detalle de configuración en `README.md` sección 4. Quién tiene permiso de push en absoluto lo define el rol de colaborador (Settings → Collaborators), no el ruleset. Un push directo, con commits de merge o sin firmar es rechazado salvo que quien lo haga tenga bypass de owner; no uses ese bypass para saltarte el flujo, es solo para emergencias. No confundir con el flujo `develop`/`release/vX.Y.Z` que describe `docs/13-Diagramas-Arquitectura.md`: ese diagrama quedó superado por este esquema más simple (18-ago-2026).

## Arquitectura técnica

### Stack

La spec original (`Especificaciones_Técnicas_y_Arquitectura_v2.md`) fue escrita el 17-ago-2026 con Laravel 11/12, Livewire 3.x y Tailwind 3.x como objetivo. Al instalar el proyecto un día después, el instalador oficial de Laravel ya traía versiones mayores más nuevas por defecto — se decidió **quedarse con lo que instala el `laravel new` actual en vez de forzar un downgrade**, y esta tabla ya refleja esa decisión (no la spec original):

| Componente | Tecnología |
|---|---|
| Backend | Laravel 13 (PHP 8.3+, entorno local en 8.5) |
| Relacional | MySQL 8.4 (contenedor de Sail) / MariaDB 10.11 |
| Cache / Queue | Redis 7.0 |
| Frontend | Blade + **Livewire 4.x** + Alpine.js (sin SPA pesado) |
| Componentes UI | **Flux UI 2.x** (`livewire/flux`, edición gratuita) — no estaba en la spec original; se adoptó porque viene con el starter kit y acelera tablas/forms/modales del MVP. |
| Estilos | **Tailwind CSS 4.x** — tokens del sistema de diseño en `resources/css/app.css`, ver `docs/15-Sistema-de-Diseno-Visual.md` |
| Tipografía | Space Grotesk (títulos/labels/botones) + Figtree (cuerpo), autoalojadas vía `laravel-vite-plugin/fonts` (`bunny()`) — sin Google Fonts CDN, crítico para el censo offline |
| Offline (PWA) | IndexedDB vía Dexie.js + Service Worker |
| Antibot | Cloudflare Turnstile |
| Mapas | Leaflet.js + OpenStreetMap |
| Tiempo real | Laravel Reverb / Redis (o Pusher) |
| Entorno local/pruebas | Laravel Sail (`compose.yaml`) — MySQL + Redis en Docker, ver `README.md` |
| Testing | Pest 5 (corre sobre PHPUnit; la spec menciona "phpunit" genéricamente, se interpreta como cumplido por Pest) |
| Análisis estático | Larastan/PHPStan + Pint (`composer run types:check`, `composer run lint`) |

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
| 7 | Beneficiarios + Estadísticas Inteligentes | Prioriza por vulnerabilidad, censo georreferenciado y recomienda entregas |
| 8-13 | Mapa Colaborativo, Búsqueda Geolocalizada, Sugerencias Inteligentes, Reportes Exportables, Dashboard Tiempo Real, Trazabilidad QR/Barcode | Funciones adicionales de `03-Funciones-Adicionales-Propuestas.md` |

M1-M6 son la base operativa (inventario → entrega). M7 se integra transversalmente: enriquece M1 (búsqueda con demanda agregada anónima, sin nombres, por LSPP), reacciona a M3 (Model Observer sobre `StockEntry` detecta brecha stock vs demanda) y se dispara al confirmarse una entrega en M6 (marca recomendaciones `FULFILLED`, actualiza `care_history`, notifica al donante).

**6 roles:** `operator` (campo), `coordinator`, `admin`, `doctor`, `donor`, `municipal` — más el ciudadano anónimo sin login. Autoriza por Policies/Gates (ej. `BeneficiaryPolicy`), no solo por rol global.

### Modelo de datos — dos documentos sin fusionar todavía

`docs/02-Modelo-Datos-MER-DDL.md` (12 tablas de M1-6: `geographic_zones`, `organizations`, `users`, `warehouses`, `categories`, `master_items`, `stock_entries`, `stock_exits`, `audit_logs`, `internal_notifications`, `warehouse_assignments`, `expiry_alerts`) y `docs/11-Modulo-7-...md` (8 tablas de M7: `beneficiaries`, `vulnerability_scores`, `protocol_recommendations`, `beneficiary_recommendations`, `care_history`, `health_referrals`, `alerts`, `statistics_cache`) **no están fusionados en un único DDL**. Si implementas migraciones, tenlo en cuenta y no asumas que uno de los dos documentos por sí solo describe el esquema completo.

**Inconsistencia conocida — resuelta en código, no en el documento:** el DDL de `02-Modelo-Datos-MER-DDL.md` (documento de diseño) todavía muestra `users.role ENUM('admin', 'acopio_operator', 'field_leader')` (3 roles) y no se ha corregido ahí. En la app real ya está resuelto: `app/Enums/UserRole.php` (backed enum, cast en `User::casts()`) define los 6 roles vigentes (`admin/operator/coordinator/doctor/donor/municipal`), con columna `role` (+ `status` vía `App\Enums\UserStatus`) agregada a `users` en `database/migrations/2026_08_18_220710_add_role_and_status_to_users_table.php`. Sigue sin implementarse: RBAC/middleware de autorización por rol, panel de aprobación de usuarios (`status=pending`), y las Policies por recurso.

**Gap conocido en el Módulo 7 (censo):** el DDL de `beneficiaries` referencia `family_id → families(id)` y `census_entry_id → census_entries(id)`, pero ninguna de las dos tablas está definida en ningún documento — falta el modelo de censo por hogar (con GPS y condiciones de vivienda). Diseño de esas dos tablas nuevas documentado directamente en la página del Módulo 7 en Notion y en las Engineering Tasks correspondientes.

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

- **Ambiente de pruebas (actual):** instancia EC2 corriendo Docker/Sail directo — ver `README.md`, sección "Ambiente de pruebas en la instancia EC2". Es deliberadamente simple porque el pipeline ECS/Terraform todavía no existe.
- **Producción (objetivo, no implementado aún):** AWS: Cloudflare (CDN/WAF/DDoS) → ALB → ECS Fargate (API + queue workers, autoscaling 2-4 tasks) → RDS Aurora MySQL Multi-AZ + ElastiCache Redis, en VPC con subnets públicas/privadas por capa. Detalle completo (security groups, alarms, DR) en `docs/13-Diagramas-Arquitectura.md` y `docs/05-Analisis-Infraestructura-AWS.md` (cifra de costo de este último desactualizada, ver tabla de vigencia).
- **CI/CD:** GitHub Actions — `.github/workflows/tests.yml` corre en PRs y push a `main`/`test` (Pint, Pest, PHPStan). No existe todavía `deploy.yml`; el despliegue al ambiente de pruebas EC2 es manual (`git pull` + rebuild, ver README) hasta que se automatice.

## Contradicciones conocidas sin resolver (transparencia)

- `02-Modelo-Datos-MER-DDL.md` no define los roles `coordinator/doctor/donor/municipal` que sí usa la spec vigente y el Módulo 7 — falta fusionar el DDL de roles.
- El esfuerzo del Módulo 7 se cita como "donado ($0)" en la línea comercial y como "$2,100-$2,250 a $50/h" en `archive/RESUMEN-ACTUALIZACION-MODULO-7.md` — esta última cifra no está vigente.

No asumas que estas contradicciones ya fueron resueltas al usar los documentos; si tu tarea depende de una de ellas, señálalo en vez de elegir una versión en silencio.

---

*La sección siguiente la genera y mantiene Laravel Boost (`composer run post-update-cmd` / `php artisan boost:update`). No la edites a mano: cualquier cambio manual se pierde en la próxima regeneración. Es un complemento a las reglas de arriba, no un reemplazo — las reglas de idioma, dominio, flujo de ramas y arquitectura de este documento siguen aplicando.*

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
