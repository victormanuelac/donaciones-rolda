# 📊 DIAGRAMAS DE FLUJO DETALLADOS — Donaciones Rolda

**Módulo 7 (Beneficiarios + Estadísticas) y Flujos de Integración**

**Versión:** 1.0  
**Formato:** ASCII Art (portable, versionable, documentable)  
**Total de Diagramas:** 8 principales + 5 detallados

---

## 📋 TABLA DE CONTENIDOS

1. [Flujo de Scoring de Vulnerabilidad](#1-flujo-de-scoring-de-vulnerabilidad) — Cálculo ponderado
2. [Flujo de Recomendación Automática](#2-flujo-de-recomendación-automática) — Protocolo matching
3. [Flujo de Alertas Automáticas](#3-flujo-de-alertas-automáticas) — Multi-rol, multi-canal
4. [Flujo de Derivación a Salud](#4-flujo-de-derivación-a-salud) — Integración puesto médico
5. [Flujo de Entrega y Actualización](#5-flujo-de-entrega-y-actualización) — M6 ↔ M7
6. [Flujo de Stock vs Demanda](#6-flujo-de-stock-vs-demanda) — M3 ↔ M7
7. [Flujo de Búsqueda Enriquecida](#7-flujo-de-búsqueda-enriquecida) — M1 ↔ M7
8. [Flujo End-to-End Completo](#8-flujo-end-to-end-completo) — De Censo a Entrega

---

---

## 1. FLUJO DE SCORING DE VULNERABILIDAD

**Objetivo:** Calcular score ponderado (0-100) para priorizar beneficiarios  
**Trigger:** Beneficiary creado o actualizado  
**Resultado:** Score + Priority Level + Contributing Factors

```
┌─────────────────────────────────────────────────────────────┐
│ INICIO: Beneficiary Registrado/Actualizado                 │
│ (Census Entry o User Update)                               │
└────────────────────┬────────────────────────────────────────┘
                     │
                     ▼
        ┌────────────────────────────┐
        │ EVENT: BeneficiaryUpdated   │
        │ (Laravel Model Observer)    │
        └────────┬───────────────────┘
                 │
                 ▼
    ┌──────────────────────────────────────┐
    │ ScoringEngine::calculate()            │
    │ (Service en app/Services/...)        │
    └────┬──────────────────────────────────┘
         │
    ┌────┴───────────────────────────────────────────────────────┐
    │ EXTRAER DATOS DEL BENEFICIARIO                             │
    │ ├─ date_of_birth → age (actual en años)                    │
    │ ├─ chronic_conditions (JSON array)                          │
    │ ├─ current_symptoms (JSON array)                            │
    │ ├─ is_pregnant + pregnancy_trimester                        │
    │ ├─ has_disability, is_single_parent, has_no_home           │
    │ ├─ employment_status, last_medical_review                   │
    │ └─ medical_notes (validación médica)                        │
    └────┬──────────────────────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────────────────────┐
    │ CALCULAR COMPONENTES PONDERADOS                            │
    └────┬──────────────────────────────────────────────────────┘
         │
    ┌────┴─────────────────────────────────────────────────────────────┐
    │                                                                   │
    ▼                    ▼                    ▼                    ▼    │
┌────────────┐  ┌────────────────┐  ┌───────────────┐  ┌──────────────┐
│ DEMOGRÁFICO│  │     SALUD      │  │  NUTRICIONAL  │  │    SOCIAL    │
│   (0-30)   │  │    (0-30)      │  │   (0-20)      │  │   (0-20)     │
└─────┬──────┘  └────────┬───────┘  └───────┬───────┘  └──────┬───────┘
      │                  │                  │                  │
      ├─ age < 5?        ├─ chronic?       ├─ age < 5?        ├─ no_home?
      │  +18 pts         │  +8 pts c/u     │  +10 pts         │  +20 pts
      │                  │                  │                  │
      ├─ age >= 60?      ├─ symptoms?      ├─ desnutrición?   ├─ single_parent?
      │  +12 pts         │  +2 pts c/u     │  +15 pts         │  +8 pts
      │                  │                  │                  │
      ├─ pregnant?       ├─ last_review    ├─ children_count? ├─ unemployed?
      │  +12 pts         │  > 90 días?     │  > 3: +5 pts     │  +6 pts
      │                  │  +6 pts         │                  │
      └─ trim >= 7?      └──────────────   └────────────────   ├─ disability?
         +8 pts                                                │  +7 pts
                                                               └────────


    ┌────────────────────────────────────────────────────────────────┐
    │ RESULTADOS PARCIALES:                                         │
    │ demographic_score = ? (0-30)                                  │
    │ health_score = ? (0-30)                                       │
    │ nutritional_score = ? (0-20)                                  │
    │ social_score = ? (0-20)                                       │
    └────┬───────────────────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────────────┐
    │ CALCULAR TOTAL:                             │
    │ total_score = SUM(todos) CAP(100)           │
    │                                             │
    │ Ejemplo:                                    │
    │ 20 + 20 + 15 + 14 = 69 → AJUSTAR A 87      │
    │ (porque embarazo crítico + anemia)         │
    └────┬────────────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────┐
    │ DETERMINAR PRIORITY_LEVEL:           │
    │                                      │
    │ IF score >= 70:                      │
    │   priority = "CRITICAL"  🔴          │
    │ ELSE IF score >= 40:                 │
    │   priority = "PRIORITY"  🟡          │
    │ ELSE:                                │
    │   priority = "NORMAL"    🟢          │
    └────┬───────────────────────────────┘
         │
         ▼
    ┌───────────────────────────────────────────────┐
    │ GENERAR CONTRIBUTING_FACTORS (JSON):          │
    │ ├─ "Edad crítica" (si demográfico >= 15)     │
    │ ├─ "Problemas de salud" (si salud >= 15)     │
    │ ├─ "Riesgo nutricional" (si nutricional >= 10)
    │ └─ "Vulnerabilidad social" (si social >= 10) │
    └────┬────────────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────────┐
    │ GUARDAR EN BASE DE DATOS:                │
    │                                          │
    │ 1. UPDATE beneficiaries SET:             │
    │    - vulnerability_score = total        │
    │    - priority_level = priority           │
    │    - last_score_update = NOW()           │
    │                                          │
    │ 2. INSERT vulnerability_scores:          │
    │    - demographic_score, health_score... │
    │    - contributing_factors (JSON)         │
    │    - calculated_at = NOW()              │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────────┐
    │ INVALIDAR CACHE EN REDIS:                │
    │ Redis::del('stats:global:v2')            │
    │ Redis::del('stats:family:ID')            │
    │ Redis::del('recommendations:ID')        │
    └────┬───────────────────────────────────┘
         │
         ▼ (¿Score cambió a CRÍTICO?)
         │
    ┌────┴──────────────┐
    │                   │
 SÍ │                   │ NO
    │                   │
    ▼                   ▼
┌──────────────┐   ┌──────────────────────┐
│ AlertService │   │ UpdateCache          │
│ dispatch()   │   │ (Redis)              │
│              │   │                      │
│ Notificar:   │   │ ✓ Hecho              │
│ - OPERATOR   │   └──────────┬───────────┘
│ - COORDINATOR│              │
│ - DOCTOR     │              │
└──────┬───────┘              │
       │                      │
       └──────────┬───────────┘
                  │
                  ▼
        ┌────────────────────────┐
        │ DASHBOARD ACTUALIZA    │
        │ (WebSocket en vivo)    │
        │                        │
        │ Operador ve: "🔴 +1"   │
        │ Coordinador ve: "⚠️    │
        │  Score actualizado"    │
        └────────────────────────┘
                  │
                  ▼
        ┌────────────────────────┐
        │ GENERAR RECOMENDACIONES│
        │ (Trigger automático)   │
        │ → Ver Flujo #2         │
        └────────────────────────┘
                  │
                  ▼
            ┌──────────────┐
            │ FIN: Score   │
            │ Actualizado  │
            └──────────────┘
```

**Duración esperada:** 50-100ms (síncrono)

**Puntos críticos:**
- ✅ Score es **IDEMPOTENTE** (igual input = igual output)
- ✅ Se calcula en **tiempo real** (no batch)
- ✅ Dispara alertas solo si **cambió a CRÍTICO**
- ✅ Caché se invalida automáticamente

---

---

## 2. FLUJO DE RECOMENDACIÓN AUTOMÁTICA

**Objetivo:** Generar recomendaciones personalizadas basadas en protocolos  
**Trigger:** Beneficiary score calculado (automático después de Flujo #1)  
**Resultado:** Beneficiary_recommendations (lista de medicinas)

```
┌──────────────────────────────────────────────────────────┐
│ ENTRADA: Beneficiary con Score Actualizado              │
│ (De: ScoringEngine::calculate())                         │
└────────────────┬─────────────────────────────────────────┘
                 │
                 ▼
    ┌────────────────────────────────────────┐
    │ RecommendationService::generate()      │
    │ (Service: app/Services/...)            │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────────────┐
    │ BUSCAR PROTOCOLOS APLICABLES:               │
    │                                             │
    │ SELECT * FROM protocol_recommendations     │
    │ WHERE                                       │
    │   is_active = TRUE                          │
    │   AND (valid_from IS NULL OR ...)          │
    │   AND (valid_until IS NULL OR ...)         │
    │   AND age_min <= beneficiary.age           │
    │   AND age_max >= beneficiary.age           │
    │   AND (pregnancy = FALSE OR                │
    │        beneficiary.is_pregnant = TRUE)    │
    │   AND (chronic IN beneficiary.chronic OR   │
    │        no chronic filter)                  │
    │   AND (symptoms IN beneficiary.symptoms OR │
    │        no symptom filter)                  │
    │                                             │
    │ ORDER BY confidence_level DESC             │
    └────┬────────────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────┐
    │ RESULTADO: Protocolos Aplicables       │
    │ (Ejemplo: María García, 34, embarazada)│
    │                                        │
    │ ✓ WHO Embarazo (confidence: 0.98)      │
    │ ✓ Local Health Anemia (confidence: 0.85)
    │ ✓ ICBF Nutrición (confidence: 0.90)    │
    │                                        │
    │ Ordena por confidence descendente      │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌───────────────────────────────────────────────────┐
    │ PARA CADA PROTOCOLO APLICABLE:                    │
    │                                                   │
    │ LOOP protocol IN protocols:                       │
    │   items = JSON_DECODE(protocol.recommended_items)│
    │                                                   │
    │   LOOP item IN items:                            │
    │     1. Obtener MasterItem de DB                  │
    │     2. Verificar disponibilidad en bodegas       │
    │     3. Crear BeneficiaryRecommendation           │
    │     4. Guardar en DB (status: PENDING)           │
    │                                                   │
    └────┬────────────────────────────────────────────┘
         │
         ▼  (Primero: WHO Embarazo)
    ┌─────────────────────────────────────────────────────┐
    │ EXTRAER ITEMS DEL PROTOCOLO:                        │
    │                                                     │
    │ {                                                   │
    │   "recommended_items": [                            │
    │     {"item_id": 15, "item_name": "Hierro",         │
    │      "quantity": 1, "frequency": "daily",          │
    │      "duration_days": 270},                        │
    │                                                     │
    │     {"item_id": 16, "item_name": "Ácido Fólico",   │
    │      "quantity": 1, "frequency": "daily",          │
    │      "duration_days": 270},                        │
    │                                                     │
    │     {"item_id": 17, "item_name": "Vitamina D",     │
    │      "quantity": 1, "frequency": "daily",          │
    │      "duration_days": 270}                         │
    │   ]                                                │
    │ }                                                   │
    └────┬────────────────────────────────────────────────┘
         │
    ┌────┴──────────────────────────────────────┐
    │ PARA CADA ITEM EN PROTOCOLO:              │
    │                                           │
    │ 1. Hierro                                 │
    │ 2. Ácido Fólico                           │
    │ 3. Vitamina D                             │
    └────┬──────────────────────────────────────┘
         │
         ▼ (Ej: Hierro)
    ┌──────────────────────────────────────────────────┐
    │ VERIFICAR DISPONIBILIDAD EN BODEGAS:             │
    │                                                  │
    │ SELECT stock_entries WITH warehouse             │
    │ WHERE master_item_id = 15                        │
    │   AND status = 'available'                       │
    │   AND quantity > 0                               │
    │                                                  │
    │ RESULTADO:                                       │
    │ Bodega Centro: 10 unidades, 0 km                 │
    │ Bodega Guayabal: 0 unidades, 8.5 km             │
    │ → Total disponible: 10                           │
    │ → Ordenar por distancia                          │
    └────┬───────────────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────────────┐
    │ CREAR RECOMMENDATION EN DB:                    │
    │                                                │
    │ INSERT INTO beneficiary_recommendations:       │
    │ {                                              │
    │   beneficiary_id: 1,                           │
    │   protocol_id: 5,  (WHO Embarazo)             │
    │   item_id: 15,     (Hierro)                    │
    │   quantity_recommended: 1,                     │
    │   frequency: "daily",                          │
    │   duration_days: 270,                          │
    │   status: "PENDING",                           │
    │   available_stock: 10,                         │
    │   available_warehouses: [{                     │
    │     warehouse_id: 1,                           │
    │     warehouse_name: "Centro",                  │
    │     quantity: 10,                              │
    │     distance_km: 0                             │
    │   }],                                          │
    │   recommended_at: NOW(),                       │
    │   recommended_by_user_id: 123                  │
    │ }                                              │
    │                                                │
    │ ✓ Guardado                                     │
    └────┬────────────────────────────────────────┘
         │
         ▼ (Siguiente: Ácido Fólico, Vitamina D)
    ├─ Repite para: Ácido Fólico
    │  ├─ disponible: 5 en Centro
    │  └─ ✓ Recomendación creada
    │
    └─ Repite para: Vitamina D
       ├─ disponible: 28 en Centro + 12 en Guayabal
       └─ ✓ Recomendación creada


    ┌────────────────────────────────────────────────┐
    │ SIGUIENTE PROTOCOLO: Local Health Anemia       │
    │ (confidence: 0.85)                             │
    │                                                │
    │ Items:                                         │
    │ ├─ Hierro (id: 15) — YA RECOMENDADO           │
    │ │  (Evitar duplicado)                          │
    │ └─ Vitamina B12 (id: 18)                       │
    │    ├─ disponible: 0 en stock                   │
    │    └─ ⚠️ CREAR CON available_stock = 0        │
    │       (Operador verá: "FALTA")                │
    │                                                │
    └────┬────────────────────────────────────────────┘
         │
         ▼
    ┌───────────────────────────────────────────────────┐
    │ SIGUIENTE PROTOCOLO: ICBF Nutrición              │
    │ (Porque María tiene hijo < 5)                    │
    │                                                   │
    │ Items:                                           │
    │ ├─ Leche en Polvo (id: 1)                       │
    │ │  ├─ disponible: 12 en Centro                  │
    │ │  └─ ✓ Recomendación creada                    │
    │ │                                                │
    │ ├─ Vitamina A (id: 8) — YA RECOMENDADO         │
    │ │  (Evitar duplicado)                           │
    │ │                                                │
    │ └─ Antiparasitarios (id: 9)                     │
    │    ├─ disponible: 1 en Centro                   │
    │    └─ ✓ Recomendación creada                   │
    │                                                   │
    └────┬────────────────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────────┐
    │ CONSOLIDAR RESULTADOS:                   │
    │                                          │
    │ Total recomendaciones creadas: 7        │
    │                                          │
    │ ✅ Hierro (95% confianza)               │
    │ ✅ Ácido Fólico (98% confianza)         │
    │ ✅ Vitamina D (95% confianza)           │
    │ ✅ Vitamina B12 (85%, FALTA stock)     │
    │ ✅ Leche en Polvo (90% confianza)       │
    │ ✅ Vitamina A (90%, ya recomendado)     │
    │ ✅ Antiparasitarios (85% confianza)     │
    │                                          │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌───────────────────────────────────────────┐
    │ INVALIDAR CACHE REDIS:                    │
    │ Redis::del('recommendations:beneficiary:1')
    └────┬────────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────────┐
    │ ACTUALIZAR UI EN TIEMPO REAL:            │
    │ (WebSocket)                              │
    │                                          │
    │ Dashboard Operador ve:                   │
    │ "María García [87/100 CRÍTICO]"         │
    │ ├─ ✅ Hierro (stock: 10)                │
    │ ├─ ✅ Ácido Fólico (stock: 5)           │
    │ ├─ ✅ Vitamina D (stock: 40)            │
    │ ├─ ❌ Vitamina B12 (stock: 0 — FALTA)   │
    │ ├─ ✅ Leche en Polvo (stock: 12)        │
    │ └─ ✅ Antiparasitarios (stock: 1)       │
    │                                          │
    │ [✅ Confirmar Entregas]                 │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────┐
    │ FIN: Recomendaciones Creadas            │
    │                                        │
    │ Next Step:                              │
    │ ├─ Operador ve ficha + recomendaciones │
    │ ├─ Escanea código bodega               │
    │ └─ Confirma entrega (→ Flujo #5)       │
    └────────────────────────────────────────┘
```

**Duración esperada:** 100-200ms (síncrono)

**Puntos críticos:**
- ✅ Detecta duplicados (no recomienda 2x Hierro)
- ✅ Muestra stock disponible en cada bodega
- ✅ Ordena bodegas por distancia (si hay geo data)
- ✅ Crea recomendación incluso si stock = 0 (para visibilidad de brecha)

---

---

## 3. FLUJO DE ALERTAS AUTOMÁTICAS

**Objetivo:** Notificar a roles correspondientes cuando hay evento crítico  
**Trigger:** 7 tipos de eventos (score crítico, stock shortage, etc)  
**Resultado:** Notificaciones multi-canal (SMS, Email, Push, WhatsApp, Dashboard)

```
┌──────────────────────────────────────────────┐
│ EVENTO CRÍTICO DETECTADO EN SISTEMA           │
└────────────────────┬───────────────────────────┘
                     │
    ┌────────────────┴────────────────┐
    │ 7 Tipos Posibles de Eventos:    │
    │                                 │
    │ 1. CRITICAL_SCORE_UPDATED       │
    │ 2. STOCK_SHORTAGE               │
    │ 3. RECOMMENDATION_FULFILLED     │
    │ 4. EXPIRY_SOON                  │
    │ 5. FOLLOW_UP_NEEDED             │
    │ 6. REFERRAL_PENDING             │
    │ 7. SYMPTOM_SEVERITY             │
    │                                 │
    └────────────────┬────────────────┘
                     │
                     ▼
    ┌──────────────────────────────────────────┐
    │ AlertService::dispatchAlert()            │
    │ (Service: app/Services/Alerts/)          │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────────────────┐
    │ CREAR REGISTRO DE ALERTA EN DB:                    │
    │                                                    │
    │ INSERT INTO alerts {                              │
    │   beneficiary_id: 1,                              │
    │   alert_type: "CRITICAL_SCORE_UPDATED",           │
    │   title: "María García — Vulnerabilidad Crítica"  │
    │   severity: "CRITICAL",                           │
    │   recipients_roles: ["OPERATOR", "COORDINATOR",   │
    │                      "DOCTOR"],                    │
    │   status: "ACTIVE",                               │
    │   created_at: NOW()                               │
    │ }                                                  │
    │                                                    │
    │ ✓ Guardado en DB (para auditoría histórica)       │
    └────┬─────────────────────────────────────────────┘
         │
         ▼
    ┌─────────────────────────────────────────┐
    │ DETERMINAR ROLES DESTINATARIOS:         │
    │                                         │
    │ IF alert_type = "CRITICAL_SCORE_..."   │
    │   recipients = [                        │
    │     "OPERATOR",                         │
    │     "COORDINATOR",                      │
    │     "HEALTH_PROVIDER"                   │
    │   ]                                     │
    │                                         │
    │ (Depende de alert_type)                 │
    └────┬────────────────────────────────────┘
         │
    ┌────┴─────────────────────────────────────────────────┐
    │                                                       │
    ▼                    ▼                    ▼            ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐  ┌─────────────┐
│  OPERATOR    │  │ COORDINATOR  │  │ DOCTOR       │  │ ...         │
│ (Si aplica)  │  │ (Si aplica)  │  │ (Si aplica)  │  │             │
└──────┬───────┘  └──────┬───────┘  └──────┬───────┘  └─────────────┘
       │                 │                 │
       ▼                 ▼                 ▼
    ┌──────────┐  ┌────────────┐  ┌────────────┐
    │  CANALES │  │  CANALES   │  │  CANALES   │
    └─────┬────┘  └─────┬──────┘  └─────┬──────┘
          │             │              │
    ┌─────┴─────┐      │         ┌──────┘
    │           │      │         │
 PUSH       SMS/WA  EMAIL    EMAIL
  Mobile  Urgentes  Semanal  Inmediato


    ┌─────────────────────────────────────────────────────┐
    │ CANAL 1: NOTIFICACIÓN PUSH (OPERATOR)                │
    ├─────────────────────────────────────────────────────┤
    │                                                     │
    │ IF severity IN ["CRITICAL", "HIGH"]:               │
    │   ENVIAR VÍA: Firebase Cloud Messaging (FCM)       │
    │   PAYLOAD:                                         │
    │   {                                                │
    │     title: "🔴 CRÍTICO: María García",             │
    │     body: "Embarazada + Anemia (Score: 87)",       │
    │     data: {                                        │
    │       beneficiary_id: 1,                           │
    │       action: "open_ficha",                        │
    │       icon: "critical_alert"                       │
    │     }                                              │
    │   }                                                │
    │                                                     │
    │   ✓ Entregado al operador                          │
    │   ✓ Sonido + vibración                             │
    │   ✓ Se abre ficha de beneficiario al tocar         │
    │                                                     │
    └────┬────────────────────────────────────────────────┘
         │
    ┌────▼────────────────────────────────────────────────┐
    │ CANAL 2: SMS + WHATSAPP (COORDINATOR)               │
    ├──────────────────────────────────────────────────────┤
    │                                                      │
    │ IF severity = "CRITICAL":                           │
    │   ENVIAR VÍA: Twilio (SMS) + Meta API (WhatsApp)    │
    │                                                      │
    │   SMS (160 chars máx):                              │
    │   "[CRÍTICO] María García: Embarazada + Anemia     │
    │    Score 87. Ver app: donaciones-rolda.com/...     │
    │    Acción: Derivar a médico"                        │
    │                                                      │
    │   WhatsApp (más flexible):                          │
    │   "🔴 CRÍTICO: María García (Familias)             │
    │    • Embarazada 7 meses                            │
    │    • Anemia (Score: 87/100)                        │
    │    • Recomendado: Hierro, Ácido Fólico            │
    │    • Acción: Derivar médico                        │
    │    Ver ficha: [Link a ficha]"                      │
    │                                                      │
    │   ✓ Entregado a Coordinador                        │
    │   ✓ Marcado como "crítico" en chat                 │
    │   ✓ Coordinador puede confirmar en WhatsApp        │
    │                                                      │
    └────┬───────────────────────────────────────────────┘
         │
    ┌────▼────────────────────────────────────────────────┐
    │ CANAL 3: EMAIL (DOCTOR)                             │
    ├──────────────────────────────────────────────────────┤
    │                                                      │
    │ IF alert_type = "CRITICAL_SCORE":                   │
    │   ENVIAR VÍA: Laravel Mail (SMTP)                   │
    │                                                      │
    │   FROM: noreply@donaciones-rolda.co                 │
    │   TO: doctor@puesto-salud.co                        │
    │   SUBJECT: "[CRÍTICO] Derivación: María García"    │
    │                                                      │
    │   BODY (HTML):                                      │
    │   {                                                 │
    │     greeting: "Dr. García",                         │
    │     message: "Se registró beneficiaria con          │
    │       vulnerabilidad crítica que requiere           │
    │       validación médica",                           │
    │     beneficiary: {                                  │
    │       name: "María García López",                   │
    │       age: 34,                                      │
    │       condition: "Embarazada 7 meses + Anemia",     │
    │       score: 87,                                    │
    │       symptoms: ["Edema", "Mareos", "Fatiga"]      │
    │     },                                              │
    │     recommendations: [                              │
    │       "Hierro (1 cap/día × 270 días)",              │
    │       "Ácido Fólico (1 comp/día × 270 días)",       │
    │       "Vitamina D (1 cap/día × 270 días)"           │
    │     ],                                              │
    │     action: "Validar en app (botón abajo)",         │
    │     link: "https://app.donaciones-rolda.co/..."     │
    │   }                                                 │
    │                                                      │
    │   ✓ Entregado al médico                            │
    │   ✓ Requiere validación en app                      │
    │                                                      │
    └────┬───────────────────────────────────────────────┘
         │
    ┌────▼────────────────────────────────────────────────┐
    │ CANAL 4: DASHBOARD EN VIVO (ALL ROLES)              │
    ├──────────────────────────────────────────────────────┤
    │                                                      │
    │ ACTUALIZAR VÍA: WebSocket (Laravel Reverb/Pusher)   │
    │                                                      │
    │ EVENTO TRANSMITIDO:                                 │
    │ {                                                   │
    │   channel: "alerts.coordinators",                   │
    │   event: "alert_created",                           │
    │   data: {                                           │
    │     alert_id: 123,                                  │
    │     type: "CRITICAL_SCORE_UPDATED",                 │
    │     beneficiary_name: "María García",               │
    │     severity: "CRITICAL",                           │
    │     timestamp: "2026-08-28 10:15:00"                │
    │   }                                                 │
    │ }                                                   │
    │                                                      │
    │ RESULTADO EN DASHBOARD COORDINADOR:                 │
    │ ┌─────────────────────────────────┐                │
    │ │ ⚠️ NUEVA ALERTA                  │                │
    │ │ 🔴 CRÍTICO: María García         │                │
    │ │ Embarazada + Anemia              │                │
    │ │ Score: 87/100                    │                │
    │ │ [Ver Ficha] [Derivar] [Cerrar]   │                │
    │ └─────────────────────────────────┘                │
    │                                                      │
    │ ✓ Actualizado en tiempo real                       │
    │ ✓ Puede marcar como "leído"                        │
    │                                                      │
    └────┬───────────────────────────────────────────────┘
         │
    ┌────▼────────────────────────────────────────────────┐
    │ CANAL 5: DIGEST SEMANAL (DONOR)                     │
    ├──────────────────────────────────────────────────────┤
    │                                                      │
    │ ENVIADO: Viernes 18:00 CO (via scheduled job)       │
    │                                                      │
    │ SUBJECT: "Tu Impacto Esta Semana - Donaciones Rolda"
    │                                                      │
    │ CONTENIDO:                                          │
    │ {                                                   │
    │   title: "Impacto de Tu Donación",                  │
    │   stats: {                                          │
    │     families_helped: 12,                            │
    │     people_reached: 34,                             │
    │     children_under_5: 8                             │
    │   },                                                │
    │   donations: [                                      │
    │     {                                               │
    │       item: "Leche en Polvo",                       │
    │       qty_donated: 20,                              │
    │       qty_delivered: 15,                            │
    │       families_helped: 6,                           │
    │       children: ["Lucas (3)", "Sofia (2)", ...]     │
    │     }                                               │
    │   ],                                                │
    │   impact: "Tu donación llegó a 34 personas.",       │
    │   next_need: "Hierro (Embarazadas: 34 demandan)"    │
    │ }                                                   │
    │                                                      │
    │ ✓ Entregado a donante                              │
    │ ✓ Motiva a seguir donando                          │
    │                                                      │
    └────┬───────────────────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────────┐
    │ ACTUALIZAR ESTADO DE ALERTA:             │
    │                                          │
    │ UPDATE alerts SET                        │
    │   status = "ACTIVE",                     │
    │   created_at = NOW(),                    │
    │   delivery_status = {                    │
    │     push: "delivered",                   │
    │     sms: "delivered",                    │
    │     email: "delivered",                  │
    │     whatsapp: "delivered",               │
    │     dashboard: "live"                    │
    │   }                                      │
    │                                          │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────┐
    │ COORDINADOR ACKNOWLEDGES EN APP:    │
    │                                    │
    │ PATCH /api/alerts/123/acknowledge  │
    │                                    │
    │ UPDATE alerts SET                  │
    │   status = "ACKNOWLEDGED",         │
    │   acknowledged_by: coordinator_id, │
    │   acknowledged_at: NOW()           │
    │                                    │
    │ ✓ Alerta marcada como "leída"      │
    │ ✓ Ya no aparece en toast           │
    │                                    │
    └────┬───────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────┐
    │ COORDINATOR RESUELVE (tras derivar)  │
    │                                      │
    │ PATCH /api/alerts/123/resolve        │
    │ {                                    │
    │   resolution: "Derivada a médico",   │
    │   notes: "Dr. García confirmó..."    │
    │ }                                    │
    │                                      │
    │ UPDATE alerts SET                    │
    │   status = "RESOLVED",               │
    │   resolved_at: NOW(),                │
    │   resolution_notes: "..."            │
    │                                      │
    │ ✓ Alerta cerrada                     │
    │ ✓ En histórico para auditoría        │
    │                                      │
    └──────────────────────────────────────┘
```

**Duración:** Inmediato (< 200ms para todos los canales)

**Puntos críticos:**
- ✅ Multi-rol: cada rol recibe lo que necesita
- ✅ Multi-canal: SMS (urgentes), Email (planificado), Push (móvil), Dashboard (vivo)
- ✅ Escalabilidad: usa queues (Redis) para no bloquear
- ✅ Auditoría: cada alerta guardada en DB (quién la vio, cuándo)

---

---

## 4. FLUJO DE DERIVACIÓN A SALUD

**Objetivo:** Canalizar beneficiarios que necesitan atención médica profesional  
**Trigger:** Síntomas severos reportados o recomendación médica  
**Resultado:** Health_referral creada, médico notificado, follow-up tracked

```
┌──────────────────────────────────────────┐
│ OPERADOR ABRE FICHA EN APP                │
│ (Field operator con Beneficiario)         │
└─────────────────┬────────────────────────┘
                  │
                  ▼
        ┌─────────────────────────────┐
        │ VE SÍNTOMAS CRÍTICOS:       │
        │ • Fiebre alta (> 38.5°C)    │
        │ • Disnea (dificultad resp)  │
        │ • Edema severo              │
        │ • Sangrado                  │
        │                             │
        │ O recomendación médica      │
        │ O socio dice: "Necesita Dr" │
        └─────────────────┬───────────┘
                          │
                          ▼
            ┌──────────────────────────┐
            │ [🩺 Derivar a Médico]    │
            │ (Botón en ficha)         │
            └──────────────┬───────────┘
                           │
                           ▼
        ┌────────────────────────────────────────┐
        │ MODAL: DERIVACIÓN A SALUD              │
        │                                        │
        │ ┌────────────────────────────────────┐ │
        │ │ Seleccionar Puesto de Salud:       │ │
        │ │                                    │ │
        │ │ (○) Centro de Salud Roldanillo    │ │
        │ │ (●) Puesto Guayabal (seleccionado)│ │
        │ │ (○) Hospital San Rafael            │ │
        │ │                                    │ │
        │ └────────────────────────────────────┘ │
        │                                        │
        │ Urgencia:                             │
        │ (●) URGENTE ← Seleccionado            │
        │ (○) Rutina                            │
        │                                        │
        │ Síntomas reportados:                  │
        │ ☑ Fiebre | ☑ Edema | ☑ Dolor pélvico│
        │                                        │
        │ Notas adicionales:                    │
        │ [Embarazada 7 meses, con anemia]     │
        │                                        │
        │ [Cancelar] [Derivar]                 │
        └────────────┬───────────────────────────┘
                     │
                     ▼ (Operador presiona: [Derivar])
        ┌────────────────────────────────────────┐
        │ CREAR HEALTH_REFERRAL EN DB:           │
        │                                        │
        │ INSERT INTO health_referrals {         │
        │   beneficiary_id: 1,                   │
        │   referred_by_user_id: 45,  (Operator)│
        │   reason: "Embarazo alto riesgo",    │
        │   reported_symptoms: [                │
        │     "Fiebre 38.5°C",                  │
        │     "Edema en piernas",               │
        │     "Dolor pélvico"                   │
        │   ],                                   │
        │   urgency: "URGENT",                  │
        │   health_facility_id: 3,              │
        │   health_facility_name: "Puesto Gua",│
        │   health_facility_phone: "+57 ...",   │
        │   status: "PENDING",                  │
        │   referred_at: NOW()                  │
        │ }                                      │
        │                                        │
        │ ✓ Guardado en DB (Auditoría LSPP)    │
        └────┬───────────────────────────────────┘
             │
             ▼
    ┌───────────────────────────────────────┐
    │ NOTIFICAR AL PUESTO DE SALUD:         │
    │                                       │
    │ Opción 1: WhatsApp                    │
    │ ───────────                           │
    │ "Derivación urgente:                  │
    │ María García, 34, embarazada 7m       │
    │ Síntomas: Fiebre 38.5, edema          │
    │ Teléfono: +57 312 XXX                 │
    │ Coordinador: Juan Pérez"              │
    │                                       │
    │ Opción 2: SMS (si no WhatsApp)        │
    │ ──────────────────────────────        │
    │ "[DERIVACIÓN URGENTE]                 │
    │ María García, embarazada              │
    │ Síntomas: Fiebre + edema              │
    │ Contact: Juan Pérez +57 312 XYZ"      │
    │                                       │
    │ Opción 3: Email                       │
    │ ──────────                            │
    │ (Si mail del puesto disponible)       │
    │                                       │
    │ ✓ Puesto de salud notificado          │
    │ ✓ Puede confirmar lectura en app      │
    │ ✓ Puede actualizar estado             │
    └────┬────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────┐
    │ ALERT AUTOMÁTICA DISPARADA:          │
    │ (Sistema dispara alerta)              │
    │                                       │
    │ AlertService::dispatch(                │
    │   type: "REFERRAL_PENDING",           │
    │   beneficiary: María,                 │
    │   roles: ["COORDINATOR", "DOCTOR"]    │
    │ )                                     │
    │                                       │
    │ NOTIFICACIONES:                       │
    │ ├─ Coordinador: SMS (urgente)        │
    │ │  "Derivación enviada a Puesto Gua" │
    │ │                                     │
    │ └─ Médico: Email (automático)        │
    │    "Nueva derivación: María García"   │
    │                                       │
    │ ✓ Stakeholders notificados            │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────┐
    │ OPERADOR VE CONFIRMACIÓN:            │
    │                                      │
    │ ✓ "Derivación enviada"              │
    │ ✓ "Puesto Guayabal ha sido notificado"
    │ ✓ "María García será contactada"     │
    │ ✓ "Seguimiento: [pendiente]"         │
    │                                      │
    │ [Volver a Ficha] [Ver Historial]     │
    └────┬───────────────────────────────────┘
         │
    ┌────┴────────────────────────────┐
    │ 48 HORAS DESPUÉS: SEGUIMIENTO    │
    │                                  │
    │ Sistema verifica: ¿Status cambió?│
    └────┬─────────────────────────────┘
         │
    ┌────┴─────────────────────────────────────┐
    │                                          │
    │ CASO 1: Médico ATENDIÓ                   │
    ▼                                          ▼
┌──────────────────────────┐  ┌─────────────────────────┐
│ Puesto actualiza en app: │  │ Coordinador actualiza:  │
│                          │  │                         │
│ PATCH /health-referrals  │  │ PATCH /health-referrals │
│ {                        │  │ {                       │
│   status: "ATTENDED",    │  │   status: "ATTENDED",   │
│   attended_at: NOW(),    │  │   attended_at: NOW(),   │
│   diagnosis: "ITU",      │  │   diagnosis: "",        │
│   treatment: {           │  │   treatment: {}         │
│     drug: "Amoxicilina", │  │ }                       │
│     dose: "500mg",       │  │                         │
│     duration: "7 días"   │  │ ✓ Derivación resuelta   │
│   }                      │  └─────────────────────────┘
│ }                        │
│                          │
│ ✓ Status cambia         │
│ ✓ Diagnosis registrado   │
│ ✓ Treatment grabado      │
│ ✓ AlertaRESUELTA auto.   │
│                          │
│ Coordinator recibe:      │
│ "[RESUELTO] María García │
│  Atendida en Puesto Gua  │
│  Dx: ITU, Tx: Amoxicilina│
│  [Ver diagnóstico]"      │
└──────────────────────────┘


    │
    │ CASO 2: NO FUE ATENDIDA (48h después)
    │
    ▼
┌──────────────────────────────────────┐
│ ALERT AUTOMÁTICA DISPARADA:          │
│ (Scheduled job verifica cada 12h)    │
│                                      │
│ IF referral.status = "PENDING"       │
│   AND referral.created_at < NOW()-48h│
│                                      │
│ ENTONCES:                            │
│ AlertService::dispatch(              │
│   type: "REFERRAL_PENDING_TIMEOUT",  │
│   severity: "HIGH",                  │
│   roles: ["COORDINATOR", "DOCTOR"]   │
│ )                                    │
│                                      │
│ NOTIFICACIÓN:                        │
│ ⚠️ "PENDIENTE: María García          │
│    Derivada hace 48h                 │
│    ¿Atendida? ¿Cancelada?            │
│    [Actualizar Estado]"              │
│                                      │
│ SMS a Coordinador:                   │
│ "Derivación María García             │
│  aún PENDIENTE 48h                   │
│  Contactar Puesto Salud"             │
│                                      │
│ ✓ Coordinador toma acción            │
│ ✓ Escalation automático              │
└──────────────────────────────────────┘
```

**Duración:** Inmediato (creación) + 48h (follow-up)

**Puntos críticos:**
- ✅ Crea audit trail (quién derivó, cuándo, por qué)
- ✅ Médico puede confirmar en app (no vía SMS solamente)
- ✅ Auto-follow-up en 48h si no hay respuesta
- ✅ Escalation automática si timeout

---

(Continuando con los flujos restantes...)

---

## 5. FLUJO DE ENTREGA Y ACTUALIZACIÓN

**Objetivo:** Registrar entrega de medicinas/elementos, actualizar recomendaciones  
**Trigger:** Operador confirma entrega en M6  
**Resultado:** Care_history creada, recomendaciones marcadas FULFILLED, alerta a donante

```
┌──────────────────────────────────────┐
│ OPERADOR EN CAMPO (PWA):             │
│ "Visité a María García"              │
│                                      │
│ Ficha abierta: María García          │
│ Recomendaciones mostradas:           │
│ ├─ Hierro (stock: 10 en Centro)      │
│ ├─ Ácido Fólico (stock: 5)           │
│ ├─ Leche (stock: 12)                 │
│ └─ Antiparasitarios (stock: 1)       │
│                                      │
│ [✅ Confirmar Entregas]              │
└────────────┬─────────────────────────┘
             │
             ▼
    ┌───────────────────────────────┐
    │ MODAL: CONFIRMAR ENTREGA      │
    │                               │
    │ ☑ Hierro (1 cápsula)          │
    │ ☑ Ácido Fólico (1 comprimido) │
    │ ☑ Leche (2 bolsas)            │
    │ ☐ Antiparasitarios            │
    │                               │
    │ Bodega origen:                │
    │ [Centro] [Guayabal] [Local]   │
    │                               │
    │ Beneficiario recibió:         │
    │ (○) Sí / (●) No               │
    │                               │
    │ Foto comprobante:             │
    │ [📷 Tomar Foto]  [✓ OK]       │
    │                               │
    │ [Cancelar] [Guardar]          │
    └─────────────┬──────────────────┘
                  │
                  ▼
    ┌──────────────────────────────────────┐
    │ GUARDAR EN IndexedDB (OFFLINE):      │
    │                                      │
    │ {                                    │
    │   uuid: "abc123",                    │
    │   action: "CREATE",                  │
    │   table: "stock_exits",              │
    │   data: {                            │
    │     warehouse_id: 1,                 │
    │     beneficiary_id: 1,               │
    │     items: [                         │
    │       {item_id: 15, qty: 1},         │
    │       {item_id: 16, qty: 1},         │
    │       {item_id: 1, qty: 2}           │
    │     ],                               │
    │     released_by_user_id: 45,         │
    │     release_date: NOW(),             │
    │     photo_path: "photo_123.jpg"      │
    │   },                                 │
    │   status: "pending_sync"             │
    │ }                                    │
    │                                      │
    │ ✓ Guardado en IndexedDB (local)     │
    │ ✓ UI muestra: "⏳ Sincronizando..."   │
    └────┬──────────────────────────────────┘
         │
         ▼ (Cuando vuelve a conexión)
    ┌──────────────────────────────────────┐
    │ SYNC: Enviar a Backend (Laravel)     │
    │                                      │
    │ POST /api/stock-exits               │
    │ {                                    │
    │   warehouse_id: 1,                   │
    │   beneficiary_id: 1,                 │
    │   items: [...],                      │
    │   photo: [Base64],                   │
    │   release_date: "2026-08-28 14:30"   │
    │ }                                    │
    │                                      │
    │ ✓ Entregado a servidor               │
    │ ✓ IndexedDB marca como "synced"      │
    └────┬──────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────┐
    │ BACKEND: Procesar Entrega             │
    │                                      │
    │ StockExitController::store()          │
    │ ├─ 1. Validar datos (FormRequest)    │
    │ ├─ 2. Crear StockExit record         │
    │ └─ 3. Llamar: UpdateRecommendations()│
    └────┬──────────────────────────────────┘
         │
    ┌────┴─────────────────────────────────────────┐
    │                                              │
    ▼                                              ▼
┌──────────────────────────────────┐  ┌─────────────────────────────────┐
│ Crear StockExit:                 │  │ UpdateRecommendations Service:  │
│                                  │  │                                 │
│ INSERT stock_exits {             │  │ Para cada item entregado:       │
│   warehouse_id: 1,               │  │                                 │
│   item_id: 15,  (Hierro)         │  │ 1. Buscar recomendaciones      │
│   quantity: 1,                   │  │    WHERE                        │
│   beneficiary_id: 1,             │  │    beneficiary_id = 1           │
│   release_date: NOW(),           │  │    AND item_id = 15             │
│   released_by_user_id: 45,       │  │    AND status = "PENDING"       │
│   notes: "..."                   │  │                                 │
│ }                                │  │ 2. UPDATE beneficiary_          │
│                                  │  │    recommendations SET          │
│ ✓ Stock disminuido               │  │    status = "FULFILLED",        │
│ ✓ Audit trail creado             │  │    fulfilled_at = NOW(),        │
│                                  │  │    fulfillment_percentage = 100 │
│                                  │  │                                 │
│                                  │  │ 3. Crear CareHistory:          │
│                                  │  │    INSERT care_history {       │
│                                  │  │      beneficiary_id: 1,        │
│                                  │  │      items_delivered: [...],   │
│                                  │  │      delivered_by: 45,         │
│                                  │  │      delivery_date: NOW()      │
│                                  │  │    }                           │
│                                  │  │                                 │
│                                  │  │ ✓ Recomendaciones marcadas    │
│                                  │  │ ✓ Historial de entrega creado  │
└──────────────────────────────────┘  └─────────────────────────────────┘
         │                                  │
         └──────────────┬───────────────────┘
                        │
                        ▼
            ┌───────────────────────────────────┐
            │ INVALIDAR CACHE:                  │
            │                                  │
            │ Redis::del(                       │
            │   "recommendations:beneficiary:1" │
            │ )                                 │
            │ Redis::del(                       │
            │   "stats:global:v2"               │
            │ )                                 │
            │                                  │
            │ ✓ Cache fresco para próximas QA   │
            └───────────┬────────────────────────┘
                        │
                        ▼
            ┌───────────────────────────────────┐
            │ ALERTA A DONANTE:                 │
            │                                  │
            │ AlertService::dispatch(           │
            │   type: "RECOMMENDATION_FULFILLED",
            │   beneficiary: María,             │
            │   item: "Hierro",                 │
            │   quantity: 1,                    │
            │   roles: ["DONOR"]                │
            │ )                                 │
            │                                  │
            │ EMAIL AL DONANTE:                │
            │ SUBJECT: "Tu Donación Llegó"     │
            │                                  │
            │ "Hola Donante,                   │
            │                                  │
            │  Tu donación de Hierro llegó a:  │
            │  • María García (embarazada)     │
            │  • Cantidad: 1 cápsula           │
            │  • Fecha: 28 ago 2026 14:30      │
            │  • Operador: Juan Pérez          │
            │                                  │
            │  Esta medicación es crítica para │
            │  prevenir anemia en embarazo.    │
            │                                  │
            │  Gracias por tu generosidad ❤️   │
            │                                  │
            │  [Ver Más Impacto]"              │
            │                                  │
            │ ✓ Donante se siente conectado    │
            │ ✓ Motiva a seguir donando        │
            └───────────┬────────────────────────┘
                        │
                        ▼
            ┌───────────────────────────────────┐
            │ OPERADOR VE CONFIRMACIÓN:         │
            │                                  │
            │ ✓ Entrega guardada                │
            │ ✓ Recomendaciones actualizadas    │
            │ ✓ "Donante notificado"           │
            │ ✓ Fotos: 1/1                      │
            │                                  │
            │ VISTA ACTUALIZADA:                │
            │ María García                      │
            │ ├─ ✅ Hierro (Entregado 14:30)   │
            │ ├─ ✅ Ácido Fólico (Entregado)   │
            │ ├─ ✅ Leche (Entregado)          │
            │ └─ ⏳ Antiparasitarios (Pendiente)│
            │                                  │
            │ [Confirmar Siguiente]            │
            │ [Marcar Seguimiento]             │
            │ [Ver Historial]                  │
            │                                  │
            └───────────────────────────────────┘
```

**Duración:** < 500ms (síncrono) + async para notificaciones

**Puntos críticos:**
- ✅ Funciona offline (sync cuando regresa conexión)
- ✅ Auto-marca recomendaciones como FULFILLED
- ✅ Crea audit trail (care_history)
- ✅ Notifica donante (impacto real)

---

## 6. FLUJO DE STOCK VS DEMANDA

**Objetivo:** Detectar y alertar cuando stock < demanda  
**Trigger:** Stock actualizado (M3) o Recomendación creada (M7)  
**Resultado:** Alert de brecha, notificación Coordinador + Donantes

```
┌─────────────────────────────────────────────────┐
│ ESCENARIO: Stock de Leche disminuye a 5 unidades│
│ (Después de entrega)                            │
└──────────────────┬────────────────────────────────┘
                   │
                   ▼
        ┌──────────────────────────────┐
        │ StockEntry::updated()        │
        │ (Laravel Model Observer)     │
        └────┬────────────────────────┘
             │
             ▼
    ┌──────────────────────────────────────────────┐
    │ CALCULAR DEMANDA:                            │
    │                                              │
    │ SELECT COUNT(*)                              │
    │ FROM beneficiary_recommendations             │
    │ WHERE item_id = 1  (Leche)                   │
    │   AND status IN ("PENDING", "IN_PROGRESS")   │
    │                                              │
    │ RESULTADO: 67 familias necesitan leche       │
    └────┬───────────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────────────┐
    │ COMPARAR: Stock vs Demanda                   │
    │                                              │
    │ Leche:                                       │
    │ ├─ Demanda: 67 familias                      │
    │ ├─ Stock actual: 5 unidades                  │
    │ ├─ Brecha: 67 - 5 = 62 familias              │
    │ ├─ Porcentaje: -92.5%  🔴 CRÍTICO            │
    │                                              │
    │ IF stock < demand:                           │
    │   severity = "CRITICAL"                      │
    │   action = "Alertar de inmediato"            │
    └────┬───────────────────────────────────────┘
         │
         ▼
    ┌──────────────────────────────────────────────┐
    │ CREAR ALERT AUTOMÁTICO:                      │
    │                                              │
    │ AlertService::dispatch(                      │
    │   type: "STOCK_SHORTAGE",                    │
    │   severity: "CRITICAL",                      │
    │   item_id: 1,                                │
    │   item_name: "Leche en Polvo",               │
    │   demand: 67,                                │
    │   stock: 5,                                  │
    │   gap: 62,                                   │
    │   gap_percentage: -92.5,                     │
    │   roles: ["COORDINATOR", "DONOR", "MUNICIPAL"]
    │ )                                            │
    └────┬──────────────────────────────────────┘
         │
    ┌────┴────────────────────────────────────────────────┐
    │                                                     │
    ▼                        ▼                    ▼       │
┌────────────────┐  ┌──────────────────┐  ┌────────────────┐
│ COORDINATOR    │  │ DONOR            │  │ MUNICIPAL      │
│ (SMS urgente)  │  │ (Email)          │  │ (Reporte)      │
└────────┬───────┘  └────────┬─────────┘  └────────┬───────┘
         │                  │                     │
         ▼                  ▼                     ▼

SMS (INMEDIATO):          EMAIL (SEMANAL):         REPORTE:
"🔴 CRÍTICO               "🔴 URGENCIA:             "Brecha de Stock
Leche EN STOCK:          Leche en Polvo            Leche en Polvo:
5 unidades               Demanda: 67 fam           • Demanda: 67
Demanda: 67 fam          Stock: 5 (BRECHA: -92%)  • Stock: 5
BRECHA: -62              • 62 familias sin         • Gap: -92%
                         • Necesitamos urgente     • Recomendación:
Acción:                  • ¿Puedes donar más?      Contactar Banco
[Ver Dashboard]          [Donar Ahora] [↗ Más]    de Alimentos"


         │                  │                     │
         └──────────────────┼─────────────────────┘
                            │
                            ▼
            ┌──────────────────────────────────┐
            │ DASHBOARD COORDINADOR ACTUALIZA  │
            │ (WebSocket)                      │
            │                                  │
            │ ⚠️ NUEVO STOCK GAP:              │
            │ 🔴 Leche: -92% (62 fam sin)     │
            │                                  │
            │ Acciones rápidas:                │
            │ [📧 Alertar Donantes]           │
            │ [📞 Llamar ONG]                 │
            │ [🏛️ Contactar Municipalidad]   │
            │                                  │
            │ [✓ Resuelto] [📝 Nota]          │
            │                                  │
            └────────────────────────────────────┘


    ┌─────────────────────────────────────────────┐
    │ COORDINADOR PRESIONA: [📧 Alertar Donantes] │
    └──────────────┬────────────────────────────┘
                   │
                   ▼
    ┌──────────────────────────────────────────────┐
    │ EMAIL EN CADENA A TODOS LOS DONANTES:        │
    │                                              │
    │ FROM: coordinador@donaciones-rolda.co        │
    │ TO: donantes@lista.donaciones-rolda.co       │
    │ SUBJECT: "🔴 URGENCIA: Leche en Polvo"       │
    │                                              │
    │ CONTENIDO:                                   │
    │ "¿Sabías que 62 familias están sin leche     │
    │  en Roldanillo?                              │
    │                                              │
    │  Necesitamos URGENTE:                        │
    │  • 62 bolsas de leche en polvo               │
    │  • O contribución económica                  │
    │                                              │
    │  Esto impactará: 89 menores < 5 años        │
    │                                              │
    │  Tu donación es CRÍTICA.                     │
    │                                              │
    │  [Donar Leche Ahora] [Donar $50k]           │
    │  [Ver Familias Impactadas]"                 │
    │                                              │
    │ ✓ Enviado a 234 donantes registrados        │
    │ ✓ Tasa de apertura: 45% (típico)            │
    │ ✓ Esperado: 3-5 donaciones en 24h            │
    │                                              │
    └────────────────────────────────────────────────┘
```

**Duración:** < 100ms (detección) + notificaciones asincrónicas

**Puntos críticos:**
- ✅ Detección automática (no manual)
- ✅ Escalable (funciona con cualquier item)
- ✅ Multi-nivel (Coordinador, Donante, Municipalidad)
- ✅ Motiva acción rápida (brecha visible)

---

## 7. FLUJO DE BÚSQUEDA ENRIQUECIDA

**Objetivo:** Mostrar demanda en búsqueda pública (sin nombres de beneficiarios)  
**Trigger:** Ciudadano busca un insumo en Módulo 1  
**Resultado:** Resultados enriquecidos con info de recomendaciones

```
┌──────────────────────────────────────────────┐
│ CIUDADANO EN BÚSQUEDA PÚBLICA:               │
│ "¿Hay leche en polvo disponible?"            │
└────────────┬─────────────────────────────────┘
             │
             ▼
    ┌──────────────────────────────────────────┐
    │ SearchController::public()                │
    │ (Módulo 1 - Búsqueda)                    │
    │                                          │
    │ SELECT master_items WHERE                │
    │   name LIKE '%Leche%'                    │
    │   status = 'approved'                    │
    └────┬───────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────────────┐
    │ PARA CADA ITEM ENCONTRADO:                     │
    │ Enriquecer con datos de Módulo 7               │
    │                                                │
    │ 1. Stock total:                                │
    │    SELECT SUM(quantity)                        │
    │    FROM stock_entries                          │
    │    WHERE item_id = 1, status = 'available'     │
    │    → 19 unidades                               │
    │                                                │
    │ 2. Demanda agregada (ANÓNIMA):                 │
    │    SELECT COUNT(*)                             │
    │    FROM beneficiary_recommendations            │
    │    WHERE item_id = 1, status = 'PENDING'       │
    │    → 67 beneficiarios necesitan                │
    │                                                │
    │ 3. Tipos de beneficiarios (AGREGADO):          │
    │    SELECT                                      │
    │      IF(beneficiary.age < 5, 'Menores < 5',...)│
    │      COUNT(*) as count                         │
    │    → Menores: 45 | Embarazadas: 8 | Adultos: 14
    │                                                │
    │ 4. Urgencia (basada en brecha):                │
    │    gap = 67 - 19 = -48 unidades               │
    │    gap% = -71%  → 🔴 CRÍTICO                   │
    └────┬──────────────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────────┐
    │ RESULTADO MOSTRADO AL CIUDADANO:           │
    │                                            │
    │ 🍼 Leche en Polvo                          │
    │ 🟢 Stock: 19 unidades (información pública)│
    │                                            │
    │ ℹ️ Información de Impacto:                 │
    │    (Agregada, sin nombres personales)      │
    │    "67 beneficiarios están esperando       │
    │     esta leche en polvo"                   │
    │                                            │
    │    Desglose por grupo:                     │
    │    • 45 menores menores que 5 años         │
    │    • 8 madres embarazadas                  │
    │    • 14 adultos en situación vulnerable    │
    │                                            │
    │ 🎯 Urgencia: 🔴 CRÍTICO                    │
    │    (Falta para 48 beneficiarios)           │
    │                                            │
    │ 📍 Ubicación: Bodega Centro (2.3 km)       │
    │ 📍 Ubicación: Bodega Guayabal (8.5 km)    │
    │                                            │
    │ [📍 Ver en Mapa] [💬 Contactar]            │
    │ [❤️ Donar Más] [ℹ️ Información]            │
    │                                            │
    └────┬───────────────────────────────────────┘
         │
         ▼
    ┌────────────────────────────────────────────┐
    │ PRIVACY CHECK (LSPP Ley 1581):             │
    │                                            │
    │ ✅ NO MOSTRAR:                             │
    │ • Nombres de beneficiarios                 │
    │ • Direcciones específicas                  │
    │ • Teléfonos personales                     │
    │ • Diagnósticos médicos                     │
    │ • Ningún dato identificable                │
    │                                            │
    │ ✅ MOSTRAR (agregado, anónimo):            │
    │ • Cantidad total beneficiarios             │
    │ • Grupos demográficos (menores, embaraz)  │
    │ • Urgencia/criticidad general              │
    │ • Stock disponible                         │
    │                                            │
    │ ✓ GDPR/LSPP Compliant                      │
    └────────────────────────────────────────────┘
```

**Duración:** 100-300ms (consultas agregadas)

**Puntos críticos:**
- ✅ Muestra impacto sin comprometer privacidad
- ✅ Motiva donaciones (urgencia visible)
- ✅ Usa agregados (COUNT, SUM) no registros individuales
- ✅ LSPP compliant (sin PII en búsqueda)

---

## 8. FLUJO END-TO-END COMPLETO

**Objetivo:** Mostrar cómo fluyen los datos desde censo hasta impacto  
**Trigger:** Beneficiario registrado en censo  
**Resultado:** Scoring → Recomendaciones → Entregas → Impacto → Donante notificado

```
┌─────────────────────────────────────────────────────────┐
│ DÍA 1: CENSO INICIAL (Módulo 2)                         │
│                                                         │
│ Operador registra familia: García López                 │
│ ├─ María (34, embarazada 7m, anemia)                   │
│ ├─ Juan (37, empleado)                                  │
│ ├─ Sofia (7 años)                                       │
│ └─ Lucas (3 años, malnutrición)                         │
│                                                         │
│ Status: ✓ Registrado en DB                             │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (INMEDIATO: Sistema auto-procesa)
┌─────────────────────────────────────────────────────────┐
│ DÍA 1 10:01 AM: SCORING AUTOMÁTICO (Módulo 7)           │
│                                                         │
│ ScoringEngine::calculate(María) ejecuta:                │
│ ├─ Demográfico: 34, pregnant, trim 7 → 28 pts         │
│ ├─ Salud: Anemia crónica → 20 pts                      │
│ ├─ Nutricional: Familia de 4 → 15 pts                  │
│ ├─ Social: Bajo ingreso, presión → 14 pts             │
│ └─ TOTAL: 28+20+15+14 = 77 pts → 🔴 CRÍTICO            │
│                                                         │
│ Actualización en DB:                                    │
│ ├─ beneficiaries.vulnerability_score = 77              │
│ ├─ beneficiaries.priority_level = "CRITICAL"           │
│ ├─ vulnerability_scores (histórico)                    │
│ └─ Cache Redis invalidado                              │
│                                                         │
│ Status: ✓ Score calculado                              │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (INMEDIATO: Alertas disparadas)
┌─────────────────────────────────────────────────────────┐
│ DÍA 1 10:02 AM: ALERTAS AUTOMÁTICAS                     │
│                                                         │
│ AlertService::dispatch(CRITICAL_SCORE_UPDATED)          │
│                                                         │
│ OPERADOR:                                               │
│ 📲 Push mobile: "🔴 CRÍTICO: María García"              │
│                                                         │
│ COORDINADOR:                                            │
│ 📧 Email: Incluye en reporte diario (agregado)          │
│ 📊 Dashboard: Muestra +1 en "Críticos"                  │
│                                                         │
│ MÉDICO:                                                 │
│ 📧 Email: "Revisar: María García (embarazada 7m)"       │
│                                                         │
│ Status: ✓ Stakeholders notificados                      │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (INMEDIATO: Recomendaciones generadas)
┌─────────────────────────────────────────────────────────┐
│ DÍA 1 10:03 AM: RECOMENDACIONES (Módulo 7)              │
│                                                         │
│ RecommendationService::generate(María)                  │
│                                                         │
│ Protocolos aplicables:                                  │
│ 1. WHO Embarazo (conf: 0.98)                            │
│    ├─ Hierro                                            │
│    ├─ Ácido Fólico                                      │
│    └─ Vitamina D                                        │
│                                                         │
│ 2. Local Health Anemia (conf: 0.85)                     │
│    ├─ Vitamina B12                                      │
│    └─ Ácido Fólico (ya recomendado)                     │
│                                                         │
│ 3. ICBF Nutrición Infantil (por Lucas < 5)             │
│    ├─ Leche en Polvo                                    │
│    ├─ Vitamina A                                        │
│    └─ Antiparasitarios                                  │
│                                                         │
│ Verificación de stock:                                  │
│ ├─ Hierro: 0 en stock (FALTA) 🔴                       │
│ ├─ Ácido Fólico: 5 en stock (Bodega Centro)            │
│ ├─ Leche: 12 en stock (Bodega Centro)                  │
│ └─ Antiparasitarios: 1 en stock (Bodega Centro)        │
│                                                         │
│ BeneficiaryRecommendations creadas: 7                   │
│ Status: PENDING (esperando confirmación operador)       │
│                                                         │
│ Status: ✓ Recomendaciones listas                        │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (OPERADOR VE EN FICHA)
┌─────────────────────────────────────────────────────────┐
│ DÍA 1 14:00 PM: OPERADOR EN CAMPO                        │
│                                                         │
│ Ficha de María en PWA:                                  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ María García López | 🔴 CRÍTICO (77/100)        │  │
│ │ Embarazada 7m, Anemia                           │  │
│ │                                                 │  │
│ │ RECOMENDACIONES AUTOMÁTICAS:                   │  │
│ │ ✅ Hierro → 🔴 FALTA (0 en stock)              │  │
│ │ ✅ Ácido Fólico → ✓ Disponible (Centro)        │  │
│ │ ✅ Vitamina D → ✓ Disponible (Centro)          │  │
│ │ ✅ Vitamina B12 → ✓ Disponible (Centro)        │  │
│ │ ✅ Leche en Polvo → ✓ Disponible (Centro)      │  │
│ │ ✅ Vitamina A → ✓ Disponible (Centro)          │  │
│ │ ✅ Antiparasitarios → ✓ Disponible (Centro)    │  │
│ │                                                 │  │
│ │ [✅ Confirmar Entregas] [🩺 Derivar]          │  │
│ └───────────────────────────────────────────────────┘  │
│                                                         │
│ Operador presiona: [✅ Confirmar Entregas]              │
│ ├─ ☑ Ácido Fólico (1 comp)                            │
│ ├─ ☑ Vitamina D (1 cap)                               │
│ ├─ ☑ Leche (2 bolsas)                                 │
│ └─ ☑ Vitamina A (1 fco)                               │
│ (No selecciona Hierro: no hay stock)                   │
│                                                         │
│ Foto comprobante: [📷 Capturada]                        │
│ Beneficiario recibió: ✓ Sí                             │
│                                                         │
│ Status: ✓ Guardado (IndexedDB local)                   │
│ Sync: ⏳ Sincronizando... (cuando regresa a internet)  │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (AL CONECTARSE A INTERNET)
┌─────────────────────────────────────────────────────────┐
│ DÍA 1 18:00 PM: SYNC A BACKEND                          │
│                                                         │
│ IndexedDB → Laravel API (StockExitController)           │
│                                                         │
│ POST /api/stock-exits                                   │
│ {                                                       │
│   beneficiary_id: 1,                                    │
│   warehouse_id: 1,                                      │
│   items: [                                              │
│     {item_id: 16, qty: 1},  // Ácido Fólico           │
│     {item_id: 17, qty: 1},  // Vitamina D             │
│     {item_id: 1, qty: 2},   // Leche                  │
│     {item_id: 8, qty: 1}    // Vitamina A             │
│   ],                                                    │
│   release_date: NOW(),                                  │
│   photo: [Base64...]                                   │
│ }                                                       │
│                                                         │
│ ✓ Entregado al servidor                               │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (BACKEND PROCESA)
┌─────────────────────────────────────────────────────────┐
│ DÍA 1 18:01 PM: ACTUALIZAR ESTADO                       │
│                                                         │
│ Backend procesa automáticamente:                        │
│                                                         │
│ 1. Crear StockExit records (para auditoría)            │
│    4 registros para los 4 items entregados            │
│                                                         │
│ 2. Crear CareHistory (historial de entrega)            │
│    1 registro consolidado                             │
│                                                         │
│ 3. Actualizar BeneficiaryRecommendations              │
│    WHERE item_id IN (16, 17, 1, 8)                    │
│    AND beneficiary_id = 1                              │
│    UPDATE status = "FULFILLED",                        │
│           fulfilled_at = NOW()                        │
│                                                         │
│ 4. Invalidar caché Redis:                              │
│    stats:global:v2                                     │
│    recommendations:beneficiary:1                       │
│                                                         │
│ Status: ✓ Estado actualizado en DB                     │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (ALERTAS GENERADAS)
┌─────────────────────────────────────────────────────────┐
│ DÍA 1 18:02 PM: ALERT DE IMPACTO                        │
│                                                         │
│ AlertService::dispatch(RECOMMENDATION_FULFILLED)        │
│ (Para cada item entregado)                              │
│                                                         │
│ EMAIL A DONANTES (DIGEST SEMANAL):                      │
│ "Tu donación de Ácido Fólico llegó a María García      │
│  (embarazada, 34 años, en situación crítica)            │
│                                                         │
│  Cantidad: 1 comprimido                                │
│  Beneficiario: María García (Familia García López)      │
│  Beneficiarios indirectos: 4 personas (familia)         │
│  Impacto: Prevenir defectos neurales en bebé           │
│                                                         │
│  Gracias por tu contribución ❤️"                        │
│                                                         │
│ Status: ✓ Donante notificado                           │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (COORDINADOR VE ACTUALIZACIÓN)
┌─────────────────────────────────────────────────────────┐
│ DÍA 1 18:03 PM: DASHBOARD COORDINADOR                   │
│                                                         │
│ WebSocket actualiza:                                    │
│ "María García: Recomendaciones actualizadas"           │
│                                                         │
│ Estadísticas agregadas cambian:                         │
│ • -1 de "CRÍTICO SIN RECOMENDACIÓN"                    │
│ • +1 de "CRÍTICO CON PARCIAL CUMPLIMIENTO"            │
│ • Top necesidades: Hierro (aún falta 34 fam)          │
│                                                         │
│ Status: ✓ Dashboard actualizado                        │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼ (SEGUIMIENTO AUTOMÁTICO)
┌─────────────────────────────────────────────────────────┐
│ DÍA 8: FOLLOW-UP AUTOMÁTICO                             │
│ (Scheduled Job ejecuta cada 24h)                        │
│                                                         │
│ Sistema verifica:                                       │
│ ├─ ¿María recibió las medicinas? ✓ Sí                 │
│ ├─ ¿Se cumplieron todas las recomendaciones? 57%      │
│ │  (No se entregó Hierro → falta)                     │
│ ├─ ¿Hay síntomas nuevos? No reportados               │
│ └─ ¿Se requiere derivación a salud? Condicional      │
│                                                         │
│ Acción:                                                 │
│ ├─ SI síntomas empeoraron:                            │
│ │  AlertService::dispatch(REFERRAL_NEEDED)            │
│ │  → Operador notificado (necesita derivación)        │
│ │                                                     │
│ └─ SI todo normal:                                    │
│    Care_history marca: follow_up_status = "DONE"      │
│    → Sistema listo para siguiente ciclo                │
│                                                         │
│ Status: ✓ Seguimiento completado                      │
└───────────────┬─────────────────────────────────────────┘
                │
                ▼
    ┌──────────────────────────────────────┐
    │ RESULTADO FINAL (1 Semana después):  │
    │                                      │
    │ ✅ María García:                     │
    │ • Score: 77/100 (CRÍTICO)            │
    │ • Recomendaciones: 7 creadas         │
    │ • Cumplimiento: 4/7 (57%)            │
    │ • Falta: Hierro (por stock shortage)│
    │ • Entregas confirmadas: 4 items      │
    │ • Beneficiarios indirectos: 4 perso │
    │ • Donantes impactados: 4             │
    │ • Status: En seguimiento             │
    │                                      │
    │ ✅ Sistema de Donaciones Rolda:      │
    │ • Automatización: 95% (sin manual)   │
    │ • Auditoría: Completa (LSPP OK)      │
    │ • Impacto: Medible y comunicable    │
    │ • Escalabilidad: Validada            │
    │                                      │
    │ NEXT: Operador derivará a médico    │
    │ si síntomas empeoran                 │
    │                                      │
    └──────────────────────────────────────┘
```

---

---

## 📊 RESUMEN VISUAL: INTEGRACIÓN DE FLUJOS

```
┌───────────────────────────────────────────────────────────────┐
│                    FLUJOS DEL MÓDULO 7                        │
│                                                               │
│ ENTRADA                 PROCESAMIENTO          SALIDA        │
│ ─────────────────────────────────────────────────────────   │
│                                                               │
│ 1. CENSO                ScoringEngine          Dashboard      │
│    └─ Beneficiario  ──→ (Scoring 0-100)   ──→ Operador      │
│                        └─ +/- score        ──→ Coordinador   │
│                        └─ Alerta crítico   ──→ Doctor/Donor  │
│                                                               │
│ 2. SCORE CRÍTICO        RecommendationService Recomendaciones
│    └─ Trigger auto  ──→ (Protocolo matching)──→ Ficha        │
│                        └─ Verifica stock    ──→ Disponibilidad
│                        └─ Calcula distancia ──→ Orden bodegas │
│                                                               │
│ 3. RECOMENDACIÓN        AlertService (Multi-rol)             │
│    └─ Creada        ──→ SMS/Email/Push      ──→ Operador     │
│                        └─ WhatsApp          ──→ Coordinador  │
│                        └─ Dashboard vivo    ──→ Todos        │
│                                                               │
│ 4. DERIVACIÓN MÉDICA    HealthReferrals      Puesto Salud    │
│    └─ Síntomas      ──→ (Crear + Notificar) ──→ Doctor      │
│                        └─ 48h Follow-up      ──→ Escalation  │
│                                                               │
│ 5. ENTREGA EN CAMPO     StockExit + Care     Auditoría       │
│    └─ Operador      ──→ History + Recom     ──→ Trazabilidad │
│                        └─ Update reco        ──→ FULFILLED    │
│                        └─ Notify Donor       ──→ Impacto      │
│                                                               │
│ 6. STOCK SHORTAGE       Alert brecha         Coordinador     │
│    └─ Stock < Demand ──→ (Calcular gap)   ──→ Alerta SMS     │
│                        └─ Notify donors    ──→ Email urgente │
│                        └─ Dashboard        ──→ Acción rápida │
│                                                               │
│ 7. BÚSQUEDA PÚBLICA     Enriquecimiento      Ciudadano       │
│    └─ Item buscado  ──→ (Agregados anónimos)──→ Demanda visi
│                        └─ Privacy check (LSPP)              │
│                        └─ Muestra urgencia                   │
│                                                               │
└───────────────────────────────────────────────────────────────┘
```

---

**FIN DE DIAGRAMAS DE FLUJO**

Todos los flujos son:
- ✅ Idempotentes (pueden ejecutarse múltiples veces)
- ✅ Escalables (funcionan con 1 o 1000 beneficiarios)
- ✅ Auditables (cada paso se registra en DB)
- ✅ Recuperables (si falla, retry automático)
- ✅ LSPP compliant (privacidad en cada paso)

