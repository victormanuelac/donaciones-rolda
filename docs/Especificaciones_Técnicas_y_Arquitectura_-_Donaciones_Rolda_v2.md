# **📌 Documentación Técnica y Funcional: Donaciones Rolda**

**Sistema Integrado para la Gestión de Insumos, Estadísticas y Asistencia Humanitaria en Situaciones de Catástrofe**

**Versión:** 2.0  
**Última Actualización:** Agosto 2026  
**Estado:** 🟢 Módulo 7 Integrado (Beneficiarios + Estadísticas Inteligentes)

---

## **📋 Índice**

1. [Visión General y Filosofía de Desarrollo](#1-visión-general-y-filosofía-de-desarrollo)
2. [Arquitectura del Sistema y Stack Tecnológico](#2-arquitectura-del-sistema-y-stack-tecnológico)
3. [Modelo de Base de Datos Mixto (MySQL + Redis + IndexedDB)](#3-modelo-de-base-de-datos-mixto)
4. [Flujos Técnicos Clave](#4-flujos-técnicos-clave)
5. [Desglose Modular del Sistema (7 Módulos)](#5-desglose-modular-del-sistema)
6. [Modelado de Vistas y Pantallas](#6-modelado-de-vistas-y-pantallas)
7. [Planes de Integración Futura (Telegram / WhatsApp)](#7-planes-de-integración-futura)

---

## **1. Visión General y Filosofía de Desarrollo**

### **1.1 Objetivo del Proyecto**

**donaciones-rolda** es una plataforma liviana, de alta velocidad y resiliente a fallas de red, diseñada para:

1. **Rastrear y gestionar** disponibilidad de medicamentos, insumos médicos, alimentos y herramientas durante emergencias
2. **Analizar vulnerabilidad** de beneficiarios con scoring inteligente ponderado
3. **Recomendar recursos** basado en perfiles de familias y síntomas reportados
4. **Coordinar entregas** con trazabilidad completa y auditoría LSPP
5. **Informar donantes** sobre impacto real de contribuciones

### **1.2 Filosofía LaraMentor / Clean Laravel**

Para garantizar un código mantenible, libre de sobrecarga técnica (*bloatware*) y extremadamente rápido:

- **Slim Controllers, Single Responsibility Actions:** Lógica de negocio en clases Action dedicadas
- **FormRequests Dedicados:** Validación aislada antes de llegar al controlador
- **Enums Tipados (PHP 8.2+):** StockStatus, TrafficLightSeverity, UserRole, PriorityLevel, AlertType
- **Data Transfer Objects (DTOs):** Estructuración de datos entre API y sincronización offline
- **Sin Dependencias Innecesarias:** Alpine.js + Tailwind CSS + Blade/Livewire v3 (sin SPA pesados)

---

## **2. Arquitectura del Sistema y Stack Tecnológico**

```
┌─────────────────────────────────────────────────────────────────┐
│                    CLIENTE / PWA + MOBILE                       │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ Vista Pública (Búsqueda)  │  Operador PWA (Offline First) │   │
│  │ Tailwind + Alpine.js      │  IndexedDB + Service Worker   │   │
│  │ Searchable                │  Modo Online/Offline          │   │
│  └──────────────────────────────────────────────────────────┘   │
└──────────────────────────┬──────────────────────────────────────┘
                           │
         (HTTPS / REST API / JSON / WebSocket Real-time)
                           │
┌──────────────────────────▼──────────────────────────────────────┐
│                    LARAVEL 11 BACKEND                           │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ • Middleware Auth, LSPP Compliance, Turnstile Captcha    │   │
│  │ • FormRequests, Rate Limiters, Actions/Services          │   │
│  │ • Scoring Engine, Recommendation Service                 │   │
│  │ • Alert Service, Event Broadcasting                      │   │
│  └──────────────────────────────────────────────────────────┘   │
└────┬─────────────────────────┬────────────────────┬─────────────┘
     │                         │                    │
     ▼                         ▼                    ▼
┌──────────────┐   ┌──────────────────┐   ┌──────────────────┐
│  MySQL 8.0   │   │  Redis 7.0       │   │ Laravel Channels │
│  (ACID)      │   │  (Cache + Queue) │   │ (Real-time)      │
│ ┌──────────┐ │   │ ┌──────────────┐ │   │ ┌──────────────┐ │
│ │ Benefic. │ │   │ │ Stats cache  │ │   │ │ Alerts       │ │
│ │ Families │ │   │ │ Semáforo     │ │   │ │ Notifications│ │
│ │ Scores   │ │   │ │ Rate limit   │ │   │ │ Ready for:   │ │
│ │ Recom.   │ │   │ │ Sessions     │ │   │ │ - Telegram   │ │
│ │ Audit    │ │   │ │              │ │   │ │ - WhatsApp   │ │
│ └──────────┘ │   │ └──────────────┘ │   │ │ - SMS/Email  │ │
└──────────────┘   └──────────────────┘   │ └──────────────┘ │
                                          └──────────────────┘
```

### **Stack Detallado**

| Componente | Tecnología | Versión | Propósito |
|---|---|---|---|
| **Backend** | Laravel | 11/12 (PHP 8.3+) | API, Business Logic, Scoring |
| **Relacional** | MySQL/MariaDB | 8.0/10.11 | Datos persistentes + Auditoría |
| **Cache/Queue** | Redis | 7.0 | Cache stats, Scoring, Alerts queue |
| **Frontend** | Alpine.js + Blade | 3.x | Interactividad + server-rendering |
| **Styling** | Tailwind CSS | 3.x | Responsive design |
| **Offline** | PWA + IndexedDB | Dexie.js | Field operations sin conexión |
| **Security** | Cloudflare Turnstile | Latest | Antibot sin cookies intrusivas |
| **Maps** | Leaflet.js + OSM | Latest | Georreferenciación sin costos |
| **Real-time** | Laravel Reverb/Redis | 1.x | Alerts + Dashboard actualizaciones |

---

## **3. Modelo de Base de Datos Mixto**

### **3.1 Tablas Principales (MySQL)**

#### **A. Estructura Geográfica y Organizacional**

```sql
-- Zonas geográficas
CREATE TABLE geographic_zones (
    id BIGINT PRIMARY KEY, name VARCHAR(150),
    zone_type ENUM('comuna', 'corregimiento', 'vereda'),
    municipality VARCHAR(100) DEFAULT 'Roldanillo'
);

-- Organizaciones (ONG, municipalidad, etc)
CREATE TABLE organizations (
    id BIGINT PRIMARY KEY, name VARCHAR(150),
    org_type ENUM('socorro', 'lideres', 'alcaldia', 'ong'),
    is_active BOOLEAN DEFAULT TRUE
);

-- Usuarios con roles
CREATE TABLE users (
    id BIGINT PRIMARY KEY, name VARCHAR(150),
    role ENUM('admin', 'operator', 'coordinator', 'doctor', 'donor', 'municipal'),
    status ENUM('pending', 'active', 'rejected'),
    organization_id BIGINT FOREIGN KEY,
    phone VARCHAR(20), email VARCHAR(150)
);
```

#### **B. Inventario e Insumos (Módulos 1,3,4)**

```sql
CREATE TABLE master_items (
    id BIGINT PRIMARY KEY,
    name VARCHAR(150), category_id BIGINT,
    unit_of_measure VARCHAR(30),
    status ENUM('approved', 'under_review'),
    requires_cold_chain BOOLEAN DEFAULT FALSE
);

CREATE TABLE stock_entries (
    id BIGINT PRIMARY KEY,
    master_item_id BIGINT, warehouse_id BIGINT,
    quantity INT, batch_number VARCHAR(100),
    expiry_date DATE, status ENUM('pending_arrival', 'available', 'reserved'),
    created_at TIMESTAMP
);

CREATE TABLE stock_exits (
    id BIGINT PRIMARY KEY,
    warehouse_id BIGINT, item_id BIGINT, quantity INT,
    beneficiary_id BIGINT, release_date TIMESTAMP,
    released_by_user_id BIGINT, notes TEXT
);
```

#### **C. NUEVO: Módulo 7 — Beneficiarios + Estadísticas**

```sql
-- =============== BENEFICIARIOS ===============
CREATE TABLE beneficiaries (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    family_id BIGINT NOT NULL,
    first_name VARCHAR(100), last_name VARCHAR(100),
    date_of_birth DATE, gender ENUM('M', 'F', 'O'),
    
    -- Vulnerabilidad
    vulnerability_score DECIMAL(5,2) DEFAULT 0,
    priority_level ENUM('CRITICAL', 'PRIORITY', 'NORMAL'),
    last_score_update TIMESTAMP,
    
    -- Salud (autorreporte)
    chronic_conditions JSON, current_symptoms JSON,
    is_pregnant BOOLEAN, pregnancy_trimester INT,
    has_disability BOOLEAN, disability_type VARCHAR(100),
    medical_notes TEXT,
    
    -- Social
    is_single_parent BOOLEAN, has_no_home BOOLEAN,
    employment_status ENUM('EMPLOYED', 'UNEMPLOYED', 'STUDENT', 'RETIRED'),
    
    -- LSPP Privacy
    privacy_consent BOOLEAN DEFAULT TRUE,
    data_visibility ENUM('PRIVATE', 'FAMILY_ONLY', 'OPERATORS', 'ALL_AUTHORIZED'),
    
    -- Audit
    registered_by_user_id BIGINT,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX (family_id, priority_level),
    FOREIGN KEY (family_id) REFERENCES families(id)
);

-- =============== SCORING ===============
CREATE TABLE vulnerability_scores (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT NOT NULL,
    demographic_score INT, health_score INT,
    nutritional_score INT, social_score INT,
    total_score INT, priority_level ENUM('CRITICAL', 'PRIORITY', 'NORMAL'),
    contributing_factors JSON,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX (beneficiary_id, calculated_at),
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id)
);

-- =============== PROTOCOLOS DE RECOMENDACIÓN ===============
CREATE TABLE protocol_recommendations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    protocol_name VARCHAR(255) NOT NULL,
    source ENUM('WHO', 'ICBF', 'LOCAL_HEALTH', 'MUNICIPAL', 'DONOR'),
    trigger_condition JSON, -- {"age_min": 0, "age_max": 5, "chronic": [...]}
    recommended_items JSON, -- [{"item_id": 1, "quantity": 1, "frequency": "daily"}]
    confidence_level DECIMAL(3,2),
    requires_medical_approval BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    valid_from DATE, valid_until DATE,
    
    FULLTEXT INDEX (protocol_name)
);

-- =============== RECOMENDACIONES PERSONALIZADAS ===============
CREATE TABLE beneficiary_recommendations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT NOT NULL,
    protocol_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    quantity_recommended INT,
    frequency VARCHAR(50), duration_days INT,
    status ENUM('PENDING', 'IN_PROGRESS', 'FULFILLED', 'EXPIRED'),
    fulfillment_percentage INT DEFAULT 0,
    available_stock INT,
    available_warehouses JSON,
    recommended_at TIMESTAMP,
    fulfilled_at TIMESTAMP,
    
    INDEX (beneficiary_id, status),
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id),
    FOREIGN KEY (protocol_id) REFERENCES protocol_recommendations(id)
);

-- =============== HISTORIAL DE ATENCIONES ===============
CREATE TABLE care_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT NOT NULL,
    items_delivered JSON,
    delivered_by_user_id BIGINT,
    delivery_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_by_beneficiary BOOLEAN DEFAULT FALSE,
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date DATE,
    
    INDEX (beneficiary_id, delivery_date),
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id)
);

-- =============== DERIVACIONES A SALUD ===============
CREATE TABLE health_referrals (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT NOT NULL,
    referred_by_user_id BIGINT,
    reason TEXT, reported_symptoms JSON,
    urgency ENUM('ROUTINE', 'URGENT', 'EMERGENCY'),
    status ENUM('PENDING', 'RECEIVED', 'ATTENDED', 'COMPLETED'),
    attended_at TIMESTAMP,
    diagnosis TEXT, treatment_prescribed JSON,
    
    INDEX (beneficiary_id, status),
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id)
);

-- =============== ALERTAS AUTOMÁTICAS ===============
CREATE TABLE alerts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT,
    alert_type ENUM(
        'CRITICAL_SCORE_UPDATED', 'RECOMMENDATION_FULFILLED',
        'STOCK_SHORTAGE', 'EXPIRY_SOON', 'FOLLOW_UP_NEEDED',
        'REFERRAL_PENDING', 'SYMPTOM_SEVERITY'
    ),
    title VARCHAR(255), description TEXT,
    severity ENUM('CRITICAL', 'HIGH', 'MEDIUM', 'LOW'),
    recipients_roles JSON,
    status ENUM('ACTIVE', 'ACKNOWLEDGED', 'RESOLVED'),
    created_at TIMESTAMP,
    
    INDEX (alert_type, created_at, status)
);

-- =============== CACHÉ DE ESTADÍSTICAS ===============
CREATE TABLE statistics_cache (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    group_type ENUM('GLOBAL', 'FAMILY', 'WAREHOUSE'),
    total_beneficiaries INT,
    critical_count INT, priority_count INT, normal_count INT,
    age_distribution JSON,
    top_recommendations JSON,
    stock_gaps JSON,
    calculated_at TIMESTAMP,
    expires_at TIMESTAMP,
    
    INDEX (group_type, calculated_at)
);
```

### **3.2 Redis Cache Structure**

```
Prefix: donaciones:
├── stats:global:v2        → JSON agregado (TTL: 1h)
├── stats:family:{id}      → Estadísticas por familia (TTL: 30m)
├── semaforo:item:{id}     → Semáforo disponibilidad (TTL: 5m)
├── recommendations:user:{id} → Recomendaciones personalizadas (TTL: 1h)
└── alerts:pending:{role}  → Cola de alertas por rol (TTL: 24h)
```

### **3.3 IndexedDB (PWA - Offline)**

```javascript
// Operador PWA
{
  "stock_entries_pending": [
    {
      "uuid": "uuid-1",
      "warehouse_id": 1,
      "item_id": 5,
      "quantity": 50,
      "expiry_date": "2026-12-31",
      "status": "pending_sync",
      "created_at": "2026-08-28T10:15:00Z"
    }
  ],
  "sync_queue": [
    { "action": "CREATE", "table": "stock_entries", "data": {...}, "uuid": "uuid-1" },
    { "action": "UPDATE", "table": "beneficiaries", "data": {...}, "uuid": "uuid-2" }
  ],
  "cache_beneficiaries": [
    { "id": 1, "name": "María García", "score": 87, "priority": "CRITICAL" }
  ]
}
```

---

## **4. Flujos Técnicos Clave**

### **4.1 Flujo de Scoring de Vulnerabilidad (NUEVO - Módulo 7)**

```
┌─────────────────────────────────────────────────────────┐
│ OPERADOR REGISTRA O ACTUALIZA BENEFICIARIO              │
└──────────────────┬──────────────────────────────────────┘
                   │
        ┌──────────▼──────────┐
        │ Evento disparado:   │
        │ BeneficiaryUpdated  │
        └──────────┬──────────┘
                   │
        ┌──────────▼──────────────────────────────────┐
        │ SCORING ENGINE calcula (0-100 ponderado):  │
        │                                            │
        │ Demográfico (0-30):                        │
        │ • Edad < 5: +18 pts (crítico)             │
        │ • Embarazada: +12 pts                      │
        │ • Edad >= 60: +12 pts                      │
        │                                            │
        │ Salud (0-30):                              │
        │ • Crónico: +8 pts por enfermedad           │
        │ • Síntomas: +2 pts c/u                     │
        │ • Sin revisión médica > 90d: +6 pts       │
        │                                            │
        │ Nutricional (0-20):                        │
        │ • < 5 años: +10 pts (base)                │
        │ • Desnutrición confirmada: +15 pts        │
        │                                            │
        │ Social (0-20):                             │
        │ • Sin hogar: +20 pts                       │
        │ • Monoparental: +8 pts                     │
        │ • Discapacidad: +7 pts                     │
        └──────────┬──────────────────────────────────┘
                   │
        ┌──────────▼──────────┐
        │ GUARDAR EN DB:      │
        │ beneficiaries       │
        │ + score histórico   │
        └──────────┬──────────┘
                   │
    ┌──────────────┴──────────────┐
    │                             │
    ▼ Score >= 70              ▼ Score < 70
 🔴 CRÍTICO               🟡 PRIORITARIO / 🟢 NORMAL
    │                          │
    ├─▶ AlertService           ├─▶ UpdateCache
    │   disparar               │   (Redis)
    │   notificaciones         │
    │   (SMS/Email/Push)       │
    │   a:                     │
    │   - OPERATOR             │
    │   - COORDINATOR          │
    │   - HEALTH_PROVIDER      │
    │                          │
    └──────────┬───────────────┘
               │
        ┌──────▼──────────┐
        │ Dashboard       │
        │ actualiza en    │
        │ tiempo real     │
        │ (WebSocket)     │
        └─────────────────┘
```

### **4.2 Flujo de Recomendación de Medicinas**

```
┌──────────────────────────────────────────────────────────┐
│ RECOMENDATION SERVICE genera recomendaciones            │
│ (Disparado al actualizar score o síntomas)              │
└────────────┬─────────────────────────────────────────────┘
             │
   ┌─────────▼──────────────────────────────────┐
   │ BUSCAR PROTOCOLOS QUE APLICAN:             │
   │                                            │
   │ 1. Si edad < 5: buscar protocolos menores  │
   │ 2. Si embarazada: buscar protocolos preñez │
   │ 3. Si crónico: buscar por enfermedad      │
   │ 4. Si síntomas: buscar por síntomas       │
   │ 5. Si desnutrición: buscar nutricionales  │
   │                                            │
   │ Resultado: Lista de ProtocolRecommendation│
   └─────────┬──────────────────────────────────┘
             │
   ┌─────────▼──────────────────────────────────┐
   │ POR CADA PROTOCOLO APLICABLE:              │
   │                                            │
   │ 1. Extraer items recomendados              │
   │ 2. Verificar disponibilidad en bodegas    │
   │ 3. Calcular distancia (si geo data)       │
   │ 4. Crear BeneficiaryRecommendation        │
   │ 5. Guardar en DB con status: PENDING      │
   └─────────┬──────────────────────────────────┘
             │
   ┌─────────▼──────────────────────────────────┐
   │ ALMACENAR DATOS PARA UI:                   │
   │                                            │
   │ {                                          │
   │   item: "Leche en polvo",                  │
   │   quantity: "1 bolsa/día",                 │
   │   duration: "60 días",                     │
   │   available: 12 (stock total),             │
   │   warehouses: [                            │
   │     {name: "Centro", qty: 5, km: 2.3},    │
   │     {name: "Guayabal", qty: 7, km: 8.5}   │
   │   ]                                        │
   │ }                                          │
   └─────────┬──────────────────────────────────┘
             │
   ┌─────────▼──────────────────────────────────┐
   │ MOSTRAR EN FICHA DE BENEFICIARIO:          │
   │ • Operador ve: "Requiere: Leche"           │
   │ • Puede: Escanear bodega + confirmar       │
   │   entraga                                  │
   │ • Automáticamente: MarksRecommendation    │
   │   como FULFILLED                           │
   │ • Alerta a: DONOR (impacto)               │
   └────────────────────────────────────────────┘
```

### **4.3 Flujo de Alerta Automática Multi-Rol**

```
EVENT TRIGGER:
├─ CRITICAL_SCORE_UPDATED → Beneficiary score sube a CRITICAL
├─ STOCK_SHORTAGE → Stock de item cae debajo de demanda
├─ RECOMMENDATION_FULFILLED → Se entrega medicación
├─ EXPIRY_SOON → Item vence en < 7 días
└─ REFERRAL_PENDING → Derivación no atendida en 48h

CANALES POR ROL:
┌────────────────────────────────────────────────────┐
│ OPERATOR (Campo):                                   │
│ ├─ 🔔 Push mobile (críticos)                       │
│ ├─ 💬 WhatsApp (urgentes)                          │
│ └─ 📱 SMS (críticos solo)                          │
│                                                    │
│ COORDINATOR (Central):                             │
│ ├─ 📧 Email (semanal digest)                       │
│ ├─ 💬 WhatsApp (diario urgentes)                   │
│ ├─ 📊 Dashboard en vivo (tiempo real)             │
│ └─ 📱 SMS (críticos)                              │
│                                                    │
│ HEALTH_PROVIDER (Médico):                          │
│ ├─ 📧 Email (derivaciones)                        │
│ ├─ 💬 WhatsApp (urgente)                          │
│ └─ 📱 SMS (referral pending)                      │
│                                                    │
│ DONOR (Donante):                                   │
│ ├─ 📧 Email (semanal impacto)                     │
│ └─ 📊 Dashboard (anytime)                         │
│                                                    │
│ MUNICIPAL (Municipalidad):                         │
│ ├─ 📊 Reporte semanal (PDF)                       │
│ ├─ 🔔 SMS (críticos solo)                         │
│ └─ 📊 Dashboard inteligencia                      │
└────────────────────────────────────────────────────┘
```

---

## **5. Desglose Modular del Sistema**

### **Módulo 1: Portal Público de Búsqueda** ✅

- Buscador inteligente con sugerencias en tiempo real
- Filtros por zona, categoría, disponibilidad
- Semáforo dinámico (🟢🟡🔴⚫)
- Mapa interactivo Leaflet.js
- Modal de contacto Cloudflare Turnstile
- Deep-links WhatsApp/Llamada

### **Módulo 2: Autenticación y Roles** ✅

- Login seguro con sesiones
- Auto-registro de voluntarios
- Panel de aprobación admin
- 6 roles: Admin, Operator, Coordinator, Doctor, Donor, Municipal

### **Módulo 3: Gestión de Inventarios** ✅

- Entradas/salidas de stock
- Gestión de lotes y vencimientos
- Soporte PWA offline
- Sincronización automática

### **Módulo 4: Control Maestro** ✅

- Aprobación de ítems nuevos
- Consolidación de duplicados
- Edición de categorización
- Auditoría de cambios

### **Módulo 5: Alertas y Auditoría** ✅

- Notificaciones por severidad
- Centro de auditoría con rastreo completo
- Reportes de vencimientos
- Trazabilidad por usuario/bodega

### **Módulo 6: Entregas y Seguimiento** ✅

- Registro de entregas a beneficiarios
- Firma digital + foto comprobante
- Historial de atenciones
- Seguimiento automático

### **Módulo 7: NUEVO - Beneficiarios + Estadísticas Inteligentes** 🆕

**Componentes principales:**

1. **Ficha de Beneficiario Individual**
   - Datos demográficos (del censo)
   - Scoring de vulnerabilidad en vivo
   - Recomendaciones de medicinas/elementos
   - Historial de atenciones
   - Derivaciones a salud

2. **Motor de Scoring Ponderado (0-100)**
   - Demográfico (30 pts): Edad crítica, embarazo, adultos mayores
   - Salud (30 pts): Crónicas, síntomas, medicinas vencidas
   - Nutricional (20 pts): Desnutrición, menores, hogares grandes
   - Social (20 pts): Sin hogar, monoparental, discapacidad
   - Priorización automática: 🔴 CRÍTICO (70-100) | 🟡 PRIORITARIO (40-69) | 🟢 NORMAL (0-39)

3. **Motor de Recomendaciones**
   - Protocolos dinámicos (WHO, ICBF, Local Health, Municipal)
   - Matching inteligente: Perfil → Síntomas → Items
   - Verificación de disponibilidad en bodegas
   - Cálculo de distancia (si geodata disponible)
   - Flujo: PENDING → IN_PROGRESS → FULFILLED

4. **Dashboards Multi-Rol**
   - **Operador (Mobile):** Lista de críticos + ficha rápida + recomendaciones
   - **Coordinador (Exec):** Agregados en vivo + alertas de brecha + operadores activos
   - **Médico:** Derivaciones pendientes + validación protocolos
   - **Donante:** Impacto visual + contribuciones trackeable
   - **Municipalidad:** Inteligencia estratégica + proyecciones presupuestarias

5. **Alertas Automáticas**
   - 7 tipos: CRITICAL_SCORE_UPDATED, STOCK_SHORTAGE, RECOMMENDATION_FULFILLED, etc.
   - Multi-canal: Push, SMS, Email, WhatsApp, Dashboard
   - Escalación por severidad: CRITICAL → HIGH → MEDIUM → LOW

6. **Caché de Estadísticas**
   - Actualizaciones automáticas en Redis
   - TTL adaptativo (1h global, 30m familia, 5m semáforo)
   - Queries optimizadas con índices compuestos

---

## **6. Modelado de Vistas y Pantallas**

### **6.1 Vista Pública: Buscador con Recomendaciones (Módulo 1 + 7)**

```
┌────────────────────────────────────────────────────────┐
│ DONACIONES ROLDA — Búsqueda de Insumos                │
│ [Buscar insumos...]  [Todas las Zonas ▼] [🔍]        │
└────────────────────────────────────────────────────────┘
│ Categorías: [All] [💊 Med] [🍞 Alim] [🩺 Insumos]     │
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│ RESULTADOS (2 encontrados):                            │
├────────────────────────────────────────────────────────┤
│ 🟢 Leche en Polvo                                     │
│ ├─ Ubicación: Bodega Centro (2.3 km)                  │
│ ├─ Stock: 12 unidades                                 │
│ ├─ ℹ️ Recomendado para: Menores < 5 años (+67 niños)  │
│ │    (Data privada agregada — sin nombres)            │
│ └─ [📍 Ver Mapa] [💬 WhatsApp]                        │
│                                                        │
│ 🟡 Hierro (Suplemento)                                │
│ ├─ Ubicación: Puesto Guayabal (8.5 km)                │
│ ├─ Stock: 0 unidades (FALTA — Demand: 34 embarazadas) │
│ ├─ ⚠️ Brecha de stock: -34 (-∞%) [Alerta enviada]     │
│ └─ [📍 Ver Mapa] [💬 Contactar Coordinador]           │
└────────────────────────────────────────────────────────┘
```

### **6.2 Ficha de Beneficiario (NUEVA - Módulo 7)**

```
┌────────────────────────────────────────────────────────┐
│ 📋 PERFIL: María García López (ID: FAM-042-001)       │
│ Edad: 34 | Embarazada (7 meses) | Monoparental        │
├────────────────────────────────────────────────────────┤
│                                                        │
│ 🔴 URGENCIA: CRÍTICA (Score: 87/100)                  │
│ Factores: Embarazo avanzado + Anemia + Bajo ingreso  │
│                                                        │
│ 👨‍👩‍👧‍👦 GRUPO FAMILIAR:                                   │
│ • Juan (37, padre) — 🟢 Normal (32/100)               │
│ • María (34, madre) — 🔴 CRÍTICO (87/100)            │
│ • Sofia (7, hija) — 🟡 Prioritario (55/100)          │
│ • Lucas (3, hijo) — 🔴 CRÍTICO (92/100)              │
│                                                        │
│ 💊 RECOMENDACIONES AUTOMÁTICAS (95% confianza):       │
│                                                        │
│ ✅ Hierro (Suplemento)                                │
│    • Razón: Anemia + Embarazo                        │
│    • Dosis: 1 cápsula/día                            │
│    • Duración: 60 días                               │
│    • 📦 Disponible: 0 en stock (FALTA)               │
│                                                        │
│ ✅ Ácido Fólico                                       │
│    • Razón: Embarazo (prevención defectos)           │
│    • Dosis: 1 comprimido/día                         │
│    • Duración: 60 días                               │
│    • 📦 Disponible: 5 unidades en Bodega Centro      │
│    • 🟡 Monitorear (stock bajo)                      │
│                                                        │
│ ✅ Leche en Polvo (Lucas, malnutrición)               │
│    • Razón: Menor < 5 + desnutrición                 │
│    • Dosis: 1 bolsa/día                              │
│    • Duración: 60 días                               │
│    • 📦 Disponible: 12 en Centro, 7 en Guayabal      │
│    • 🔄 Parcialmente disponible                      │
│                                                        │
│ ❓ Protocolo local (pendiente validación médica)      │
│    [🩺 Derivar a Puesto de Salud]                    │
│                                                        │
├────────────────────────────────────────────────────────┤
│ 📤 ENTREGAS REALIZADAS:                               │
│ • 25/ago 14:30 → Leche (2 bolsas), Vitamina A        │
│   Entregado por: Juan Pérez | Estado: ✅ Confirmado  │
│                                                        │
│ • 20/ago 10:00 → Registro de censo                    │
│   Registrado por: Carmen Ramos                        │
│                                                        │
│ [📋 Ver Historial Completo]                          │
│                                                        │
│ [✅ Confirmar Entrega]  [🩺 Derivar Médico]           │
│ [📸 Añadir Foto]      [📝 Notas Operador]             │
└────────────────────────────────────────────────────────┘
```

### **6.3 Dashboard Coordinador (NUEVA - Módulo 7)**

```
┌────────────────────────────────────────────────────────┐
│ 📊 DASHBOARD COORDINADOR — Estado Operacional         │
├────────────────────────────────────────────────────────┤
│                                                        │
│ 🎯 ESTADO GENERAL (Actualizado: Hace 2 min)          │
│ • Familias censadas: 284 (+42 en 24h)                │
│ • Personas: 1,127                                    │
│ • Score vulnerabilidad promedio: 45/100               │
│                                                        │
│ 🔴🟡🟢 DISTRIBUCIÓN DE PRIORIDADES                     │
│ ████████░░░ 🔴 Críticas: 127 (11%) → [Ver 127]       │
│ █████████████████░░░ 🟡 Prioritarias: 384 (34%)      │
│ ██████████████████████████░ 🟢 Normales: 616 (55%)   │
│                                                        │
│ ⚠️ TOP 5 NECESIDADES CRÍTICAS:                        │
│ 1. 🍼 Leche en polvo: 67 familias | Stock: 12        │
│    BRECHA: -55 (-82%)  [🔴 ALERTA]                   │
│    Acción: [📧 Alerta a Donantes] [📞 Llamar ONG]    │
│                                                        │
│ 2. 💊 Hierro: 34 familias | Stock: 0                │
│    BRECHA: -34 (∞%)  [🔴 CRÍTICO]                    │
│                                                        │
│ 3. 🧬 Vitamina A: 43 familias | Stock: 28             │
│    BRECHA: -15 (-35%)  [🟡 Monitorear]               │
│                                                        │
│ 4. 🪱 Antiparasitarios: 38 familias | Stock: 1       │
│    BRECHA: -37 (-97%)  [🔴 CRÍTICO]                  │
│                                                        │
│ 5. 🥛 Alimentos especiales: 22 familias | Stock: 5   │
│    BRECHA: -17 (-77%)  [🟡 Monitorear]               │
│                                                        │
│ 📡 ACTIVIDAD EN TIEMPO REAL:                          │
│ • Operadores activos: 5/6 (83%)                      │
│ • Entregas en últimas 2h: 8                          │
│ • Derivaciones a salud pendientes: 3                 │
│ • Alertas no leídas: 12                              │
│                                                        │
│ 📊 COMPARATIVO ÚLTIMOS 7 DÍAS:                        │
│ • Nuevas familias: +42 (+15%)                        │
│ • Score promedio: Subió a 45/100 (antes 42)          │
│ • Stock items críticos: Bajó 23%                     │
│                                                        │
│ 🗺️ COBERTURA GEOGRÁFICA:                              │
│ • Bodega Centro: 68% atendidas                       │
│ • Guayabal: 60% atendidas                            │
│ • Corregimientos: 53% atendidas                      │
│                                                        │
│ [📤 Exportar Reportes PDF]  [🔔 Alertar Alcalde]     │
│ [⚙️ Configurar Protocolos]  [📊 Ver Analítica]       │
└────────────────────────────────────────────────────────┘
```

---

## **7. Planes de Integración Futura**

### **Fase II: Telegram / WhatsApp Integration**

El sistema de alertas está **completamente desacoplado** mediante Laravel Notification Channels.

**Fase I (MVP):** Alertas en dashboard + Email/SMS (backend preparado)

**Fase II:** Activar canales externos:

```php
// app/Notifications/CriticalScoreNotification.php
public function via($notifiable): array {
    return [
        'database',        // MVP
        'mail',           // MVP
        'sms',            // MVP (con Twilio)
        'telegram',       // FASE II
        'whatsapp',       // FASE II (con Meta API)
    ];
}
```

**Preparación actual:** Clases de notificación listas, solo faltan webhooks/SDK de proveedores.

---

## **📈 Métricas Clave (Resumen Ejecutivo)**

### **Timeline MVP**
- **Especificación (Completado):** 7 documentos técnicos
- **Desarrollo:** 11 días (287-302 horas, paralelizable)
  - Módulos 1-6: Días 1-5
  - Módulo 7: Días 6-9
  - Testing + DevOps: Días 8-11
- **Go-live Staging:** Viernes 30 agosto 2026
- **Go-live Producción:** Lunes 2 septiembre 2026

### **Inversión AWS (3 meses)**
- Mes 1: $398 (50% first month)
- Mes 2-3: $644-$706/mes
- **Total 3 meses:** $1,748

### **Stack Confirmado**
✅ Laravel 11 (PHP 8.3+) + MySQL 8.0 + Redis 7.0  
✅ Alpine.js 3.x + Tailwind CSS 3.x  
✅ PWA + IndexedDB (Offline-first)  
✅ Leaflet.js + OpenStreetMap  
✅ Cloudflare Turnstile (Antibot)  
✅ Laravel Reverb o Pusher (Real-time)

### **Cumplimiento Regulatorio**
✅ LSPP (Ley 1581/2012) — Privacidad + Auditoría  
✅ Encriptación TLS 1.3 + AES-256 en reposo  
✅ Consent management + Derecho olvido  
✅ Audit logs con retención 2 años  
✅ Role-based access control (RBAC)

---

## **✅ Validación de Arquitectura**

**Checklist de Completitud:**

- [x] Especificaciones técnicas expandidas (7 módulos)
- [x] Modelo de datos (MySQL + Redis + IndexedDB)
- [x] Funciones adicionales propuestas (5 opciones)
- [x] Diagramas de flujos (5+ flujos clave)
- [x] Análisis infraestructura AWS (3 opciones)
- [x] Estimación costos 3 meses (con escenarios)
- [x] Plan de entrega MVP (11 días)
- [x] Matriz LSPP y compliance (auditoría + privacidad)
- [x] **NUEVO: Módulo 7 (Beneficiarios + Estadísticas Inteligentes)**
  - [x] Modelo de datos beneficiarios (7 nuevas tablas)
  - [x] Motor de scoring ponderado (algoritmo detallado)
  - [x] Motor de recomendaciones (protocolos + matching)
  - [x] Dashboards multi-rol (5 variantes)
  - [x] Sistema de alertas (7 tipos, multi-canal)
  - [x] Integración con módulos 1,3,6

---

**Documentación Completa:** Agosto 2026  
**Pronto:** Diagramas ASCII detallados de flujos + Ejemplo de código (Controllers, Services, Models)

