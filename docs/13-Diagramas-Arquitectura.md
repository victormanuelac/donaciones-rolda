# 🏗️ DIAGRAMAS DE ARQUITECTURA Y RELACIONES — Módulo 7

**Donaciones Rolda: Componentes, Capas y Flujo de Datos**

**Versión:** 1.0  
**Formato:** ASCII Diagrams + Texto  
**Complemento:** 12-Diagramas-Flujo-Detallados.md

---

## 📋 TABLA DE CONTENIDOS

1. [Arquitectura de Capas](#1-arquitectura-de-capas)
2. [Diagrama de Entidades (ER)](#2-diagrama-de-entidades-módulo-7)
3. [Flujo de Datos: Request → Response](#3-flujo-de-datos-request--response)
4. [Integración entre Módulos](#4-integración-entre-módulos-1-3-6-7)
5. [Componentes Frontend por Rol](#5-componentes-frontend-por-rol)
6. [Pipeline CI/CD](#6-pipeline-cicd)
7. [Deployment Architecture](#7-deployment-architecture)
8. [Security & Privacy Layers](#8-security--privacy-layers)

---

---

## 1. ARQUITECTURA DE CAPAS

```
┌──────────────────────────────────────────────────────────────────┐
│                         CLIENT LAYER (PWA/Mobile)               │
│  ┌────────────────┬────────────────┬──────────────────┐          │
│  │   Búsqueda     │   Operador     │   Coordinador    │  ...     │
│  │   Pública      │   (Mobile)     │   (Dashboard)    │          │
│  │   (Módulo 1)   │   (Offline)    │   (Exec)         │          │
│  └────────┬───────┴────────┬───────┴────────┬─────────┘          │
│           │                │                │                    │
│           │   Realm State  │   Observable   │  Redux/Vuex        │
│           └────────────────┼────────────────┘                    │
│                            │                                     │
│                   (Sync cuando hay conexión)                     │
│                            │                                     │
│           ┌────────────────┴────────────────┐                    │
│           │                                 │                    │
│           ▼                                 ▼                    │
│      Service Worker              Dexie.js (IndexedDB)           │
│      (PWA Cache Strategy)         (Local Data Store)            │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                                  │
                    ┌─────────────┴──────────────┐
                    │ (HTTPS / REST API)         │
                    │ (JSON)                     │
                    ▼                            ▼
┌──────────────────────────────────────────────────────────────────┐
│                         API GATEWAY LAYER                        │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ • CORS Middleware                                        │   │
│  │ • Rate Limiter (Redis-backed)                            │   │
│  │ • JWT/Session Auth (Laravel Sanctum)                     │   │
│  │ • Request Validation (FormRequest)                       │   │
│  │ • HTTPS + TLS 1.3                                        │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                                  │
                    ┌─────────────┴──────────────┐
                    │ (Laravel Routing)          │
                    ▼
┌──────────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER (Laravel)                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ CONTROLLERS (Slim, Action-based)                         │   │
│  │ ├─ BeneficiaryController                                │   │
│  │ ├─ RecommendationController                             │   │
│  │ ├─ AlertController                                      │   │
│  │ ├─ HealthReferralController                             │   │
│  │ ├─ StockExitController (M3 ↔ M7)                        │   │
│  │ └─ StatsController                                      │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                  │                               │
│  ┌──────────────────────────────┴──────────────────────────┐   │
│  │ SERVICES & ENGINES (Business Logic)                     │   │
│  │ ├─ ScoringEngine                                        │   │
│  │ │  └─ calculateDemographic(), calculateHealth()...     │   │
│  │ ├─ RecommendationService                               │   │
│  │ │  └─ generateRecommendations(), findApplicableProtocols()
│  │ ├─ AlertService                                        │   │
│  │ │  └─ dispatchAlert(), sendMultiChannel()             │   │
│  │ ├─ StatisticsService                                   │   │
│  │ │  └─ aggregateBeneficiaries(), calculateTopNeeds()   │   │
│  │ └─ IntegrationService                                  │   │
│  │    └─ Module1↔7 search, Module3↔7 stock gaps...        │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                  │                               │
│  ┌──────────────────────────────┴──────────────────────────┐   │
│  │ MODELS & RELATIONS (Eloquent ORM)                       │   │
│  │ ├─ Beneficiary (con scoring, recomendaciones)           │   │
│  │ ├─ VulnerabilityScore (histórico)                       │   │
│  │ ├─ ProtocolRecommendation (base de protocolos)          │   │
│  │ ├─ BeneficiaryRecommendation (personalizadas)           │   │
│  │ ├─ CareHistory (entregas)                               │   │
│  │ ├─ HealthReferral (derivaciones)                        │   │
│  │ ├─ Alert (alertas)                                      │   │
│  │ └─ Master/Pivot models (M1, M3, M6)                     │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ EVENT SYSTEM (Laravel Events + Queues)                  │   │
│  │ ├─ BeneficiaryUpdated → dispatchEvent()                │   │
│  │ ├─ CriticalScoreDetected → AlertJob()                  │   │
│  │ ├─ StockExitCreated → UpdateRecommendations()          │   │
│  │ └─ Async Jobs (Redis Queue)                            │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                                  │
                    ┌─────────────┴──────────────┐
                    │ (Doctrine/Eloquent ORM)    │
                    ▼
┌──────────────────────────────────────────────────────────────────┐
│                     DATA LAYER                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ MySQL 8.0 / MariaDB 10.11 (Persistent)                  │   │
│  │ ├─ Transaccional (ACID)                                 │   │
│  │ ├─ Índices compuestos (performance)                     │   │
│  │ ├─ Replicación (HA)                                     │   │
│  │ └─ Backups diarios                                      │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Redis 7.0 (Cache & Queues)                              │   │
│  │ ├─ stats:global:v2 (1h TTL)                             │   │
│  │ ├─ recommendations:* (1h TTL)                           │   │
│  │ ├─ alerts:pending:* (24h TTL)                           │   │
│  │ ├─ Rate limiting                                        │   │
│  │ ├─ Session store                                        │   │
│  │ └─ Job Queue (async)                                    │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ IndexedDB (Client-side, Offline)                        │   │
│  │ ├─ stock_entries_pending                                │   │
│  │ ├─ sync_queue                                           │   │
│  │ ├─ cache_beneficiaries                                  │   │
│  │ └─ localStorage (preferences)                           │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
                                  │
                    ┌─────────────┴──────────────┐
                    │ (Notification Channels)    │
                    ▼
┌──────────────────────────────────────────────────────────────────┐
│                    EXTERNAL INTEGRATIONS                         │
│  ├─ Firebase Cloud Messaging (Push notifications)               │
│  ├─ Twilio (SMS)                                                │
│  ├─ Meta API (WhatsApp)                                         │
│  ├─ SMTP Server (Email)                                         │
│  ├─ Cloudflare Turnstile (Captcha)                              │
│  ├─ Leaflet.js + OpenStreetMap (Maps)                           │
│  └─ Puesto de Salud APIs (External integration)                 │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

---

---

## 2. DIAGRAMA DE ENTIDADES (Módulo 7)

```
┌─────────────────────────────────────────────────────────────────┐
│                   MODELOS PRINCIPALES                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  families (De Módulo 2: Censo)                                 │
│  ┌──────────────────┐                                          │
│  │ id (PK)          │                                          │
│  │ name             │                                          │
│  │ address          │                                          │
│  │ lat/lng          │                                          │
│  │ census_date      │                                          │
│  └──────────────────┘                                          │
│         │                                                       │
│         │ 1:N                                                   │
│         │                                                       │
│         ▼                                                       │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ beneficiaries (NUEVA - Módulo 7)                         │ │
│  ├──────────────────────────────────────────────────────────┤ │
│  │ id (PK)                                                  │ │
│  │ family_id (FK → families.id)                             │ │
│  │ first_name, last_name, date_of_birth, gender            │ │
│  │ phone, email                                             │ │
│  │                                                          │ │
│  │ [SCORING]                                               │ │
│  │ vulnerability_score (0-100)                             │ │
│  │ priority_level (CRITICAL|PRIORITY|NORMAL)               │ │
│  │ last_score_update                                        │ │
│  │                                                          │ │
│  │ [HEALTH - Autorreporte]                                 │ │
│  │ chronic_conditions (JSON)                               │ │
│  │ current_symptoms (JSON)                                 │ │
│  │ is_pregnant, pregnancy_trimester                        │ │
│  │ has_disability, disability_type                         │ │
│  │ last_medical_review, medical_notes                      │ │
│  │                                                          │ │
│  │ [SOCIAL]                                                │ │
│  │ is_single_parent, has_no_home                           │ │
│  │ employment_status, educational_level                    │ │
│  │                                                          │ │
│  │ [PRIVACY - LSPP]                                        │ │
│  │ privacy_consent, data_visibility                        │ │
│  │ registered_by_user_id, registered_at                    │ │
│  └──────────────────────────────────────────────────────────┘ │
│         │                                                       │
│         │ 1:N                                                   │
│         │ 1:N                                                   │
│         │ 1:N                                                   │
│         │ 1:N                                                   │
│         │ 1:N                                                   │
│         │                                                       │
│  ┌──────┴──────┬──────────┬───────────┬──────────┬──────────┐ │
│  ▼             ▼          ▼           ▼          ▼          ▼ │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ vulnerability_scores (Histórico)                        │  │
│ ├──────────────────────────────────────────────────────────┤  │
│ │ id, beneficiary_id (FK)                                 │  │
│ │ demographic_score, health_score                         │  │
│ │ nutritional_score, social_score                         │  │
│ │ total_score, priority_level                             │  │
│ │ contributing_factors (JSON)                             │  │
│ │ calculated_at, calculated_by_rule_version               │  │
│ └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ beneficiary_recommendations (Personalizadas)            │  │
│ ├──────────────────────────────────────────────────────────┤  │
│ │ id, beneficiary_id (FK), protocol_id (FK)               │  │
│ │ item_id (FK → master_items)                             │  │
│ │ quantity_recommended, frequency, duration_days          │  │
│ │ status (PENDING|IN_PROGRESS|FULFILLED|EXPIRED)          │  │
│ │ fulfillment_percentage                                  │  │
│ │ available_stock, available_warehouses (JSON)            │  │
│ │ recommended_at, fulfilled_at, notes                     │  │
│ └──────────────────────────────────────────────────────────┘  │
│         │          │                                            │
│         │ N:M      │ 1:N                                        │
│         │          │                                            │
│         ▼          ▼                                            │
│ ┌──────────────────────────────────┐  ┌────────────────────┐  │
│ │ protocol_recommendations         │  │ care_history       │  │
│ ├──────────────────────────────────┤  ├────────────────────┤  │
│ │ id (PK)                          │  │ id, beneficiary_id │  │
│ │ protocol_name                    │  │ items_delivered    │  │
│ │ source (WHO|ICBF|LOCAL|...)      │  │ (JSON)             │  │
│ │ trigger_condition (JSON)         │  │ delivery_date      │  │
│ │ recommended_items (JSON)         │  │ delivered_by_user  │  │
│ │ confidence_level (0.0-1.0)       │  │ follow_up_*        │  │
│ │ requires_medical_approval        │  │ notes, photo_path  │  │
│ │ is_active, valid_from/until      │  └────────────────────┘  │
│ └──────────────────────────────────┘                           │
│         │                                                       │
│         │ 1:N                                                   │
│         │                                                       │
│         ▼                                                       │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ health_referrals (Derivaciones)                         │  │
│ ├──────────────────────────────────────────────────────────┤  │
│ │ id, beneficiary_id (FK), referred_by_user_id (FK)       │  │
│ │ reason, reported_symptoms (JSON)                        │  │
│ │ urgency (ROUTINE|URGENT|EMERGENCY)                      │  │
│ │ health_facility_id, health_facility_name/phone          │  │
│ │ status (PENDING|RECEIVED|ATTENDED|COMPLETED|CANCELLED)  │  │
│ │ diagnosis, treatment_prescribed (JSON)                  │  │
│ │ referred_at, attended_at, attended_by_health_provider   │  │
│ │ external_referral_id                                    │  │
│ └──────────────────────────────────────────────────────────┘  │
│         │                                                       │
│         │ 1:N                                                   │
│         │                                                       │
│         ▼                                                       │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ alerts (Alertas Automáticas)                            │  │
│ ├──────────────────────────────────────────────────────────┤  │
│ │ id, beneficiary_id (FK nullable)                        │  │
│ │ alert_type                                              │  │
│ │ title, description, severity                           │  │
│ │ recipients_roles (JSON)                                 │  │
│ │ status (ACTIVE|ACKNOWLEDGED|RESOLVED)                   │  │
│ │ created_at, acknowledged_at, resolved_at                │  │
│ │ resolution_notes                                        │  │
│ └──────────────────────────────────────────────────────────┘  │
│         │                                                       │
│         │ 1:1                                                   │
│         │                                                       │
│         ▼                                                       │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ statistics_cache (Caché de Performance)                 │  │
│ ├──────────────────────────────────────────────────────────┤  │
│ │ id, group_type (GLOBAL|FAMILY|WAREHOUSE)                │  │
│ │ total_beneficiaries, critical_count, priority_count     │  │
│ │ age_distribution (JSON), demographics                   │  │
│ │ top_recommendations (JSON), stock_gaps (JSON)           │  │
│ │ calculated_at, expires_at                               │  │
│ └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│ [RELACIONES CON OTROS MÓDULOS]                                │
│                                                                 │
│ master_items (FK) ← Módulo 4                                   │
│ stock_entries (FK) ← Módulo 3                                  │
│ stock_exits (FK) ← Módulo 6                                    │
│ users (FK) ← Módulo 2                                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

---

## 3. FLUJO DE DATOS: Request → Response

```
CLIENT REQUEST
     │
     │ POST /api/beneficiaries
     │ {name, age, conditions, ...}
     │
     ▼
┌─────────────────────────────────────────────┐
│ LARAVEL ROUTING                              │
│ ├─ Route::post('/beneficiaries', [...])      │
│ └─ BeneficiaryController@store()             │
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ MIDDLEWARE PIPELINE                          │
│ ├─ CORS (api/cors)                           │
│ ├─ Rate Limiter (throttle:api)              │
│ ├─ Auth (auth:sanctum)                      │
│ ├─ Verify tenant (tenancy/verify)           │
│ └─ Request validation ready                 │
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ FORM REQUEST VALIDATION                     │
│ ├─ App\Http\Requests\StoreBeneficiary       │
│ ├─ $validated = $request->validated()        │
│ └─ Throws ValidationException if errors      │
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ CONTROLLER ACTION                            │
│ ├─ public function store(StoreBeneficiary) │
│ │                                            │
│ │  1. Create beneficiary record              │
│ │     $beneficiary = Beneficiary::create()   │
│ │                                            │
│ │  2. Call ScoringEngine (sync)              │
│ │     $scoring = ScoringEngine->calculate()  │
│ │                                            │
│ │  3. Update with score                      │
│ │     $beneficiary->update($scoring)         │
│ │                                            │
│ └─ Return response                          │
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ MODEL EVENTS (Automatic)                     │
│ ├─ BeneficiaryUpdated event fires            │
│ ├─ Listeners subscribed:                     │
│ │  ├─ UpdateRecommendations (async)          │
│ │  ├─ InvalidateCache (Redis)                │
│ │  ├─ DispatchAlerts (if critical)           │
│ │  └─ LogAudit (LSPP)                        │
│ │                                            │
│ │ [Estos pueden ser sync o async]            │
│ │  Via: ShouldQueue, afterCommit(), etc      │
│ └─                                          │
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ BACKGROUND JOBS (Async - Redis Queue)       │
│ ├─ UpdateRecommendationsJob                 │
│ │  └─ RecommendationService->generate()     │
│ │     (enqueue si usar jobs)                 │
│ │                                            │
│ ├─ SendAlertNotificationsJob                │
│ │  └─ AlertService->sendNotifications()     │
│ │                                            │
│ ├─ InvalidateCacheJob                       │
│ │  └─ Redis::del(...) keys                  │
│ │                                            │
│ └─ AuditLogJob (LSPP)                       │
│    └─ AuditLog::create(...)                 │
│                                              │
│ [Si usar sync: ocurren inmediatamente]      │
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ DATA PERSISTENCE                             │
│ ├─ MySQL: INSERT beneficiaries              │
│ ├─ MySQL: INSERT vulnerability_scores       │
│ ├─ MySQL: INSERT beneficiary_recommendations│
│ ├─ MySQL: INSERT alerts                     │
│ ├─ MySQL: INSERT audit_logs (LSPP)          │
│ └─ Redis: Invalidate cache keys             │
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ EXTERNAL NOTIFICATIONS (Async Queue)        │
│ ├─ SendPushNotificationJob                  │
│ │  └─ Firebase Cloud Messaging               │
│ │                                            │
│ ├─ SendSmsNotificationJob                   │
│ │  └─ Twilio API                            │
│ │                                            │
│ ├─ SendEmailNotificationJob                 │
│ │  └─ SMTP / Mailgun / SendGrid              │
│ │                                            │
│ └─ SendWhatsAppNotificationJob               │
│    └─ Meta API                               │
│                                              │
│ [Estos pueden fallar y retry automático]    │
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ BROADCAST UPDATES (WebSocket)                │
│ ├─ Event: BeneficiaryCreated                │
│ │  └─ Broadcast to "dashboard.coordinators" │
│ │                                            │
│ ├─ Event: ScoreUpdated                      │
│ │  └─ Broadcast to "alerts.all"             │
│ │                                            │
│ └─ Clients receive real-time (if connected)│
└────┬────────────────────────────────────────┘
     │
     ▼
┌─────────────────────────────────────────────┐
│ API RESPONSE (to Client)                     │
│ {                                            │
│   "status": "success",                      │
│   "data": {                                 │
│     "beneficiary": {...},                  │
│     "score": {                             │
│       "total": 77,                         │
│       "level": "CRITICAL",                 │
│       "factors": [...]                     │
│     },                                      │
│     "recommendations": [...]                │
│   },                                        │
│   "message": "Beneficiario creado"         │
│ }                                           │
│                                             │
│ HTTP Status: 201 Created                    │
└────┬────────────────────────────────────────┘
     │
     ▼
CLIENT (Browser/App)
     │
     ├─ Update UI with response data
     ├─ Show notification to user
     ├─ Sync IndexedDB locally
     └─ Subscribe to WebSocket updates


SIMULTANEIDAD Y TIMING:
─────────────────────
Request arrival  ──T0──
Controller process ──T0+50ms──
Events fire     ──T0+60ms──
│
├─ Sync tasks (< 100ms total):
│  ├─ Model operations
│  ├─ Cache invalidation
│  └─ Response generation
│
└─ Async tasks (background):
   ├─ Notifications (SMS, Email, Push)
   ├─ External API calls
   ├─ Audit logging
   └─ Analytics
   
Response sent ──T0+150ms──
Client receives ──T0+160ms──

Notifications continue in background...
(retry if failed)
```

---

---

## 4. INTEGRACIÓN ENTRE MÓDULOS (1, 3, 6, 7)

```
┌─────────────────────────────────────────────────────────────────┐
│                    MÓDULO 1 ↔ MÓDULO 7                          │
│                   (Búsqueda Pública)                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ENTRADA:  Ciudadano busca "Leche"                              │
│           (Desde PublicSearchController)                        │
│                                                                 │
│ PROCESO:                                                        │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │ 1. Buscar item en master_items                           │   │
│ │    SELECT * FROM master_items                            │   │
│ │    WHERE name LIKE '%Leche%'                             │   │
│ │                                                          │   │
│ │ 2. Obtener stock total (M3)                              │   │
│ │    SELECT SUM(quantity) FROM stock_entries               │   │
│ │    WHERE item_id = 1, status = 'available'               │   │
│ │                                                          │   │
│ │ 3. **NUEVA**: Obtener demanda agregada (M7)             │   │
│ │    SELECT COUNT(*) FROM beneficiary_recommendations     │   │
│ │    WHERE item_id = 1, status = 'PENDING'                │   │
│ │    GROUP BY beneficiary.age_category                    │   │
│ │                                                          │   │
│ │ 4. Calcular brecha de stock                             │   │
│ │    gap = demand - stock                                 │   │
│ │    gap_percentage = (gap / demand) * 100                │   │
│ │                                                          │   │
│ │ 5. Determinar urgencia                                  │   │
│ │    IF gap > 50% → 🔴 CRÍTICO                           │   │
│ │    IF gap 20-50% → 🟡 PRIORITARIO                      │   │
│ │    IF gap < 20% → 🟢 NORMAL                            │   │
│ │                                                          │   │
│ └──────────────────────────────────────────────────────────┘   │
│                                                                 │
│ SALIDA: Resultado enriquecido                                  │
│ {                                                              │
│   "item": "Leche en Polvo",                                   │
│   "stock": 19,                                                │
│   "beneficiaries_need": 67,  ← **NEW (M7)**                   │
│   "gap": -48,                ← **NEW (M7)**                   │
│   "urgency": "CRITICAL",     ← **NEW (M7)**                   │
│   "beneficiary_types": [     ← **NEW (M7)**                   │
│     "Menores < 5: 45",                                        │
│     "Embarazadas: 8",                                         │
│     "Adultos: 14"                                             │
│   ],                                                           │
│   "locations": [                                              │
│     {warehouse, qty, distance}  ← (M3)                       │
│   ]                                                            │
│ }                                                              │
│                                                                 │
│ PRIVACY (LSPP):  ✓ NO NAMES, NO ADDRESSES                    │
│                  ✓ Solo agregados anónimos                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                    MÓDULO 3 ↔ MÓDULO 7                          │
│                  (Stock vs Demanda)                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ TRIGGER: StockEntry.quantity actualizado (M3)                 │
│                                                                 │
│ LISTENER: StockShortageDetector (M7)                           │
│                                                                 │
│ LÓGICA:                                                         │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │ protected static function booted()                       │   │
│ │ {                                                        │   │
│ │   static::updated(function ($entry) {                   │   │
│ │                                                          │   │
│ │     $demand = BeneficiaryRecommendation::where(         │   │
│ │       'item_id', $entry->master_item_id                 │   │
│ │     )->where('status', 'PENDING')->count();             │   │
│ │                                                          │   │
│ │     $stock = StockEntry::where(                         │   │
│ │       'master_item_id', $entry->master_item_id          │   │
│ │     )->sum('quantity');                                  │   │
│ │                                                          │   │
│ │     if ($stock < $demand) {                             │   │
│ │       AlertService::dispatch(                           │   │
│ │         'STOCK_SHORTAGE',                               │   │
│ │         $entry->masterItem,                             │   │
│ │         ['demand' => $demand, 'stock' => $stock]         │   │
│ │       );                                                 │   │
│ │     }                                                    │   │
│ │                                                          │   │
│ │     Cache::forget('stats:global:v2');  // Invalidate   │   │
│ │   });                                                    │   │
│ │ }                                                        │   │
│ └──────────────────────────────────────────────────────────┘   │
│                                                                 │
│ RESULTADO:                                                      │
│ ├─ Alert creada en DB                                         │
│ ├─ Notificaciones enviadas:                                   │
│ │  ├─ SMS Coordinador: "🔴 Leche -82% brecha"               │
│ │  ├─ Email Donantes: "URGENCIA: Se necesita leche"        │
│ │  └─ Dashboard: Actualiza stock gaps en vivo               │
│ │                                                             │
│ └─ Redis cache invalidado → stats refrescadas                │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                    MÓDULO 6 ↔ MÓDULO 7                          │
│                  (Entregas y Recomendaciones)                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ TRIGGER: StockExit creada (M6 - Operador entrega)             │
│                                                                 │
│ LISTENER: UpdateRecommendationsService (M7)                    │
│                                                                 │
│ FLUJO:                                                          │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │ StockExitController::store()                             │   │
│ │ {                                                        │   │
│ │   // 1. Crear stock exit                                │   │
│ │   $exit = StockExit::create($validated);                │   │
│ │                                                          │   │
│ │   // 2. NUEVA: Crear care_history                       │   │
│ │   CareHistory::create([                                 │   │
│ │     'beneficiary_id' => $request->beneficiary_id,       │   │
│ │     'items_delivered' => $request->items,               │   │
│ │     'delivery_date' => now()                            │   │
│ │   ]);                                                    │   │
│ │                                                          │   │
│ │   // 3. NUEVA: Actualizar recomendaciones a FULFILLED  │   │
│ │   foreach ($request->items as $item) {                 │   │
│ │     BeneficiaryRecommendation::where([                 │   │
│ │       'beneficiary_id' => $request->beneficiary_id,    │   │
│ │       'item_id' => $item['id'],                        │   │
│ │       'status' => 'PENDING'                            │   │
│ │     ])->update([                                        │   │
│ │       'status' => 'FULFILLED',                          │   │
│ │       'fulfilled_at' => now()                           │   │
│ │     ]);                                                  │   │
│ │   }                                                      │   │
│ │                                                          │   │
│ │   // 4. NUEVA: Alerta a donante (impacto)              │   │
│ │   AlertService::dispatch(                              │   │
│ │     'RECOMMENDATION_FULFILLED',                         │   │
│ │     $beneficiary                                        │   │
│ │   );                                                    │   │
│ │                                                          │   │
│ │   return response()->json(['status' => 'ok']);          │   │
│ │ }                                                        │   │
│ └──────────────────────────────────────────────────────────┘   │
│                                                                 │
│ RESULTADO:                                                      │
│ ├─ StockExit guardado (auditoría M6)                           │
│ ├─ CareHistory creada (historial M7)                           │
│ ├─ Recomendaciones marcadas FULFILLED (M7)                     │
│ ├─ Donante notificado: "Tu donación llegó a María"             │
│ └─ Dashboard actualizado (Coordinador ve progreso)             │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

---

## 5. COMPONENTES FRONTEND POR ROL

```
┌─────────────────────────────────────────────────────────────────┐
│                  COMPONENTES REACT/VUE                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 🏪 MÓDULO 1: BÚSQUEDA PÚBLICA                                  │
│                                                                 │
│  <PublicSearch>                                                │
│  ├─ <SearchBar>                                               │
│  │  └─ [input] Buscar item                                    │
│  │  └─ autocomplete de items                                  │
│  │                                                             │
│  ├─ <FilterPanel>                                             │
│  │  ├─ Zona geográfica (dropdown)                             │
│  │  ├─ Categoría (checkboxes)                                │
│  │  └─ Disponibilidad (radio)                                │
│  │                                                             │
│  ├─ <ResultsList>                                             │
│  │  └─ .map(item =>                                           │
│  │     <ItemCard>                                            │
│  │     ├─ Nombre + Stock                                     │
│  │     ├─ 🟢🟡🔴 Semáforo                                     │
│  │     ├─ **NEW**: Demanda (M7)                              │
│  │     ├─ **NEW**: Urgencia (M7)                             │
│  │     ├─ Ubicación (Mapa Leaflet)                           │
│  │     └─ [💬 Contactar]                                    │
│  │  )                                                         │
│  │                                                             │
│  └─ <TurnstileModal>                                          │
│     └─ Antibot check → WhatsApp deep link                     │
│                                                                 │
│ ─────────────────────────────────────────────────────────────  │
│                                                                 │
│ 📋 MÓDULO 7: OPERADOR (Mobile-First PWA)                       │
│                                                                 │
│  <DashboardOperador>                                          │
│  ├─ <CriticalsList>                                           │
│  │  └─ .map(beneficiary =>                                   │
│  │     <BeneficiaryCard>                                    │
│  │     ├─ Foto (avatar)                                     │
│  │     ├─ Nombre + Score badge (🔴/🟡/🟢)                   │
│  │     ├─ Priority alert                                    │
│  │     ├─ [📋 Ver Perfil]                                  │
│  │     └─ [✅ Marcar Visitado]                             │
│  │  )                                                         │
│  │                                                             │
│  ├─ <BeneficiaryFicha>                                        │
│  │  ├─ Datos personales (ro)                                │
│  │  ├─ <VulnerabilityScore>                                 │
│  │  │  └─ Score 77/100, factors, histórico                 │
│  │  ├─ <RecommendationsList>                                │
│  │  │  └─ .map(rec =>                                       │
│  │  │     Item + Stock disponible + Bodegas                │
│  │  │  )                                                     │
│  │  ├─ <CareHistoryTimeline>                                │
│  │  │  └─ .map(entry => delivery date + items)             │
│  │  │                                                         │
│  │  └─ [✅ Confirmar Entregas]                             │
│  │     └─ <DeliveryModal>                                   │
│  │        ├─ Checkboxes items                               │
│  │        ├─ [📷 Foto] camera                               │
│  │        └─ [Guardar] → IndexedDB                          │
│  │                                                             │
│  └─ <ProgressBar>                                             │
│     ├─ Familias visitadas: 5/12                              │
│     ├─ Entregas confirmadas: 8                               │
│     └─ Fotos: 8/8                                            │
│                                                                 │
│ ─────────────────────────────────────────────────────────────  │
│                                                                 │
│ 📊 MÓDULO 7: COORDINADOR (Dashboard Exec)                      │
│                                                                 │
│  <DashboardCoordinator>                                       │
│  ├─ <KPICards>                                                │
│  │  ├─ Total beneficiarios                                   │
│  │  ├─ Critical count                                        │
│  │  └─ Promedio score                                        │
│  │                                                             │
│  ├─ <VulnerabilityChart>                                      │
│  │  └─ Pie/Donut: 🔴/🟡/🟢 distribution                     │
│  │                                                             │
│  ├─ <StockGapsTable>                                          │
│  │  └─ Item, Demand, Stock, Gap%, [Alert Donante]           │
│  │                                                             │
│  ├─ <OperatorMap>                                             │
│  │  └─ Leaflet map con operators activos                    │
│  │                                                             │
│  ├─ <AlertCenter>                                             │
│  │  └─ Alertas activas, acknowledge, resolver               │
│  │                                                             │
│  └─ [📤 Reportes] [🔔 Alertar Alcalde]                      │
│                                                                 │
│ ─────────────────────────────────────────────────────────────  │
│                                                                 │
│ 🩺 MÓDULO 7: MÉDICO (Validación)                               │
│                                                                 │
│  <DashboardDoctor>                                            │
│  ├─ <ReferralsList>                                           │
│  │  └─ .map(referral =>                                      │
│  │     <ReferralCard>                                       │
│  │     ├─ Beneficiary name + symptoms                       │
│  │     └─ [✅ Validar] [Diagnosticar]                      │
│  │  )                                                         │
│  │                                                             │
│  ├─ <ProtocolManager>                                         │
│  │  ├─ Ver protocolos activos                               │
│  │  └─ [➕ Crear protocolo]                                │
│  │                                                             │
│  └─ <RecommendationValidator>                                │
│     └─ Revisar recommendations pending approval              │
│                                                                 │
│ ─────────────────────────────────────────────────────────────  │
│                                                                 │
│ ❤️ MÓDULO 7: DONANTE (Impacto)                                │
│                                                                 │
│  <DashboardDonor>                                             │
│  ├─ <ImpactSummary>                                           │
│  │  ├─ Donaciones realizadas                                │
│  │  ├─ Familias alcanzadas                                  │
│  │  ├─ Personas beneficiadas                                │
│  │  └─ Valor estimado                                       │
│  │                                                             │
│  ├─ <ImpactBreakdown>                                         │
│  │  └─ .map(donation =>                                     │
│  │     <DonationDetail>                                    │
│  │     ├─ Item + qty donated                                │
│  │     ├─ Qty delivered                                     │
│  │     ├─ Beneficiaries: [Maria, Sofia, ...]                │
│  │     └─ Impact message                                    │
│  │  )                                                         │
│  │                                                             │
│  └─ [❤️ Donar Más] [📊 Ver Reportes]                        │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

---

## 6. PIPELINE CI/CD

> ⚠️ **Nota de vigencia (18-ago-2026):** el diagrama de Git Flow de abajo (`develop` → `staging` → `release/vX.Y.Z` → `main`) quedó **superado**. El flujo vigente es más simple: `feature/xxx` → PR contra `test` → CI/CD al ambiente de pruebas en EC2 → aprobado → merge a `main`. Sin rama `develop` ni tags `release/*`. Ver "Flujo de ramas y despliegue" en `CLAUDE.md` y la sección "Ambiente de pruebas en la instancia EC2" en `README.md` para el detalle vigente. El resto de este diagrama (tests automatizados, GitHub Actions) sigue siendo la referencia de diseño, salvo que `.github/workflows/deploy.yml` todavía no existe — solo `tests.yml`.

```
┌───────────────────────────────────────────────────────────────┐
│                   GIT FLOW & VERSIONING (superado, ver nota arriba) │
├───────────────────────────────────────────────────────────────┤
│                                                               │
│ Feature Branch                                                │
│   │                                                            │
│   ├─ feat/module-7-scoring  ← Developer trabajo              │
│   │  ├─ Commit: "feat(scoring): implement ponderado"        │
│   │  ├─ Commit: "test(scoring): add unit tests"            │
│   │  ├─ Commit: "docs(scoring): update README"             │
│   │  └─ Push to origin                                       │
│   │                                                            │
│   ▼                                                            │
│  Pull Request                                                 │
│   │                                                            │
│   ├─ Code review (Tech Lead)                                 │
│   ├─ Automated checks (GitHub Actions)                       │
│   └─ Approve & Merge to develop                              │
│       │                                                        │
│       ▼                                                        │
│      develop branch                                           │
│       │                                                        │
│       ├─ Integration tests run                                │
│       ├─ Code quality checks                                  │
│       └─ Deploy to staging                                    │
│           │                                                    │
│           ▼                                                    │
│        STAGING environment                                    │
│           │                                                    │
│           ├─ QA testing (2 days)                             │
│           ├─ Performance testing                              │
│           ├─ Security scan                                    │
│           └─ Smoke tests                                      │
│               │                                                │
│               ├─ ✓ PASS: Create release tag                   │
│               └─ ✗ FAIL: Return to develop                    │
│                   │                                            │
│                   ▼                                            │
│               release/v2.1.0                                  │
│                   │                                            │
│                   ├─ Bump version (package.json, .env)        │
│                   ├─ Update CHANGELOG                          │
│                   ├─ Create tag: v2.1.0                       │
│                   └─ Merge to main                            │
│                       │                                        │
│                       ▼                                        │
│                    main branch                                │
│                       │                                        │
│                       ├─ Deploy to PRODUCTION                 │
│                       ├─ Update CDN                           │
│                       └─ Monitor (30 min)                     │
│                                                               │
│ ─────────────────────────────────────────────────────────────│
│                    AUTOMATED TESTS                            │
│                                                               │
│ UNIT TESTS (phpunit)                                         │
│ ├─ Tests/Unit/ScoringEngineTest.php                          │
│ │  ├─ testCalculateDemographicScore()                        │
│ │  ├─ testCalculateTotalScore()                              │
│ │  └─ testDeterminePriority()                                │
│ │                                                             │
│ ├─ Tests/Unit/RecommendationServiceTest.php                  │
│ │  ├─ testFindApplicableProtocols()                          │
│ │  ├─ testCheckAvailability()                                │
│ │  └─ testGenerateRecommendations()                          │
│ │                                                             │
│ └─ Coverage: 80%+ (goal)                                     │
│                                                               │
│ INTEGRATION TESTS (phpunit)                                  │
│ ├─ Tests/Integration/BeneficiaryFlowTest.php                 │
│ │  ├─ testCreateBeneficiaryTriggersScoring()                │
│ │  ├─ testScoringDispatchesAlert()                           │
│ │  └─ testRecommendationsCreated()                           │
│ │                                                             │
│ ├─ Tests/Integration/StockGapsTest.php                       │
│ │  ├─ testStockShortageDetected()                            │
│ │  └─ testAlertDispatchedToCoordinator()                     │
│ │                                                             │
│ └─ Coverage: 60%+ (goal)                                     │
│                                                               │
│ E2E TESTS (Cypress/Playwright)                               │
│ ├─ e2e/operator.spec.ts                                      │
│ │  ├─ "Operador abre ficha y confirma entrega"             │
│ │  └─ "Sistema marca recomendación como fulfilled"          │
│ │                                                             │
│ ├─ e2e/coordinator.spec.ts                                   │
│ │  ├─ "Coordinador ve dashboard actualizado"               │
│ │  └─ "Click en [Alertar Donantes] envía email"            │
│ │                                                             │
│ └─ Coverage: Key flows (goal)                                │
│                                                               │
│ ─────────────────────────────────────────────────────────────│
│                   GITHUB ACTIONS                              │
│                                                               │
│ .github/workflows/tests.yml                                  │
│ ├─ Trigger: on push to any branch                            │
│ ├─ Jobs:                                                      │
│ │  ├─ lint (Code style)                                      │
│ │  │  └─ ./vendor/bin/pint --test                           │
│ │  │                                                          │
│ │  ├─ test (Unit + Integration)                              │
│ │  │  └─ php artisan test --coverage                        │
│ │  │                                                          │
│ │  ├─ security (SAST)                                        │
│ │  │  └─ phpstan analyse app/                               │
│ │  │                                                          │
│ │  └─ coverage (Report to Codecov)                           │
│ │     └─ Upload coverage badge                               │
│ │                                                             │
│ .github/workflows/deploy.yml                                 │
│ ├─ Trigger: on merge to main                                 │
│ ├─ Jobs:                                                      │
│ │  ├─ build (Docker image)                                   │
│ │  │  └─ docker build -t donaciones-rolda:v2.1.0            │
│ │  │                                                          │
│ │  ├─ push (Push to ECR)                                     │
│ │  │  └─ aws ecr push ...                                   │
│ │  │                                                          │
│ │  ├─ deploy (ECS update)                                    │
│ │  │  └─ aws ecs update-service ...                         │
│ │  │                                                          │
│ │  ├─ smoke-test                                             │
│ │  │  └─ curl https://api.donaciones.../health              │
│ │  │                                                          │
│ │  └─ monitor (30 min)                                       │
│ │     └─ Check CloudWatch metrics                            │
│ │                                                             │
│ ─────────────────────────────────────────────────────────────│
│                    NOTIFICATIONS                              │
│                                                               │
│ Success:                                                      │
│ ├─ Slack: #deployments "#v2.1.0 deployed to prod ✅"       │
│ └─ Email: tech-leads@... "Deploy successful"                 │
│                                                               │
│ Failure:                                                      │
│ ├─ Slack: #deployments "#v2.1.0 FAILED ❌"                 │
│ ├─ Email: devops@... "Deploy failed: [reason]"              │
│ └─ Rollback: Automático si health checks fallan              │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

---

---

## 7. DEPLOYMENT ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────┐
│                        AWS DEPLOYMENT                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ INTERNET                                                        │
│      │                                                           │
│      ▼                                                           │
│ ┌─────────────────────────────────────────┐                    │
│ │  Cloudflare CDN (DDoS Protection)       │                    │
│ │  ├─ Cache static assets                 │                    │
│ │  ├─ Global edge network                 │                    │
│ │  └─ TLS 1.3 termination                 │                    │
│ └────────┬────────────────────────────────┘                    │
│          │                                                      │
│          ▼                                                      │
│ ┌─────────────────────────────────────────────────────────┐   │
│ │  AWS VPC (172.16.0.0/16)                               │   │
│ │                                                         │   │
│ │  ┌───────────────────────────────────────────────────┐ │   │
│ │  │ PUBLIC SUBNET (Availability Zone A)               │ │   │
│ │  │                                                   │ │   │
│ │  │  ┌────────────────────────────────────────────┐  │ │   │
│ │  │  │  ALB (Application Load Balancer)           │  │ │   │
│ │  │  │  ├─ HTTPS listener (port 443)              │  │ │   │
│ │  │  │  ├─ Health check: /api/health              │  │ │   │
│ │  │  │  └─ Target group: ECS tasks                │  │ │   │
│ │  │  └────────────────────────────────────────────┘  │ │   │
│ │  │                                                   │ │   │
│ │  └─────────────────────────────────────────────────┘ │   │
│ │                                                       │   │
│ │  ┌───────────────────────────────────────────────────┐ │   │
│ │  │ PRIVATE SUBNET (Application Tier - AZ A)         │ │   │
│ │  │                                                   │ │   │
│ │  │  ECS Fargate Cluster                             │ │   │
│ │  │  ├─ Task 1: Laravel API (cpu: 256, mem: 512)    │ │   │
│ │  │  ├─ Task 2: Laravel API (redundancy)            │ │   │
│ │  │  ├─ Task 3: Queue Worker (jobs)                 │ │   │
│ │  │  └─ ASG: min 2, max 4 (auto-scale on CPU)      │ │   │
│ │  │                                                   │ │   │
│ │  └───────────────────────────────────────────────────┘ │   │
│ │                                                         │   │
│ │  ┌───────────────────────────────────────────────────┐ │   │
│ │  │ PRIVATE SUBNET (Database Tier - AZ A)            │ │   │
│ │  │                                                   │ │   │
│ │  │  RDS Aurora MySQL                                │ │   │
│ │  │  ├─ Multi-AZ (failover automático)               │ │   │
│ │  │  ├─ db.t3.small primary                          │ │   │
│ │  │  ├─ Read replica (AZ B)                          │ │   │
│ │  │  ├─ Automated backups (7 days)                   │ │   │
│ │  │  └─ Enhanced monitoring                          │ │   │
│ │  │                                                   │ │   │
│ │  │  ElastiCache Redis                               │ │   │
│ │  │  ├─ cache.t3.micro                               │ │   │
│ │  │  ├─ Multi-AZ (failover)                          │ │   │
│ │  │  ├─ Encryption at transit (TLS)                  │ │   │
│ │  │  └─ Automatic snapshots                          │ │   │
│ │  │                                                   │ │   │
│ │  └───────────────────────────────────────────────────┘ │   │
│ │                                                         │   │
│ │  ┌───────────────────────────────────────────────────┐ │   │
│ │  │ PRIVATE SUBNET (AZ B - Disaster Recovery)        │ │   │
│ │  │                                                   │ │   │
│ │  │  ├─ Database read replica (Aurora)               │ │   │
│ │  │  ├─ Redis replica (ElastiCache)                  │ │   │
│ │  │  └─ Standby ECS (if failover)                    │ │   │
│ │  │                                                   │ │   │
│ │  └───────────────────────────────────────────────────┘ │   │
│ │                                                         │   │
│ └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ STORAGE & LOGGING                                        │  │
│ │                                                           │  │
│ │  S3 Buckets                                              │  │
│ │  ├─ donaciones-rolda-photos                              │  │
│ │  │  └─ Care history photos (encrypted)                  │  │
│ │  ├─ donaciones-rolda-backups                             │  │
│ │  │  └─ Daily DB backups (lifecycle: 30 days)            │  │
│ │  └─ donaciones-rolda-logs                                │  │
│ │     └─ CloudFront logs, ECS logs (lifecycle)            │  │
│ │                                                           │  │
│ │  CloudWatch Logs                                          │  │
│ │  ├─ /aws/ecs/donaciones-rolda                            │  │
│ │  ├─ /aws/rds/donaciones-rolda                            │  │
│ │  ├─ /aws/elasticache/redis                              │  │
│ │  └─ Log retention: 30 days                               │  │
│ │                                                           │  │
│ │  CloudWatch Alarms                                        │  │
│ │  ├─ CPU > 75% (ECS)                                      │  │
│ │  ├─ Memory > 80% (ECS)                                   │  │
│ │  ├─ Database connections > 80                            │  │
│ │  ├─ Redis evictions > 0                                  │  │
│ │  ├─ ALB unhealthy targets                                │  │
│ │  └─ Error rate > 1%                                      │  │
│ │                                                           │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ MONITORING & OBSERVABILITY                               │  │
│ │                                                           │  │
│ │  Datadog (Optional)                                       │  │
│ │  ├─ APM: Detect slow endpoints                           │  │
│ │  ├─ RUM: Frontend performance                            │  │
│ │  ├─ Infrastructure monitoring                             │  │
│ │  └─ Custom metrics (score calculations, etc)             │  │
│ │                                                           │  │
│ │  X-Ray (AWS)                                              │  │
│ │  ├─ Trace requests end-to-end                            │  │
│ │  ├─ Identify bottlenecks                                 │  │
│ │  └─ Service map visualization                            │  │
│ │                                                           │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ SECURITY GROUPS & NETWORK                                │  │
│ │                                                           │  │
│ │  Security Group: ALB                                      │  │
│ │  ├─ Inbound: 443 (HTTPS) from 0.0.0.0/0                 │  │
│ │  └─ Outbound: All traffic (for external APIs)            │  │
│ │                                                           │  │
│ │  Security Group: ECS                                      │  │
│ │  ├─ Inbound: 8000 from ALB                               │  │
│ │  └─ Outbound: All (to RDS, Redis, Internet)              │  │
│ │                                                           │  │
│ │  Security Group: RDS                                      │  │
│ │  ├─ Inbound: 3306 from ECS security group                │  │
│ │  └─ Outbound: None (read-only DB)                        │  │
│ │                                                           │  │
│ │  Security Group: Redis                                    │  │
│ │  ├─ Inbound: 6379 from ECS security group                │  │
│ │  └─ Outbound: None                                       │  │
│ │                                                           │  │
│ └───────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

---

## 8. SECURITY & PRIVACY LAYERS

```
┌─────────────────────────────────────────────────────────────────┐
│                    SECURITY LAYERS                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ LAYER 1: PERIMETER (Network)                                   │
│ ├─ Cloudflare DDoS protection                                  │
│ ├─ WAF (Web Application Firewall)                              │
│ │  ├─ Rate limiting                                            │
│ │  ├─ Geo-blocking (if needed)                                │
│ │  └─ OWASP Top 10 protection                                 │
│ ├─ AWS Shield Standard (DDoS mitigation)                       │
│ └─ VPC endpoints (private connectivity to AWS services)        │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────┐   │
│ │ LAYER 2: API SECURITY                                   │   │
│ │ ├─ HTTPS/TLS 1.3 mandatory                              │   │
│ │ ├─ JWT tokens (Laravel Sanctum)                         │   │
│ │ │  ├─ Token expiry: 24 hours                            │   │
│ │ │  ├─ Refresh token mechanism                           │   │
│ │ │  └─ Revocation on logout                              │   │
│ │ │                                                        │   │
│ │ ├─ CORS (Cross-Origin Resource Sharing)                 │   │
│ │ │  └─ Whitelist specific domains                        │   │
│ │ │                                                        │   │
│ │ ├─ Rate limiting (Redis-backed)                         │   │
│ │ │  ├─ 100 requests/minute per IP                        │   │
│ │ │  ├─ 10 login attempts / 15 minutes (brute force)      │   │
│ │ │  └─ Exponential backoff on failure                    │   │
│ │ │                                                        │   │
│ │ ├─ CSRF protection (SameSite cookies)                   │   │
│ │ │  └─ Laravel CSRF tokens for form submissions          │   │
│ │ │                                                        │   │
│ │ └─ Input validation & sanitization                      │   │
│ │    ├─ FormRequest validation rules                      │   │
│ │    ├─ Type casting (int, bool, string)                  │   │
│ │    └─ Escape output (XSS prevention)                    │   │
│ │                                                          │   │
│ └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────┐   │
│ │ LAYER 3: DATA ENCRYPTION                                │   │
│ │ ├─ At Rest (Database & Storage)                         │   │
│ │ │  ├─ RDS encryption (AWS KMS)                          │   │
│ │ │  ├─ EBS volumes (ECS hosts)                           │   │
│ │ │  ├─ S3 encryption (SSE-S3)                            │   │
│ │ │  └─ Sensitive fields hash (passwords, tokens)         │   │
│ │ │                                                        │   │
│ │ └─ In Transit                                           │   │
│ │    ├─ HTTPS/TLS 1.3 (internal & external)              │   │
│ │    ├─ RDS SSL connections                               │   │
│ │    ├─ Redis SSL (in-transit encryption)                 │   │
│ │    └─ SMTP TLS for emails                               │   │
│ │                                                          │   │
│ └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────┐   │
│ │ LAYER 4: AUTHENTICATION & AUTHORIZATION                 │   │
│ │ ├─ Laravel Sanctum (API auth)                           │   │
│ │ │  ├─ Personal access tokens                            │   │
│ │ │  └─ Session-based auth (web)                          │   │
│ │ │                                                        │   │
│ │ ├─ Role-Based Access Control (RBAC)                     │   │
│ │ │  ├─ Middleware: RoleMiddleware                        │   │
│ │ │  ├─ Gates: can('view_beneficiaries')                  │   │
│ │ │  └─ Policies: BeneficiaryPolicy                       │   │
│ │ │                                                        │   │
│ │ ├─ Multi-tenant isolation (Tenancy)                     │   │
│ │ │  ├─ Data scoped per tenant                            │   │
│ │ │  ├─ Migrations per tenant                             │   │
│ │ │  └─ Isolation verified in middleware                  │   │
│ │ │                                                        │   │
│ │ └─ Password policy                                      │   │
│ │    ├─ Minimum 12 characters                             │   │
│ │    ├─ Uppercase, lowercase, number, symbol              │   │
│ │    ├─ Bcrypt hashing (rounds: 12)                       │   │
│ │    └─ Password reuse prevention (last 5)                │   │
│ │                                                          │   │
│ └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────┐   │
│ │ LAYER 5: PRIVACY (LSPP - Ley 1581/2012)                │   │
│ │ ├─ Data Minimization                                    │   │
│ │ │  └─ Only collect necessary PII                        │   │
│ │ │                                                        │   │
│ │ ├─ Consent Management                                   │   │
│ │ │  ├─ Explicit opt-in (privacy_consent field)          │   │
│ │ │  ├─ Consent audit log                                 │   │
│ │ │  └─ Withdrawal mechanism                              │   │
│ │ │                                                        │   │
│ │ ├─ Purpose Limitation                                   │   │
│ │ │  ├─ Data used only for stated purposes                │   │
│ │ │  └─ No third-party sharing without consent            │   │
│ │ │                                                        │   │
│ │ ├─ Data Subject Rights (Articles 8, 9, 10 LSPP)        │   │
│ │ │  ├─ Right to access (GET /api/my-data)               │   │
│ │ │  ├─ Right to rectification (PATCH /api/my-data)      │   │
│ │ │  ├─ Right to erasure (DELETE /api/my-data)           │   │
│ │ │  ├─ Right to data portability (Export JSON)          │   │
│ │ │  └─ Right to complaint (form in app)                  │   │
│ │ │                                                        │   │
│ │ ├─ Data Retention Policy                                │   │
│ │ │  ├─ Active beneficiaries: retain indefinitely          │   │
│ │ │  ├─ Inactive (1 year): anonymize or delete            │   │
│ │ │  ├─ Audit logs: 2 years (legal requirement)           │   │
│ │ │  └─ Backups: 7 days (then purged)                     │   │
│ │ │                                                        │   │
│ │ └─ Breach Notification                                  │   │
│ │    ├─ Detect breach (intrusion detection)               │   │
│ │    ├─ Notify affected users < 72 hours                  │   │
│ │    ├─ Notify authorities (Superintendencia)             │   │
│ │    └─ Audit trail documented                            │   │
│ │                                                          │   │
│ └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────┐   │
│ │ LAYER 6: AUDITING & MONITORING                          │   │
│ │ ├─ Audit Logging (LSPP Compliance)                      │   │
│ │ │  ├─ Who: user_id logged                               │   │
│ │ │  ├─ What: action_type (CREATE, UPDATE, DELETE)        │   │
│ │ │  ├─ When: timestamp (UTC)                             │   │
│ │ │  ├─ Where: IP address, endpoint                       │   │
│ │ │  └─ Why: reason (for sensitive ops)                   │   │
│ │ │                                                        │   │
│ │ ├─ Change Tracking (Audit columns)                      │   │
│ │ │  ├─ created_at, created_by_user_id                    │   │
│ │ │  ├─ updated_at, updated_by_user_id                    │   │
│ │ │  └─ deleted_at (soft deletes, not hard)               │   │
│ │ │                                                        │   │
│ │ ├─ Sensitive Data Access Logging                        │   │
│ │ │  ├─ Every access to PII logged                        │   │
│ │ │  ├─ Anomaly detection (unusual access patterns)       │   │
│ │ │  └─ Alerts on unauthorized access                     │   │
│ │ │                                                        │   │
│ │ └─ Compliance Reporting                                 │   │
│ │    ├─ LSPP compliance report (quarterly)                │   │
│ │    ├─ Incident reports (if any)                         │   │
│ │    └─ Data processing agreements signed                 │   │
│ │                                                          │   │
│ └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│ ┌─────────────────────────────────────────────────────────┐   │
│ │ LAYER 7: SECRETS MANAGEMENT                             │   │
│ │ ├─ Environment Variables (.env)                         │   │
│ │ │  ├─ Never commit secrets to git                       │   │
│ │ │  ├─ Rotate every 90 days                              │   │
│ │ │  └─ Different secrets per environment (dev/prod)      │   │
│ │ │                                                        │   │
│ │ ├─ AWS Secrets Manager (for sensitive config)           │   │
│ │ │  ├─ Database credentials                              │   │
│ │ │  ├─ API keys (Twilio, Firebase, etc)                  │   │
│ │ │  ├─ Auto-rotation enabled                             │   │
│ │ │  └─ Audit trail of access                             │   │
│ │ │                                                        │   │
│ │ └─ KMS Key Management                                   │   │
│ │    ├─ RDS encryption keys                               │   │
│ │    ├─ S3 encryption keys                                │   │
│ │    └─ Cross-account access (if needed)                  │   │
│ │                                                          │   │
│ └─────────────────────────────────────────────────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

**FIN DE DIAGRAMAS DE ARQUITECTURA**

Todos los diagramas son complementarios a los flujos detallados en el documento anterior.

