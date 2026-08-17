# 🗺️ REFERENCIA RÁPIDA: ÍNDICE DE DIAGRAMAS

**Donaciones Rolda - Mapeo Visual Completo**

**Documentación Visual:** 2 archivos + este índice  
**Total de Diagramas:** 16 principales  
**Formato:** ASCII Art (portable y versionable)

---

## 📊 DIAGRAMAS DE FLUJO (12-Diagramas-Flujo-Detallados.md)

### Flujos Individuales (8)

| # | Flujo | Propósito | Input | Output | Duración |
|---|-------|----------|-------|--------|----------|
| **1** | [Scoring Vulnerabilidad](#1-scoring-vulnerabilidad) | Calcula 0-100 ponderado | Beneficiary creado | Score + Priority + Alert | 50-100ms |
| **2** | [Recomendación Automática](#2-recomendación-automática) | Protocolo matching + stock | Score actualizado | BeneficiaryRecommendations | 100-200ms |
| **3** | [Alertas Automáticas](#3-alertas-automáticas) | Multi-rol, multi-canal | Evento crítico | SMS/Email/Push/Dashboard | < 200ms |
| **4** | [Derivación a Salud](#4-derivación-a-salud) | Canalizar a médico | Síntomas críticos | HealthReferral + Notificación | Inmediato + 48h follow-up |
| **5** | [Entrega y Actualización](#5-entrega-y-actualización) | Registrar entrega | Operador confirma | CareHistory + FULFILLED | < 500ms |
| **6** | [Stock vs Demanda](#6-stock-vs-demanda) | Detectar brecha | Stock actualizado | Alert de gap | < 100ms |
| **7** | [Búsqueda Enriquecida](#7-búsqueda-enriquecida) | Mostrar impacto | Ciudadano busca item | Resultados con demanda | 100-300ms |
| **8** | [End-to-End Completo](#8-flujo-end-to-end-completo) | De censo a impacto | Beneficiary registrado | Donante notificado | 1-7 días |

### Características de Flujos

✅ **Idempotentes:** Pueden ejecutarse múltiples veces  
✅ **Escalables:** 1 o 1000+ beneficiarios  
✅ **Auditables:** Cada paso registrado en DB  
✅ **Recuperables:** Retry automático si falla  
✅ **LSPP Compliant:** Privacidad en cada paso  

---

## 🏗️ DIAGRAMAS DE ARQUITECTURA (13-Diagramas-Arquitectura.md)

### Diagramas de Capas y Componentes (8)

| # | Diagrama | Nivel | Capa | Focus |
|---|----------|-------|------|-------|
| **1** | [Arquitectura de Capas](#1-arquitectura-de-capas) | Sistema Completo | 7 capas | Client → API → App → Data → External |
| **2** | [Entidades ER](#2-diagrama-de-entidades) | Modelo de Datos | Data Layer | 8 tablas Módulo 7 + relaciones |
| **3** | [Request → Response](#3-flujo-de-datos-request--response) | API Flow | App + Data | Sincronía + Async jobs |
| **4** | [Integración Módulos](#4-integración-entre-módulos) | Sistemas | App Layer | M1↔7, M3↔7, M6↔7 |
| **5** | [Componentes Frontend](#5-componentes-frontend-por-rol) | UI | Client Layer | 5+ roles, 10+ componentes |
| **6** | [CI/CD Pipeline](#6-pipeline-cicd) | DevOps | Deployment | GitHub → Testing → Production |
| **7** | [Deployment AWS](#7-deployment-architecture) | Infrastructure | Cloud | VPC, ECS, RDS, Redis, ALB |
| **8** | [Security Layers](#8-security--privacy-layers) | Seguridad | Multi-layer | 7 capas de protección + LSPP |

---

## 🎯 GUÍA DE LECTURA POR ROL

### 👤 **Project Manager**
Lee en este orden:
1. [Flujo End-to-End Completo](#8-flujo-end-to-end-completo) — Entiende todo el flujo
2. [Arquitectura de Capas](#1-arquitectura-de-capas) — Cómo interactúan los componentes
3. [CI/CD Pipeline](#6-pipeline-cicd) — Cómo se deploya
4. [Componentes Frontend](#5-componentes-frontend-por-rol) — Qué ve cada rol

**Tiempo:** 1 hora

---

### 👨‍💻 **Arquitecto de Software**
Lee en este orden:
1. [Arquitectura de Capas](#1-arquitectura-de-capas) — Stack completo
2. [Entidades ER](#2-diagrama-de-entidades) — Modelo de datos
3. [Integración Módulos](#4-integración-entre-módulos) — Puntos de conexión
4. [Request → Response](#3-flujo-de-datos-request--response) — Cómo fluyen datos
5. [Deployment AWS](#7-deployment-architecture) — Infraestructura
6. [Security Layers](#8-security--privacy-layers) — Protección

**Tiempo:** 1.5 horas

---

### 🔧 **Backend Developer**
Lee en este orden:
1. [Flujo de Scoring](#1-scoring-vulnerabilidad) — Lógica core
2. [Flujo de Recomendación](#2-recomendación-automática) — Protocolo matching
3. [Entidades ER](#2-diagrama-de-entidades) — Qué construir
4. [Request → Response](#3-flujo-de-datos-request--response) — Cómo integrar
5. [Integración Módulos](#4-integración-entre-módulos) — Qué otros módulos usar

**Tiempo:** 1 hora

---

### 🎨 **Frontend Developer**
Lee en este orden:
1. [Componentes Frontend](#5-componentes-frontend-por-rol) — Qué construir
2. [Flujo End-to-End](#8-flujo-end-to-end-completo) — Contexto general
3. [Entidades ER](#2-diagrama-de-entidades) — Estructura de datos
4. [Request → Response](#3-flujo-de-datos-request--response) — Cómo llamar API

**Tiempo:** 45 min

---

### ⚙️ **DevOps**
Lee en este orden:
1. [CI/CD Pipeline](#6-pipeline-cicd) — Build y testing
2. [Deployment AWS](#7-deployment-architecture) — Dónde corre
3. [Security Layers](#8-security--privacy-layers) — Qué proteger
4. [Arquitectura de Capas](#1-arquitectura-de-capas) — Dependencias

**Tiempo:** 1 hora

---

### 🩺 **Médico/Validador**
Lee en este orden:
1. [Flujo de Derivación a Salud](#4-derivación-a-salud) — Tu rol
2. [Flujo de Scoring](#1-scoring-vulnerabilidad) — Cómo se priorizan
3. [Flujo de Recomendación](#2-recomendación-automática) — Protocolos

**Tiempo:** 20 min

---

## 🔄 FLUJOS INTEGRADOS: Cómo se conectan

```
┌─────────────────────────────────────────────────────────┐
│ FLUJO 1: Scoring                                        │
│ "Beneficiary registrado → Score 0-100 calculado"       │
│ └─ Si CRÍTICO → Dispara Flujo 3 (Alertas)              │
│ └─ Siempre → Dispara Flujo 2 (Recomendaciones)        │
└────────────┬────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────┐
│ FLUJO 2: Recomendación                                  │
│ "Protocolos matching → Medicinas específicas"          │
│ └─ Al crear → Verifica Flujo 6 (Stock vs Demanda)      │
└────────────┬────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────┐
│ FLUJO 3: Alertas                                        │
│ "Eventos críticos → SMS/Email/Push/Dashboard"          │
│ └─ Puede disparar Flujo 4 (Derivación) si síntomas     │
└────────────┬────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────┐
│ FLUJO 4: Derivación                                     │
│ "Síntomas severos → Referral a puesto médico"          │
│ └─ Si médico atiende → completa diagnóstico            │
└────────────┬────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────┐
│ FLUJO 5: Entrega                                        │
│ "Operador entrega items → Marcar FULFILLED"            │
│ └─ Alerta a donante (Flujo 3 variante)                │
│ └─ Actualiza Flujo 6 (Stock disminuye)                 │
└────────────┬────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────┐
│ FLUJO 6: Stock vs Demanda                               │
│ "Detectar brecha → Alert si stock < demanda"           │
│ └─ Notifica Coordinador + Donantes (Flujo 3)           │
└────────────┬────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────┐
│ FLUJO 7: Búsqueda Enriquecida                           │
│ "Ciudadano busca → Ve demanda agregada"                │
│ └─ Motiva donación → puede cumplir Flujo 5             │
└────────────┬────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────┐
│ FLUJO 8: End-to-End                                     │
│ "Síntesis: Censo → Scoring → Recomendaciones →         │
│  Alertas → Entregas → Impacto → Donante"               │
└─────────────────────────────────────────────────────────┘
```

---

## 📐 ARQUITECTURA: Cómo se conectan las capas

```
CLIENTE (PWA/Mobile)
     ↓ (REST API + WebSocket)
GATEWAY (ALB, Rate Limiting, Auth)
     ↓ (Sanctum Middleware)
CONTROLLERS (FormRequest, Authorization)
     ↓ (Business Logic)
SERVICES (ScoringEngine, RecommendationService, AlertService)
     ↓ (Eloquent ORM)
MODELOS (Beneficiary, Recomendations, Alerts, etc)
     ↓ (Query Builder)
DATA LAYER (MySQL, Redis, IndexedDB)
     ↓ (Notification Channels)
EXTERNAL (Twilio, Firebase, Meta, SMTP)
```

---

## ✅ CHECKLIST: ¿Qué Necesitas Leer?

### Antes de Empezar Desarrollo
- [ ] Leer Flujo End-to-End completo (entender qué se construye)
- [ ] Leer Arquitectura de Capas (entender dónde construirlo)
- [ ] Leer Entidades ER (entender qué guardar en DB)
- [ ] Leer tu flujo específico (Scoring, Recom, Alerts, etc)

### Antes de Hacer Deployment
- [ ] Leer CI/CD Pipeline (cómo testear)
- [ ] Leer Deployment AWS (dónde correrá)
- [ ] Leer Security Layers (qué proteger)

### Antes de Deploy a Producción
- [ ] Todo lo anterior ✓
- [ ] Checklist de security review completado
- [ ] LSPP compliance validado
- [ ] Monitoring y alertas configurados

---

## 🎯 PUNTOS CLAVE POR DIAGRAMA

### Flujo #1: Scoring
- **Trigger:** BeneficiaryUpdated event
- **Output:** Score 0-100 + Priority Level + Contributing Factors
- **Nota:** Si cambia a CRÍTICO → dispara alertas automáticas

### Flujo #2: Recomendación
- **Trigger:** Score calculado (automático después de #1)
- **Output:** 7+ recomendaciones personalizadas en estado PENDING
- **Nota:** Verifica stock en bodegas automáticamente

### Flujo #3: Alertas
- **Trigger:** Eventos de 7 tipos diferentes
- **Output:** SMS/Email/Push/WhatsApp/Dashboard (según rol)
- **Nota:** Multi-canal, retry automático, auditable

### Flujo #4: Derivación
- **Trigger:** Síntomas críticos o recomendación médica
- **Output:** HealthReferral creada + Médico notificado
- **Nota:** Auto follow-up en 48h

### Flujo #5: Entrega
- **Trigger:** Operador confirma entrega en campo
- **Output:** StockExit + CareHistory + FULFILLED flag
- **Nota:** Donante notificado del impacto

### Flujo #6: Stock vs Demanda
- **Trigger:** Stock actualizado (automático)
- **Output:** Alert si hay brecha
- **Nota:** Notifica Coordinador + Donantes

### Flujo #7: Búsqueda Enriquecida
- **Trigger:** Ciudadano busca item en Módulo 1
- **Output:** Resultados + demanda agregada (anónima)
- **Nota:** LSPP compliant (sin PII)

### Flujo #8: End-to-End
- **Trigger:** Beneficiary registrado en censo
- **Output:** Donante notificado de impacto (7 días después)
- **Nota:** Integración completa de todos los flujos

---

## 🔐 CAPAS DE SEGURIDAD

1. **Perimeter:** Cloudflare, WAF, DDoS
2. **API:** HTTPS/TLS 1.3, JWT, Rate limiting, CORS
3. **Data:** Encryption at rest + in transit
4. **Auth:** RBAC, Multi-tenant isolation
5. **Privacy:** LSPP compliance (datos sujeto)
6. **Auditing:** Audit logs, change tracking
7. **Secrets:** Environment vars, KMS, Secrets Manager

---

## 🚀 PRIORIDAD DE LECTURA

**Si tienes 30 minutos:**
- Flujo #8 (End-to-End)

**Si tienes 1 hora:**
- Flujo #8 + Arquitectura de Capas

**Si tienes 2 horas:**
- Todos los flujos principales (1-7)

**Si tienes 3 horas:**
- Todos los flujos + Arquitectura completa

**Si tienes 4+ horas:**
- Todo: flujos + arquitectura + deployment + security

---

## 📎 REFERENCIAS CRUZADAS

| Necesitas entender... | Lee estos diagramas |
|---|---|
| Cómo se calcula el score | Flujo #1 + ER Vulnerability_scores |
| Cómo se generan recomendaciones | Flujo #2 + ER ProtocolRecommendation |
| Cómo se envían notificaciones | Flujo #3 + Arquitectura Capas (External) |
| Cómo se derivan a médico | Flujo #4 + ER HealthReferral |
| Cómo se registran entregas | Flujo #5 + ER CareHistory |
| Cómo se detectan brechas | Flujo #6 + Integración M3↔M7 |
| Cómo ve el ciudadano el impacto | Flujo #7 + Integración M1↔M7 |
| Cómo se construye la app | Componentes Frontend + Request→Response |
| Cómo se deploya | CI/CD + Deployment AWS |
| Cómo se asegura | Security Layers (8 capas) |

---

## 💡 TIPS DE LECTURA

1. **Usa ASCII art para visualizar:** Los diagramas son texto plano, abribles en cualquier editor
2. **Sigue las flechas:** Cada → y ▼ muestra el flujo de datos
3. **Busca [Sección]:** Usa Ctrl+F para saltar rápido a una sección
4. **Léelos en orden:** Flujos primero (qué pasa), Arquitectura después (cómo pasa)
5. **Referencia durante desarrollo:** Ten abierto en otra ventana

---

**Próximas acciones:**
1. Abre `12-Diagramas-Flujo-Detallados.md`
2. Lee el flujo de tu área (Backend → Scoring+Recom, Frontend → Componentes, etc)
3. Abre `13-Diagramas-Arquitectura.md`
4. Lee la capa que te corresponde
5. ¿Dudas? Vuelve a este índice

