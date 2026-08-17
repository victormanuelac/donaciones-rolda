# 🏥 **MÓDULO 7: BENEFICIARIOS + ESTADÍSTICAS INTELIGENTES**

**Sistema de Perfilación, Scoring de Vulnerabilidad y Recomendaciones Automáticas de Medicinas**

**Versión:** 1.0  
**Estado:** 🟢 Listo para Desarrollo (Días 6-9 del MVP)  
**Esfuerzo Estimado:** 42-45 horas (parallelizable)  
**Audiencia:** Operadores, Coordinador Central, Médicos, Donantes, Municipalidad

---

## **📋 Tabla de Contenidos**

1. [Visión General](#1-visión-general)
2. [Componentes Principales](#2-componentes-principales)
3. [Modelo de Datos Detallado](#3-modelo-de-datos-detallado)
4. [Motor de Scoring Ponderado](#4-motor-de-scoring-ponderado)
5. [Motor de Recomendaciones](#5-motor-de-recomendaciones)
6. [Dashboards por Rol](#6-dashboards-por-rol)
7. [Sistema de Alertas](#7-sistema-de-alertas)
8. [Integración con Otros Módulos](#8-integración-con-otros-módulos)
9. [Código de Ejemplo](#9-código-de-ejemplo)
10. [Plan de Implementación](#10-plan-de-implementación)

---

## **1. Visión General**

### **1.1 Problema que Resuelve**

En situaciones de emergencia, los operadores de campo necesitan:
- ✅ Identificar **quién es más vulnerable** rápidamente
- ✅ Saber **qué medicinas/elementos necesita** cada familia
- ✅ **Priorizar entregas** de forma justa y eficiente
- ✅ **Rastrear impacto** real de donaciones
- ✅ **Coordinar derivaciones** a servicios de salud

**Sin este módulo:** Decisiones basadas en corazonada, entregas ad-hoc, sin data, difícil priorización.

**Con este módulo:** Scoring automático, recomendaciones basadas en evidencia, impacto medible, decisiones coordinadas.

### **1.2 Personas Beneficiadas**

| Rol | Beneficio |
|---|---|
| **Operador (Campo)** | Ve lista priorizada, recomendaciones claras, escanea y entrega en 2 min |
| **Coordinador** | Dashboard ejecutivo con alertas de brechas, toma decisiones estratégicas |
| **Médico** | Valida recomendaciones, hace derivaciones, ve efectividad |
| **Donante** | Ve impacto concreto: "Tu leche llegó a 8 niños de 5 familias" |
| **Municipalidad** | Data para presupuestar, planificar, comunicar a ciudadanía |

---

## **2. Componentes Principales**

### **2.1 Ficha Individual de Beneficiario**

Perfil consolidado que muestra:

```
┌─────────────────────────────────────────────────────┐
│ FICHA: María García López (FAM-042-001)            │
├─────────────────────────────────────────────────────┤
│ 👤 DATOS PERSONALES                                │
│ • Edad: 34 años                                    │
│ • Género: Mujer                                    │
│ • Relación familia: Madre/Cabeza de hogar          │
│ • Teléfono: +57 312 XXX (privado)                 │
│ • Dirección: Calle 5 #10-20 (privado)             │
│                                                    │
│ 🔴 VULNERABILIDAD ACTUAL                           │
│ • Score: 87/100 (CRÍTICO)                         │
│ • Última actualización: 28/ago 10:15               │
│ • Trending: ↑ Aumentó 12 pts (antes 75)           │
│                                                    │
│ ⚠️ FACTORES CRÍTICOS:                              │
│ • Embarazada (7 meses)                            │
│ • Anemia (confirmada por médico)                  │
│ • Monoparental (solo ingresos propios)            │
│ • Desempleo (3 meses sin trabajo)                 │
│                                                    │
│ 👨‍👩‍👧‍👦 COMPOSICIÓN FAMILIAR (4 personas):          │
│ • Juan (37, padre) — Score 32 (Normal)            │
│ • María (34, madre) — Score 87 (Crítico)         │
│ • Sofia (7, hija) — Score 55 (Prioritario)       │
│ • Lucas (3, hijo) — Score 92 (Crítico)           │
│                                                    │
│ 💊 RECOMENDACIONES PERSONALIZADAS:                 │
│ 1. Hierro (1 cap/día, 60 días) — 95% confianza   │
│ 2. Ácido fólico (1 comp/día, 60 días) — 98%      │
│ 3. Leche en polvo (Lucas) — 90% confianza        │
│ 4. Antiparasitarios (Sofia) — 85% confianza      │
│                                                    │
│ 📦 DISPONIBILIDAD DE RECURSOS:                     │
│ ✅ Hierro: 0 en stock (FALTA)                     │
│ ✅ Ácido fólico: 5 en Bodega Centro               │
│ ✅ Leche: 12 Centro + 7 Guayabal                  │
│ ✅ Antiparasitarios: 1 en Centro                  │
│                                                    │
│ 📋 HISTORIAL:                                      │
│ • 25/ago: Leche + Vitamina A (✅ Entregado)       │
│ • 20/ago: Registro en censo                       │
│                                                    │
│ 🩺 ESTADO MÉDICO:                                  │
│ • Última revisión: 25/ago (médico validó anemia) │
│ • Derivaciones pendientes: Ninguna                │
│ • Seguimiento: Requerido                          │
│                                                    │
│ [✅ Confirmar Entrega] [🩺 Derivar] [📝 Notas]   │
└─────────────────────────────────────────────────────┘
```

### **2.2 Motor de Scoring de Vulnerabilidad (0-100 Ponderado)**

Sistema que calcula automáticamente un score integral basado en:

#### **Demográfico (0-30 pts)**
```
Edad < 5 años:                      +18 pts (crítico)
Embarazo:                           +12 pts (+8 si trimestre 3)
Edad >= 60 años:                    +12 pts
Edad 5-18:                          +8 pts
```

**Lógica:** Menores y embarazadas requieren recursos específicos; adultos mayores mayor cuidado.

#### **Salud (0-30 pts)**
```
Crónico (Diabetes, Hipertensión):   +8 pts c/u
Síntomas actuales:                  +2 pts c/u
Sin revisión médica > 90 días:       +6 pts
VIH/TBC/Asma severo:               +15 pts
```

**Lógica:** Enfermedades persistentes requieren medicinas continuas.

#### **Nutricional (0-20 pts)**
```
Menor < 5 años:                    +10 pts (base + riesgo)
Desnutrición confirmada:            +15 pts
Hogar > 4 menores:                 +5 pts
Familia grande (> 6 personas):      +5 pts
```

**Lógica:** Menores y desnutridos requieren proteína, vitaminas.

#### **Social (0-20 pts)**
```
Sin hogar:                         +20 pts (máximo)
Monoparental:                      +8 pts
Desempleo:                         +6 pts
Discapacidad:                      +7 pts
Sin educación:                     +4 pts
```

**Lógica:** Vulnerabilidad socioeconómica multiplica riesgos.

#### **Scoring Decisional**
```
Score >= 70     → 🔴 CRÍTICO (Acción inmediata)
Score 40-69     → 🟡 PRIORITARIO (Próximos 7 días)
Score < 40      → 🟢 NORMAL (Seguimiento regular)
```

### **2.3 Motor de Recomendaciones Inteligentes**

Genera medicinas/elementos basado en:

1. **Protocolos (WHO, ICBF, Local Health, Municipal)**
   - Cada protocolo tiene trigger_condition (JSON)
   - Ej: `{"age_min": 0, "age_max": 5, "malnutrition": true}`

2. **Matching Automático**
   - Si beneficiario cumple condición → aplica protocolo
   - Extrae items recomendados
   - Verifica disponibilidad en bodegas

3. **Scoring de Confianza**
   - WHO/ICBF: 0.95 (máxima)
   - Local Health: 0.85-0.90
   - Requiere validación médica si < 0.75

4. **Resultado**
   - Estado: PENDING (espera confirmación operador)
   - Una vez entregado: FULFILLED + timestamp
   - Alerta automática a donante: "Tu donación llegó"

### **2.4 Dashboards por Rol (6 Variantes)**

Cada rol ve información filtrada por su necesidad:

- **Operador:** Lista de críticos + ficha rápida + recomendaciones
- **Coordinador:** Agregados + alertas de brecha + operadores en vivo
- **Médico:** Derivaciones + validación protocolos
- **Donante:** Impacto (quién usó tu donación)
- **Municipalidad:** Inteligencia estratégica (tendencias, presupuesto)
- **Admin:** Auditoría + configuración

### **2.5 Sistema de Alertas Multi-Canal**

Cuando ocurre evento crítico → notificaciones a roles correspondientes:

```
EVENTO: Score pasa a CRÍTICO
 ↓
CANALES:
├─ 🔔 Operador: Push mobile + WhatsApp
├─ 📧 Coordinador: Email + Dashboard
├─ 📱 Médico: Email + SMS
└─ 📊 Municipalidad: Reporte semanal

EVENTO: Stock de Leche cae bajo demanda
 ↓
CANALES:
├─ 🔔 Coordinador: SMS urgente
├─ ❤️ Donantes: Email "Se necesita Leche"
└─ 📞 Municipalidad: Llamada de coordinador
```

---

## **3. Modelo de Datos Detallado**

### **3.1 Tabla: beneficiaries**

```sql
CREATE TABLE beneficiaries (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    family_id BIGINT NOT NULL,
    census_entry_id BIGINT NOT NULL,
    
    -- Identidad
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('M', 'F', 'O'),
    phone VARCHAR(20),
    email VARCHAR(100),
    
    -- Vulnerabilidad
    vulnerability_score DECIMAL(5,2) DEFAULT 0,
    priority_level ENUM('CRITICAL', 'PRIORITY', 'NORMAL') DEFAULT 'NORMAL',
    last_score_update TIMESTAMP,
    
    -- Salud (autorreporte + validación médica)
    chronic_conditions JSON, -- ["Diabetes", "Asma", ...]
    current_symptoms JSON,   -- ["Fiebre", "Tos", ...]
    last_medical_review DATE,
    medical_notes TEXT,      -- Notas médico validador
    
    -- Embarazo/Vulnerable
    is_pregnant BOOLEAN DEFAULT FALSE,
    pregnancy_trimester INT, -- 1-9
    has_disability BOOLEAN DEFAULT FALSE,
    disability_type VARCHAR(100),
    
    -- Social
    is_single_parent BOOLEAN DEFAULT FALSE,
    has_no_home BOOLEAN DEFAULT FALSE,
    employment_status ENUM('EMPLOYED', 'UNEMPLOYED', 'STUDENT', 'RETIRED'),
    educational_level ENUM('NONE', 'PRIMARY', 'SECONDARY', 'TERTIARY'),
    household_size INT,
    
    -- Privacy (LSPP Ley 1581/2012)
    privacy_consent BOOLEAN DEFAULT TRUE,
    data_visibility ENUM('PRIVATE', 'FAMILY_ONLY', 'OPERATORS', 'ALL_AUTHORIZED'),
    
    -- Auditoría
    registered_by_user_id BIGINT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_updated_by_user_id BIGINT,
    
    INDEX (family_id),
    INDEX (vulnerability_score),
    INDEX (priority_level),
    INDEX (registered_at),
    FOREIGN KEY (family_id) REFERENCES families(id),
    FOREIGN KEY (census_entry_id) REFERENCES census_entries(id),
    FOREIGN KEY (registered_by_user_id) REFERENCES users(id)
);
```

### **3.2 Tabla: vulnerability_scores (Histórico)**

```sql
CREATE TABLE vulnerability_scores (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT NOT NULL,
    
    -- Componentes
    demographic_score INT,      -- 0-30
    health_score INT,           -- 0-30
    nutritional_score INT,      -- 0-20
    social_score INT,           -- 0-20
    
    -- Total y nivel
    total_score INT,            -- 0-100
    priority_level ENUM('CRITICAL', 'PRIORITY', 'NORMAL'),
    
    -- Factores que influenciaron
    contributing_factors JSON,
    
    -- Auditoría
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    calculated_by_rule_version INT,
    
    INDEX (beneficiary_id),
    INDEX (calculated_at),
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id)
);
```

### **3.3 Tabla: protocol_recommendations (Base de Protocolos)**

```sql
CREATE TABLE protocol_recommendations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Identificación
    protocol_name VARCHAR(255) NOT NULL UNIQUE,
    source ENUM('WHO', 'ICBF', 'LOCAL_HEALTH', 'MUNICIPAL', 'DONOR'),
    source_url VARCHAR(255),
    
    -- Condición de aplicación (JSON flexible)
    trigger_condition JSON, -- {
                            --   "age_min": 0,
                            --   "age_max": 5,
                            --   "chronic_diseases": ["Asma"],
                            --   "symptoms": ["Tos"],
                            --   "malnutrition": true,
                            --   "pregnancy": false
                            -- }
    
    -- Items recomendados
    recommended_items JSON, -- [
                            --   {
                            --     "item_id": 1,
                            --     "quantity": 1,
                            --     "frequency": "daily|weekly|once",
                            --     "duration_days": 60
                            --   }
                            -- ]
    
    -- Validación
    confidence_level DECIMAL(3,2), -- 0.70-0.95
    priority_override ENUM('CRITICAL', 'PRIORITY', 'NORMAL'),
    requires_medical_approval BOOLEAN DEFAULT FALSE,
    approved_by_user_id BIGINT,
    approved_at TIMESTAMP,
    
    -- Vigencia
    is_active BOOLEAN DEFAULT TRUE,
    valid_from DATE,
    valid_until DATE,
    
    -- Auditoría
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FULLTEXT INDEX (protocol_name, notes),
    INDEX (is_active, valid_from, valid_until)
);
```

### **3.4 Tabla: beneficiary_recommendations (Personalizadas)**

```sql
CREATE TABLE beneficiary_recommendations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT NOT NULL,
    protocol_id BIGINT NOT NULL,
    
    -- Item específico
    item_id BIGINT NOT NULL,
    quantity_recommended INT,
    frequency VARCHAR(50), -- "daily", "weekly", "once"
    duration_days INT,
    
    -- Estado del cumplimiento
    status ENUM('PENDING', 'IN_PROGRESS', 'FULFILLED', 'EXPIRED', 'CANCELLED'),
    fulfillment_percentage INT DEFAULT 0,
    
    -- Matching con stock
    available_stock INT DEFAULT 0,
    available_warehouses JSON, -- [{warehouse_id, qty, distance_km, expiry_date}]
    
    -- Auditoría
    recommended_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recommended_by_user_id BIGINT,
    fulfilled_at TIMESTAMP,
    fulfilled_by_user_id BIGINT,
    
    -- Notas
    notes TEXT,
    
    INDEX (beneficiary_id, status),
    INDEX (recommended_at),
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id),
    FOREIGN KEY (protocol_id) REFERENCES protocol_recommendations(id),
    FOREIGN KEY (item_id) REFERENCES master_items(id)
);
```

### **3.5 Tabla: care_history (Entregas Realizadas)**

```sql
CREATE TABLE care_history (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT NOT NULL,
    
    -- Qué se entregó
    items_delivered JSON, -- [{item_id, quantity, warehouse}]
    
    -- Quién entregó
    delivered_by_user_id BIGINT NOT NULL,
    delivery_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Confirmación
    received_by_beneficiary BOOLEAN DEFAULT FALSE,
    recipient_signature_path VARCHAR(255),
    photo_path VARCHAR(255),
    
    -- Seguimiento
    follow_up_required BOOLEAN DEFAULT FALSE,
    follow_up_date DATE,
    follow_up_status ENUM('PENDING', 'DONE', 'NO_SHOW'),
    
    -- Auditoría
    notes TEXT,
    
    INDEX (beneficiary_id, delivery_date),
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id),
    FOREIGN KEY (delivered_by_user_id) REFERENCES users(id)
);
```

### **3.6 Tabla: health_referrals (Derivaciones)**

```sql
CREATE TABLE health_referrals (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT NOT NULL,
    referred_by_user_id BIGINT NOT NULL,
    
    -- Razón de derivación
    reason TEXT NOT NULL,
    reported_symptoms JSON,
    urgency ENUM('ROUTINE', 'URGENT', 'EMERGENCY'),
    
    -- A dónde se deriva
    health_facility_id BIGINT,
    health_facility_name VARCHAR(255),
    health_facility_phone VARCHAR(20),
    
    -- Estado
    status ENUM('PENDING', 'RECEIVED', 'ATTENDED', 'COMPLETED', 'CANCELLED'),
    referred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attended_at TIMESTAMP,
    
    -- Resultados (médico completa)
    attended_by_health_provider VARCHAR(255),
    diagnosis TEXT,
    treatment_prescribed JSON,
    
    -- Integración externa
    external_referral_id VARCHAR(100),
    
    INDEX (beneficiary_id, status),
    INDEX (referred_at),
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id),
    FOREIGN KEY (referred_by_user_id) REFERENCES users(id)
);
```

### **3.7 Tabla: alerts (Alertas Automáticas)**

```sql
CREATE TABLE alerts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    beneficiary_id BIGINT,
    
    -- Tipo de alerta
    alert_type ENUM(
        'CRITICAL_SCORE_UPDATED',
        'RECOMMENDATION_FULFILLED',
        'STOCK_SHORTAGE',
        'EXPIRY_SOON',
        'FOLLOW_UP_NEEDED',
        'REFERRAL_PENDING',
        'SYMPTOM_SEVERITY'
    ),
    
    -- Contenido
    title VARCHAR(255) NOT NULL,
    description TEXT,
    severity ENUM('CRITICAL', 'HIGH', 'MEDIUM', 'LOW'),
    
    -- A quiénes va dirigida
    recipients_roles JSON, -- ["OPERATOR", "COORDINATOR", ...]
    
    -- Estado
    status ENUM('ACTIVE', 'ACKNOWLEDGED', 'RESOLVED'),
    
    -- Auditoría
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by_user_id BIGINT,
    acknowledged_at TIMESTAMP,
    resolved_at TIMESTAMP,
    resolved_by_user_id BIGINT,
    resolution_notes TEXT,
    
    INDEX (alert_type, status, created_at),
    INDEX (severity, created_at)
);
```

### **3.8 Tabla: statistics_cache (Caché de Performance)**

```sql
CREATE TABLE statistics_cache (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    
    -- Agrupación
    group_type ENUM('GLOBAL', 'FAMILY', 'WAREHOUSE', 'HEALTH_FACILITY'),
    group_id BIGINT,
    
    -- Métricas agregadas
    total_beneficiaries INT,
    critical_count INT,
    priority_count INT,
    normal_count INT,
    
    -- Demógraficos
    age_distribution JSON, -- {"0-5": 20, "5-18": 50, ...}
    pregnant_count INT,
    disability_count INT,
    single_parent_count INT,
    
    -- Top necesidades
    top_recommendations JSON, -- [{item, count, stock, gap_pct}]
    
    -- Brecha stock vs demanda
    stock_gaps JSON, -- [{item, demand, stock, gap_pct}]
    
    -- Control
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    
    INDEX (group_type, group_id, calculated_at)
);
```

---

## **4. Motor de Scoring Ponderado**

### **4.1 Pseudocódigo del Algoritmo**

```
ENTRADA: Beneficiary object
SALIDA: {total_score, priority_level, contributing_factors}

PROCESO:
1. DEMOGRÁFICO (0-30)
   IF age < 5:
       score += 18
   ELSE IF age >= 60:
       score += 12
   ELSE IF age 5-18:
       score += 8
   
   IF is_pregnant:
       score += 12
       IF pregnancy_trimester >= 7:
           score += 8

2. SALUD (0-30)
   FOR EACH chronic_condition:
       IF condition IN ['Diabetes', 'Hipertensión', 'Asma', 'VIH']:
           score += 8
       ELSE:
           score += 4
   
   FOR EACH symptom:
       score += 2
   
   IF last_medical_review > 90 days AGO:
       score += 6

3. NUTRICIONAL (0-20)
   IF age < 5:
       score += 10
   
   IF medical_notes CONTAINS 'desnutrición':
       score += 15
   
   IF family.children_count > 3:
       score += 5

4. SOCIAL (0-20)
   IF has_no_home:
       score += 20
   
   IF is_single_parent:
       score += 8
   
   IF employment_status == 'UNEMPLOYED':
       score += 6
   
   IF has_disability:
       score += 7

5. DETERMINAR NIVEL:
   IF total_score >= 70:
       priority_level = 'CRITICAL'
   ELSE IF total_score >= 40:
       priority_level = 'PRIORITY'
   ELSE:
       priority_level = 'NORMAL'

6. GUARDAR:
   - Actualizar beneficiaries.vulnerability_score
   - Crear registro en vulnerability_scores (histórico)
   - Disparar alertas si cambió a CRITICAL
   - Invalidar caché en Redis

7. RETORNAR: {score, level, factors}
```

### **4.2 Cálculo Ponderado (Ejemplo Real)**

**Beneficiario: María García López (edad 34, embarazada)**

```
COMPONENTE             PUNTOS    DETALLE
═════════════════════════════════════════════════════════
Demográfico
├─ Embarazada          +12       (criterio: is_pregnant)
├─ Trimestre 3         +8        (pregnancy_trimester = 7)
├─ No < 5              0         
└─ No >= 60            0         
   SUBTOTAL:           20 / 30

Salud
├─ Anemia (crónica)    +8        (chronic_conditions)
├─ Síntomas (3)        +6        (fiebre, mareos, fatiga)
├─ Revisión médica     0         (hace 3 días)
└─ Medicinas vencidas  +6        (más de 30 días sin)
   SUBTOTAL:           20 / 30

Nutricional
├─ No < 5              0         
├─ No desnutrida       0         
├─ Familia grande      +5        (4 personas)
└─ Bebé < 5            +10       (Lucas, hermano)
   SUBTOTAL:           15 / 20

Social
├─ Hogar              0         
├─ Monoparental       +8        (solo ingresos María)
├─ Desempleo          +6        (3 meses sin trabajo)
├─ No discapacidad    0         
└─ Educación          0         
   SUBTOTAL:           14 / 20

═════════════════════════════════════════════════════════
TOTAL SCORE:                    69 / 100
AJUSTE:                         (capped at 100)

RESULTADO:            69 → 🟡 PRIORITARIO
PERO RECLASIF.:       Embarazo + Anemia → CRÍTICO (justificado 70+)
FINAL:                Score: 87 → 🔴 CRÍTICO

FACTORES CONTRIBUTING:
1. "Embarazo avanzado (trimestre 3)"
2. "Anemia crónica sin medicinas"
3. "Síntomas actuales (fiebre, mareos)"
4. "Situación monoparental + desempleo"
5. "Hijo menor < 5 años con desnutrición"
```

---

## **5. Motor de Recomendaciones**

### **5.1 Protocolos Ejemplo (Base de Datos)**

#### **Protocolo 1: Embarazo Saludable (WHO)**

```json
{
  "protocol_name": "Embarazo Saludable - WHO",
  "source": "WHO",
  "confidence_level": 0.98,
  "trigger_condition": {
    "age_min": 15,
    "age_max": 50,
    "pregnancy": true,
    "pregnancy_trimester": [1, 2, 3]
  },
  "recommended_items": [
    {
      "item_id": 15,
      "item_name": "Hierro (Suplemento)",
      "quantity": 1,
      "frequency": "daily",
      "duration_days": 270,
      "notes": "Prevenir anemia en embarazo"
    },
    {
      "item_id": 16,
      "item_name": "Ácido Fólico",
      "quantity": 1,
      "frequency": "daily",
      "duration_days": 270,
      "notes": "Defectos neural prevention"
    },
    {
      "item_id": 17,
      "item_name": "Vitamina D",
      "quantity": 1,
      "frequency": "daily",
      "duration_days": 270
    }
  ]
}
```

#### **Protocolo 2: Menores < 5 Años (ICBF)**

```json
{
  "protocol_name": "Suplementación Menores < 5 años - ICBF",
  "source": "ICBF",
  "confidence_level": 0.95,
  "trigger_condition": {
    "age_min": 0,
    "age_max": 5
  },
  "recommended_items": [
    {
      "item_id": 1,
      "item_name": "Leche en Polvo",
      "quantity": 1,
      "frequency": "daily",
      "duration_days": 365,
      "notes": "Nutrición completa"
    },
    {
      "item_id": 8,
      "item_name": "Vitamina A",
      "quantity": 1,
      "frequency": "weekly",
      "duration_days": 365,
      "notes": "Deficiencia en menores vulnerables"
    },
    {
      "item_id": 9,
      "item_name": "Antiparasitarios",
      "quantity": 1,
      "frequency": "every_6_months",
      "duration_days": 365,
      "notes": "Prevención parasitosis"
    }
  ]
}
```

#### **Protocolo 3: Diabetes (Local Health)**

```json
{
  "protocol_name": "Manejo Diabetes - Local Health",
  "source": "LOCAL_HEALTH",
  "confidence_level": 0.85,
  "trigger_condition": {
    "chronic_diseases": ["Diabetes"]
  },
  "recommended_items": [
    {
      "item_id": 25,
      "item_name": "Metformina 500mg",
      "quantity": 2,
      "frequency": "daily",
      "duration_days": 90,
      "requires_medical_approval": true
    },
    {
      "item_id": 26,
      "item_name": "Medidores de glucosa",
      "quantity": 1,
      "frequency": "once",
      "duration_days": 90
    }
  ]
}
```

### **5.2 Flujo de Matching**

```
┌─────────────────────────────────────────┐
│ BENEFICIARY ACTUALIZADO EN SISTEMA      │
└────────────┬────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────┐
│ BUSCAR PROTOCOLOS APLICABLES:           │
│ SELECT * FROM protocol_recommendations  │
│ WHERE:                                  │
│ - is_active = TRUE                      │
│ - JSON_EXTRACT(trigger_condition,       │
│   '$.age_min') <= edad                  │
│ - JSON_EXTRACT(trigger_condition,       │
│   '$.age_max') >= edad                  │
│ - (pregnancy = true OR no filter)       │
│ - (chronic en lista O no filter)        │
└────────────┬────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────┐
│ PARA CADA PROTOCOLO APLICABLE:          │
│                                         │
│ FOR protocol IN protocolos:             │
│   FOR item IN protocol.recommended:     │
│     1. Verificar stock en bodegas       │
│     2. Calcular distancia               │
│     3. Crear recomendación (PENDING)    │
└────────────┬────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────┐
│ INSERTAR EN beneficiary_recommendations │
│                                         │
│ INSERT INTO beneficiary_recommendations │
│ (beneficiary_id, protocol_id, item_id,  │
│  quantity_recommended, frequency,       │
│  duration_days, status,                 │
│  available_stock, available_warehouses) │
│ VALUES (...)                            │
└────────────┬────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────┐
│ RENDERIZAR EN FICHA:                    │
│                                         │
│ ✅ Leche en polvo (12 en stock)        │
│ ✅ Hierro (0 — FALTA)                  │
│ ✅ Vitamina A (28 en stock)            │
│                                         │
│ [✅ Confirmar Entregas]                │
└─────────────────────────────────────────┘
```

---

## **6. Dashboards por Rol**

### **6.1 Dashboard Operador (Mobile-First)**

```
┌─────────────────────────────────────────┐
│ 📱 MI TURNO — Donaciones Rolda          │
│ Estado: 🟢 ONLINE | Bodega: Centro     │
├─────────────────────────────────────────┤
│                                         │
│ 🔴 CRÍTICOS A VISITAR (3):              │
│                                         │
│ 1️⃣ María García (Score: 87)             │
│    Embarazada + Anemia                  │
│    Requiere: Hierro, Ácido fólico       │
│    [📋 Ver Ficha] [✅ Marcar Visitado] │
│                                         │
│ 2️⃣ Lucas Pérez (Score: 91)              │
│    < 5 años + Malnutrición              │
│    Requiere: Leche, Vitaminas           │
│    [📋 Ver Ficha] [✅ Marcar Visitado] │
│                                         │
│ 3️⃣ Juan López (Score: 72)               │
│    Diabético + Sin medicinas            │
│    Requiere: Metformina                 │
│    [📋 Ver Ficha] [✅ Marcar Visitado] │
│                                         │
│ 🟡 PRIORITARIOS (8) [🔽 Ver más]        │
│                                         │
│ 📊 MI AVANCE:                           │
│ • Visitadas: 5/12                       │
│ • Entregas confirmadas: 8               │
│ • Fotos: 8/8                            │
│                                         │
│ [🔄 Sincronizar] [📞 Coordinador]      │
└─────────────────────────────────────────┘
```

### **6.2 Dashboard Coordinador (Ejecutivo)**

```
┌─────────────────────────────────────────────────────────┐
│ 📊 COORDINADOR CENTRAL — Roldanillo                    │
│ Actualización: Hace 2 minutos                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ 🎯 ESTADO GENERAL:                                     │
│ • Familias censadas: 284 (+42 en 24h)                 │
│ • Personas: 1,127                                     │
│ • Score promedio: 45/100 (Prioritario)                │
│                                                         │
│ 🔴🟡🟢 DISTRIBUCIÓN:                                   │
│ ▓▓▓▓▓▓▓░░░░ 127 CRÍTICOS (11%)   [📌 Ver 127]        │
│ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░ 384 PRIORITARIOS (34%)            │
│ ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓ 616 NORMALES (55%)           │
│                                                         │
│ ⚠️ TOP 5 BRECHAS DE STOCK:                             │
│ 1. 🍼 LECHE: Demanda 67 | Stock 12 → GAP -82%        │
│    🔴 CRÍTICO → [📧 Alerta Donantes] [📞 ONG]         │
│                                                         │
│ 2. 💊 HIERRO: Demanda 34 | Stock 0 → GAP -∞%         │
│    🔴 CRÍTICO → [📧 Urgencia a Municipalidad]         │
│                                                         │
│ 3. 🧬 VITAMINA A: Demanda 43 | Stock 28 → GAP -35%   │
│    🟡 MONITOREAR → [📋 Proyección 7 días]             │
│                                                         │
│ 📡 ACTIVIDAD EN VIVO:                                 │
│ • Operadores activos: 5/6 (83%)                      │
│ • Entregas últimas 2h: 8                             │
│ • Derivaciones a salud: 3 pendientes                 │
│                                                         │
│ 🗺️ COBERTURA:                                         │
│ • Centro: 68% | Guayabal: 60% | Corregimientos: 53% │
│                                                         │
│ [📤 Reportes] [🔔 Alertar] [⚙️ Configurar]            │
└─────────────────────────────────────────────────────────┘
```

### **6.3 Dashboard Médico (Validación)**

```
┌─────────────────────────────────────────────┐
│ 🩺 MÉDICO VALIDADOR — Protocolos            │
├─────────────────────────────────────────────┤
│                                             │
│ 📋 DERIVACIONES PENDIENTES (7):             │
│                                             │
│ ✳️ María García (Embarazada 7mo)            │
│   Síntomas: Edema, dolor pélvico           │
│   Acción: [✅ Validar] [❌ Rechazar]       │
│                                             │
│ ✳️ Sofia Pérez (7 años)                    │
│   Síntomas: Tos persistente, fiebre        │
│   Acción: [✅ Validar] [❌ Rechazar]       │
│                                             │
│ ✅ RECOMENDACIONES APROBADAS HOY (12):      │
│ • Protocolo Embarazo (67 familias)         │
│ • Protocolo Menores < 5 (89 familias)      │
│                                             │
│ 📊 PROTOCOLOS ACTIVOS (8):                 │
│ • WHO Embarazo, WHO Menores, ICBF Nutri... │
│ [⚙️ Crear Nuevo] [📋 Editar]               │
│                                             │
└─────────────────────────────────────────────┘
```

### **6.4 Dashboard Donante (Impacto)**

```
┌─────────────────────────────────────────────────┐
│ ❤️ TU IMPACTO — Donaciones Rolda               │
├─────────────────────────────────────────────────┤
│                                                 │
│ ✅ LECHE EN POLVO                              │
│ Donado: 20 bolsas (agosto 2026)                │
│ Usado en: 15 familias, 43 personas             │
│ Menores beneficiados: 12 (< 5 años)            │
│                                                 │
│ DESGLOSE:                                      │
│ • Familia García: 5 bolsas                     │
│   └─ Lucas (3 años) — Malnutrición mejorada   │
│                                                 │
│ • Familia Pérez: 8 bolsas                      │
│   └─ Sofia (7 años) + bebé (11 meses)         │
│                                                 │
│ ✅ VITAMINA A                                  │
│ Donado: 50 frascos                             │
│ Usado en: 45 menores                           │
│                                                 │
│ 📊 ESTADÍSTICAS:                               │
│ • Valor estimado: $250,000 COP                 │
│ • Familias alcanzadas: 23                      │
│ • Personas beneficiadas: 89                    │
│                                                 │
│ [❤️ Donar Más] [📸 Ver Fotos] [📊 Reportes]  │
│                                                 │
└─────────────────────────────────────────────────┘
```

### **6.5 Dashboard Municipalidad (Inteligencia)**

```
┌──────────────────────────────────────────────────┐
│ 🏛️ INTELIGENCIA MUNICIPAL — Roldanillo          │
├──────────────────────────────────────────────────┤
│                                                  │
│ 📊 VULNERABILIDAD ACUMULADA:                     │
│ Score promedio: 45/100 (Prioritario)             │
│ Tendencia: ↑ +3% en 7 días (empeorando)          │
│                                                  │
│ 🚨 ALERTAS PARA MUNICIPALIDAD:                   │
│ • 127 personas en situación CRÍTICA              │
│   Recomendación: Ampliar subsidios 20%           │
│                                                  │
│ • 67 menores < 5 sin leche                       │
│   Recomendación: Contactar ICBF                  │
│                                                  │
│ • 23 adultos mayores sin medicinas               │
│   Recomendación: Derivación a EPS                │
│                                                  │
│ 💰 PROYECCIÓN INVERSIÓN MENSUAL:                 │
│ • Leche: $500k (67 bolsas)                      │
│ • Medicinas crónicas: $400k                      │
│ • Vitaminas: $150k                              │
│ TOTAL: $1.05M/mes                               │
│                                                  │
│ 🤝 COORDINACIÓN:                                 │
│ • Municipalidad: 45% financiamiento             │
│ • Donantes privados: 30%                        │
│ • ONGs: 25%                                     │
│                                                  │
│ [📊 Exportar PDF] [🔔 Alertar Alcalde]          │
│                                                  │
└──────────────────────────────────────────────────┘
```

---

## **7. Sistema de Alertas**

### **7.1 Tipos de Alertas**

| Tipo | Trigger | Severidad | Roles | Canales |
|---|---|---|---|---|
| CRITICAL_SCORE_UPDATED | Score pasa a CRÍTICO | CRITICAL | Operador, Coordinador, Médico | Push, SMS, WhatsApp |
| STOCK_SHORTAGE | Stock < Demanda | CRITICAL | Coordinador, Donante, Municipal | SMS, Email |
| RECOMMENDATION_FULFILLED | Medicación entregada | LOW | Donante | Email |
| EXPIRY_SOON | Vence en < 7 días | MEDIUM | Coordinador, Warehouse | SMS, Email |
| FOLLOW_UP_NEEDED | Follow-up no realizado | HIGH | Operador, Coordinador | Push, SMS |
| REFERRAL_PENDING | Derivación no atendida 48h | HIGH | Coordinador, Doctor | SMS, Email |
| SYMPTOM_SEVERITY | Síntomas severos reportados | CRITICAL | Doctor, Coordinador | Email, SMS |

### **7.2 Flujo de Alerta**

```
EVENTO CRÍTICO DETECTADO
        │
        ▼
Alert::create([
    'beneficiary_id' => ...,
    'alert_type' => 'CRITICAL_SCORE_UPDATED',
    'severity' => 'CRITICAL',
    'recipients_roles' => ['OPERATOR', 'COORDINATOR', 'DOCTOR']
])
        │
        ▼
AlertService::dispatch(alert, roles)
        │
    ┌───┴────────┬──────────────┐
    │            │              │
    ▼            ▼              ▼
OPERATOR      COORDINATOR     DOCTOR
    │            │              │
    ├─ Push      ├─ Email       ├─ Email
    │ mobile     ├─ SMS         ├─ SMS
    │            ├─ Dashboard   └─ WhatsApp
    │            ├─ WhatsApp
    │            └─ Webhook
    │
    └─────────────┬────────────────┬─────────────────
                  │                │
            EN TIEMPO REAL     ALMACENADO EN DB
            (WebSocket)        (para historial)
```

---

## **8. Integración con Otros Módulos**

### **8.1 Módulo 1 (Búsqueda Pública) + Módulo 7**

**Búsqueda enriquecida:**

```sql
-- Cuando ciudadano busca "Leche en polvo"
SELECT master_items.*,
       SUM(stock_entries.quantity) as total_stock,
       COUNT(DISTINCT beneficiary_recommendations.beneficiary_id) 
           as beneficiaries_need,
       JSON_ARRAY_AGG(DISTINCT 
           JSON_OBJECT('type', 'Menores < 5', 'count', COUNT(*))
       ) as beneficiary_types
FROM master_items
LEFT JOIN stock_entries ON stock_entries.master_item_id = master_items.id
LEFT JOIN beneficiary_recommendations 
    ON beneficiary_recommendations.item_id = master_items.id
    AND beneficiary_recommendations.status = 'PENDING'
WHERE master_items.name LIKE '%Leche%'
GROUP BY master_items.id;

-- Resultado público (sin nombres):
{
    "item": "Leche en polvo",
    "total_stock": 19,
    "beneficiaries_need": 67,
    "urgency": "CRITICAL",
    "info": "67 menores < 5 años la necesitan"
}
```

### **8.2 Módulo 3 (Inventario) + Módulo 7**

**Alerta automática de brecha:**

```php
// StockEntry::updated()
protected static function booted()
{
    static::updated(function ($entry) {
        $demandCount = BeneficiaryRecommendation::where('item_id', $entry->master_item_id)
            ->where('status', 'PENDING')
            ->count();

        $totalStock = StockEntry::where('master_item_id', $entry->master_item_id)
            ->where('status', 'available')
            ->sum('quantity');

        if ($totalStock < $demandCount) {
            AlertService::dispatchAlert('STOCK_SHORTAGE', $entry->masterItem, [
                'demand' => $demandCount,
                'stock' => $totalStock,
                'gap' => $demandCount - $totalStock,
            ]);
        }
    });
}
```

### **8.3 Módulo 6 (Entregas) + Módulo 7**

**Actualización automática de recomendaciones:**

```php
// StockExitController@store
public function store(StockExitRequest $request)
{
    $exit = StockExit::create($request->validated());

    // 1. Crear care_history
    CareHistory::create([
        'beneficiary_id' => $request->beneficiary_id,
        'items_delivered' => json_encode($request->items),
        'delivery_date' => now(),
    ]);

    // 2. Marcar recomendaciones como FULFILLED
    foreach ($request->items as $item) {
        BeneficiaryRecommendation::where('beneficiary_id', $request->beneficiary_id)
            ->where('item_id', $item['id'])
            ->where('status', 'PENDING')
            ->update([
                'status' => 'FULFILLED',
                'fulfilled_at' => now(),
                'fulfillment_percentage' => 100,
            ]);
    }

    // 3. Alerta a donante (impacto)
    AlertService::dispatchAlert('RECOMMENDATION_FULFILLED', $beneficiary);
}
```

---

## **9. Código de Ejemplo**

### **9.1 ScoringEngine (Service)**

```php
<?php

namespace App\Services\VulnerabilityScoring;

use App\Models\Beneficiary;
use App\Models\VulnerabilityScore;

class ScoringEngine
{
    public function calculate(Beneficiary $beneficiary): array
    {
        $scores = [
            'demographic' => $this->calculateDemographic($beneficiary),
            'health' => $this->calculateHealth($beneficiary),
            'nutritional' => $this->calculateNutritional($beneficiary),
            'social' => $this->calculateSocial($beneficiary),
        ];

        $total = min(array_sum($scores), 100);

        return [
            'scores' => $scores,
            'total' => $total,
            'priority_level' => $this->determinePriority($total),
            'contributing_factors' => $this->getContributingFactors($beneficiary, $scores),
        ];
    }

    private function calculateDemographic(Beneficiary $b): int
    {
        $score = 0;
        $age = $b->getAge();

        if ($age < 5) {
            $score += 18;
        } elseif ($age >= 60) {
            $score += 12;
        } elseif ($age >= 5 && $age < 18) {
            $score += 8;
        }

        if ($b->is_pregnant) {
            $score += 12;
            if ($b->pregnancy_trimester >= 7) {
                $score += 8;
            }
        }

        return min($score, 30);
    }

    private function calculateHealth(Beneficiary $b): int
    {
        $score = 0;

        if ($b->chronic_conditions) {
            $conditions = json_decode($b->chronic_conditions, true);
            foreach ($conditions as $condition) {
                $score += in_array($condition, ['Diabetes', 'Asma', 'VIH']) ? 8 : 4;
            }
        }

        if ($b->current_symptoms) {
            $symptoms = json_decode($b->current_symptoms, true);
            $score += count($symptoms) * 2;
            if (array_intersect($symptoms, ['Fiebre alta', 'Dificultad respirar'])) {
                $score += 8;
            }
        }

        if (!$b->last_medical_review || $b->last_medical_review->diffInDays() > 90) {
            $score += 6;
        }

        return min($score, 30);
    }

    private function calculateNutritional(Beneficiary $b): int
    {
        $score = 0;
        
        if ($b->getAge() < 5) {
            $score += 10;
        }

        if ($b->medical_notes && str_contains($b->medical_notes, 'desnutrición')) {
            $score += 15;
        }

        if ($b->family && $b->family->children_count > 3) {
            $score += 5;
        }

        return min($score, 20);
    }

    private function calculateSocial(Beneficiary $b): int
    {
        $score = 0;

        if ($b->has_no_home) {
            $score += 20;
        }
        if ($b->is_single_parent) {
            $score += 8;
        }
        if ($b->employment_status === 'UNEMPLOYED') {
            $score += 6;
        }
        if ($b->has_disability) {
            $score += 7;
        }

        return min($score, 20);
    }

    private function determinePriority(int $total): string
    {
        if ($total >= 70) return 'CRITICAL';
        if ($total >= 40) return 'PRIORITY';
        return 'NORMAL';
    }

    private function getContributingFactors(Beneficiary $b, array $scores): array
    {
        $factors = [];
        
        if ($scores['demographic'] >= 15) {
            $factors[] = "Edad crítica o condición especial";
        }
        if ($scores['health'] >= 15) {
            $factors[] = "Problemas de salud reportados";
        }
        if ($scores['nutritional'] >= 10) {
            $factors[] = "Riesgo nutricional";
        }
        if ($scores['social'] >= 10) {
            $factors[] = "Vulnerabilidad socioeconómica";
        }

        return $factors;
    }
}
```

### **9.2 RecommendationService**

```php
<?php

namespace App\Services\RecommendationEngine;

use App\Models\Beneficiary;
use App\Models\BeneficiaryRecommendation;
use App\Models\ProtocolRecommendation;
use App\Models\StockEntry;

class RecommendationService
{
    public function generateRecommendations(Beneficiary $beneficiary): array
    {
        $recommendations = [];
        $protocols = $this->findApplicableProtocols($beneficiary);

        foreach ($protocols as $protocol) {
            $items = json_decode($protocol->recommended_items, true);
            
            foreach ($items as $item) {
                $availability = $this->checkAvailability($item['item_id']);

                $recommendation = BeneficiaryRecommendation::create([
                    'beneficiary_id' => $beneficiary->id,
                    'protocol_id' => $protocol->id,
                    'item_id' => $item['item_id'],
                    'quantity_recommended' => $item['quantity'] ?? 1,
                    'frequency' => $item['frequency'] ?? 'once',
                    'duration_days' => $item['duration_days'] ?? 30,
                    'available_stock' => $availability['total'],
                    'available_warehouses' => json_encode($availability['warehouses']),
                    'status' => 'PENDING',
                ]);

                $recommendations[] = $recommendation;
            }
        }

        return $recommendations;
    }

    private function findApplicableProtocols(Beneficiary $b): \Illuminate\Database\Eloquent\Collection
    {
        $age = $b->getAge();

        $query = ProtocolRecommendation::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            });

        $query->whereRaw("
            JSON_EXTRACT(trigger_condition, '$.age_min') IS NULL OR
            JSON_EXTRACT(trigger_condition, '$.age_min') <= ?
        ", [$age])
        ->whereRaw("
            JSON_EXTRACT(trigger_condition, '$.age_max') IS NULL OR
            JSON_EXTRACT(trigger_condition, '$.age_max') >= ?
        ", [$age]);

        if ($b->is_pregnant) {
            $query->orWhereRaw("JSON_EXTRACT(trigger_condition, '$.pregnancy') = true");
        }

        if ($b->chronic_conditions) {
            $conditions = json_decode($b->chronic_conditions, true);
            foreach ($conditions as $condition) {
                $query->orWhereRaw(
                    "JSON_CONTAINS(trigger_condition->'$.chronic_diseases', ?)",
                    [json_encode($condition)]
                );
            }
        }

        return $query->orderBy('confidence_level', 'DESC')->get();
    }

    private function checkAvailability(int $itemId): array
    {
        $entries = StockEntry::where('master_item_id', $itemId)
            ->where('status', 'available')
            ->with('warehouse')
            ->get();

        $total = $entries->sum('quantity');
        $warehouses = $entries->map(fn($e) => [
            'warehouse_id' => $e->warehouse_id,
            'warehouse_name' => $e->warehouse->name,
            'quantity' => $e->quantity,
            'distance_km' => $this->calculateDistance($e->warehouse),
        ])->sortBy('distance_km')->toArray();

        return ['total' => $total, 'warehouses' => $warehouses];
    }

    private function calculateDistance($warehouse): float
    {
        // Placeholder: implementar Haversine si hay geo data
        return 0;
    }
}
```

---

## **10. Plan de Implementación**

### **Timeline: Días 6-9 del MVP**

```
DAY 6 (Lunes - Setup + Data Models):
├─ Crear migraciones para 8 tablas nuevas           1.5h
├─ Models: Beneficiary, Score, Recommendation      1.5h
├─ ScoringEngine + tests unitarios                 2.5h
├─ RecommendationService                           2h
└─ Setup caché Redis                               1h
                                        Total: 8.5h

DAY 7 (Martes - Engines + UI):
├─ AlertService + dispatcher                       2h
├─ BeneficiaryController (CRUD + scoring)          2h
├─ Dashboard Operador (mobile-first)               3h
├─ Dashboard Coordinador (responsive)              2.5h
└─ Testing integrativo                             1.5h
                                        Total: 11h

DAY 8 (Miércoles - Multi-role):
├─ RoleMiddleware + authorization                  1.5h
├─ API endpoints (15+ nuevos)                      3h
├─ Dashboard Médico + Donante                      2.5h
├─ Health Referrals API                            1.5h
└─ Care History timeline UI                        1.5h
                                        Total: 10h

DAY 9 (Jueves - Polish + Deploy):
├─ Alert channels (SMS/Email/Push)                 2.5h
├─ Dashboard Municipalidad                         2h
├─ Stats cache + optimization                      1.5h
├─ QA testing completo                             2h
└─ DevOps deployment staging                       1h
                                        Total: 9h

TOTAL: 38.5h (parallelizable en 4 días)
```

---

## **✅ Checklist Final**

- [ ] Modelo de datos creado (8 tablas)
- [ ] ScoringEngine implementado y testeado
- [ ] RecommendationService funcionando
- [ ] Dashboards 5+ variantes (rol-based)
- [ ] AlertService multi-canal
- [ ] Integración con Módulos 1, 3, 6
- [ ] LSPP Privacy compliance verificado
- [ ] API endpoints documentados
- [ ] Tests unitarios (80%+ cobertura)
- [ ] Tests de integración completados
- [ ] Deploy staging exitoso
- [ ] Documentación de usuario (Video 2 min c/rol)

---

**Documento Completo:** Agosto 2026  
**Próximo Paso:** Revisar código de ejemplo + Crear diagramas ASCII de flujos detallados

