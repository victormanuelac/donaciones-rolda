# 📅 Plan de Entrega MVP — Donaciones Rolda

**Versión:** 1.0  
**Timeline:** 7-14 días  
**Inicio:** Lunes, Agosto 19, 2026  
**Go-Live Target:** Viernes, Agosto 30 (Fase 1) + Martes, Sept 2 (Estable)  
**Audiencia:** Equipo de desarrollo, PM, stakeholders

---

## 🎯 Objetivo del MVP

Lanzar una versión funcional de Donaciones Rolda que permita:
- ✅ Ciudadanos buscar insumos en portal público
- ✅ Operadores de campo registrar entradas/salidas (online + offline)
- ✅ Administradores aprobar ítems y monitorear sistema
- ✅ Auditoría completa de accesos (LSPP compliance)

**NO incluido en MVP (Fase II):**
- ❌ Notificaciones via Telegram/WhatsApp (solo internas)
- ❌ Reportes avanzados (solo básicos)
- ❌ Mobile apps nativas (PWA es suficiente)
- ❌ Multi-municipalidad (fase II)

---

## 📋 Timeline Detallado (7-14 días)

### **SEMANA 1: Setup + Core Arquitectura**

#### **Día 1 (Lun 19 Ago) — Infrastructure & Setup**

**Hito: Infraestructura base lista**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| AWS account + billing alerts | DevOps | 2h | SÍ |
| Terraform setup (backend S3) | DevOps | 2h | SÍ |
| VPC, subnets, security groups | DevOps | 2h | SÍ |
| ECR repository | DevOps | 1h | SÍ |
| **Subtotal Día 1** | | **7h** | |

**Estado:** Verde ✅ (asumiendo AWS account ya existe)

---

#### **Día 2 (Mar 20 Ago) — Database + Caché**

**Hito: Bases de datos funcionando**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| Terraform: RDS Aurora MySQL | DevOps | 2h | SÍ |
| Terraform: ElastiCache Redis | DevOps | 1h | SÍ |
| Seed initial data (categories, zones, warehouses) | Backend | 2h | NO |
| Test DB connections | DevOps + Backend | 1h | SÍ |
| **Subtotal Día 2** | | **6h** | |

**Estado:** Verde ✅

---

#### **Día 3 (Mié 21 Ago) — Load Balancer + DNS**

**Hito: Networking listo**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| Terraform: ALB + target groups | DevOps | 2h | SÍ |
| Terraform: Route 53 + hosted zone | DevOps | 1h | SÍ |
| ACM certificate (auto-renew) | DevOps | 1h | SÍ |
| DNS propagation test | DevOps | 0.5h | NO |
| **Subtotal Día 3** | | **4.5h** | |

**Estado:** Verde ✅

---

#### **Día 4 (Jue 22 Ago) — Docker + ECS Setup**

**Hito: Contenedor y orquestación lista**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| Dockerfile (PHP-FPM + Nginx) | DevOps | 2h | SÍ |
| Build + push image to ECR | DevOps | 1h | SÍ |
| Terraform: ECS cluster | DevOps | 1.5h | SÍ |
| Terraform: Task definition | DevOps | 1.5h | SÍ |
| Terraform: ECS service | DevOps | 1h | SÍ |
| Health check endpoint (/health) | Backend | 1h | SÍ |
| **Subtotal Día 4** | | **8h** | |

**Estado:** Verde ✅ (container running in prod)

---

#### **Día 5 (Vie 23 Ago) — Backend API Core (1/2)**

**Hito: Endpoints públicos de búsqueda**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| API Controller: Search items | Backend | 2h | SÍ |
| API Controller: Get warehouses | Backend | 1.5h | SÍ |
| API Service: Calculate semaphore | Backend | 1.5h | SÍ |
| API Service: Haversine distance | Backend | 1h | NO |
| Full-text search indexing | Backend | 1h | NO |
| Unit tests (Search, Semaphore) | Backend | 2h | NO |
| **Subtotal Día 5** | | **9h** | |

**Estado:** Verde ✅

**End of Day 5 Status:**
```
Infrastructure: 100% ✅
Database: 100% ✅
API (Public): 60% ⏳
Frontend: 0% 🔴
Offline: 0% 🔴
Auth: 0% 🔴
```

---

### **SEMANA 2: APIs + Frontend + Testing**

#### **Día 6 (Lun 26 Ago) — Backend API Core (2/2)**

**Hito: Auth + Inventario API**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| Auth: Register/Login endpoints | Backend | 2.5h | SÍ |
| Auth: User approval workflow | Backend | 1.5h | SÍ |
| Inventory: Create stock entry | Backend | 2h | SÍ |
| Inventory: Create stock exit | Backend | 1.5h | SÍ |
| Audit logging (all actions) | Backend | 2h | SÍ |
| Unit tests (Auth, Inventory) | Backend | 2h | NO |
| **Subtotal Día 6** | | **11.5h** | |

**Estado:** Amarillo ⏳ (backend 95%)

---

#### **Día 7 (Mar 27 Ago) — Frontend: Public Portal**

**Hito: Búsqueda pública funcionando**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| Layout: Header + navigation | Frontend | 1.5h | NO |
| Search form + filters | Frontend | 2h | SÍ |
| Results display (cards) | Frontend | 2h | SÍ |
| Map integration (Leaflet) | Frontend | 1.5h | NO |
| Turnstile contact modal | Frontend | 1.5h | SÍ |
| WhatsApp deeplink generation | Frontend | 1h | SÍ |
| Responsive design (mobile) | Frontend | 2h | NO |
| Basic accessibility (a11y) | Frontend | 1h | NO |
| **Subtotal Día 7** | | **12.5h** | |

**Estado:** Verde ✅ (Public portal ready)

---

#### **Día 8 (Mié 28 Ago) — Frontend: Operator PWA + Admin**

**Hito: PWA y admin panel básicos**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| PWA: Service Worker setup | Frontend | 2h | SÍ |
| PWA: Manifest + install prompts | Frontend | 1h | SÍ |
| PWA: Offline storage (IndexedDB) | Frontend | 2h | SÍ |
| Operator: Entry form | Frontend | 2.5h | SÍ |
| Operator: Sync queue status | Frontend | 1.5h | SÍ |
| Admin: User approval panel | Frontend | 2h | SÍ |
| Admin: Master items queue | Frontend | 2h | SÍ |
| Admin: Simple dashboard | Frontend | 1.5h | NO |
| **Subtotal Día 8** | | **14.5h** | |

**Estado:** Amarillo ⏳ (PWA 90%, Admin 85%)

---

#### **Día 9 (Jue 29 Ago) — Testing + Bug Fixes + Hardening**

**Hito: Estabilidad MVP + Seguridad**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| End-to-end testing (public search) | QA | 2h | SÍ |
| End-to-end testing (operator entry) | QA | 2h | SÍ |
| End-to-end testing (offline → sync) | QA | 2.5h | SÍ |
| Security: SQL injection tests | Security | 1.5h | SÍ |
| Security: XSS/CSRF tests | Security | 1.5h | SÍ |
| Performance: Load test (100 concurrent) | DevOps | 2h | NO |
| Bug fixes from testing | Backend + Frontend | 3h | SÍ |
| Documentation: API (postman) | Backend | 1h | NO |
| **Subtotal Día 9** | | **15.5h** | |

**Estado:** Verde ✅ (MVP estable)

---

#### **Día 10 (Vie 30 Ago) — Pre-Launch + Staging Deploy**

**Hito: Go-live Staging + final checklist**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| Deploy to Staging environment | DevOps | 1h | SÍ |
| Full regression test (staging) | QA | 2h | SÍ |
| UAT with stakeholder | PM + Stakeholder | 2h | NO |
| Production checklist review | All | 1h | SÍ |
| Runbooks + incident procedures | DevOps | 1.5h | NO |
| Monitoring dashboards (prod) | DevOps | 1h | SÍ |
| Alerts + thresholds (prod) | DevOps | 0.5h | SÍ |
| Backup verification | DevOps | 1h | NO |
| **Subtotal Día 10** | | **10h** | |

**Estado:** Amarillo ⏳ (listo para launch, pequeños fixes)

---

#### **Día 11 (Lun 02 Sept) — Go-Live**

**Hito: 🚀 PRODUCCIÓN EN VIVO**

| Tarea | Responsable | Duración | Blocker? |
|-------|-----------|----------|----------|
| Final production deployment | DevOps | 1h | SÍ |
| Smoke tests (prod health check) | QA | 0.5h | SÍ |
| Monitor metrics (1h post-launch) | DevOps | 1h | NO |
| Launch announcement | Marketing | 0.5h | NO |
| On-call rotation activated | All | 8h | NO |
| User onboarding (operators) | Training | 2h | NO |
| **Subtotal Día 11** | | **13h** | |

**Estado:** Verde ✅ **MVP LIVE**

---

### **BONUS: Dias 12-14 (Sept 3-5) — Stabilization**

Después del go-live, posibles actividades:

| Tarea | Prioridad | Duración |
|-------|-----------|----------|
| Monitor for issues + hotfixes | 🔴 Crítica | Ongoing |
| User feedback collection | 🟡 Alta | 4h |
| Performance tuning | 🟡 Alta | 3h |
| Security audit (internal) | 🟡 Alta | 2h |
| Documentation improvements | 🟢 Media | 2h |

---

## 👥 Asignación de Recursos

### **Team Structure (Minimal MVP)**

```
├─ Project Manager (1 FTE)
│  ├─ Timeline management
│  ├─ Stakeholder communication
│  └─ Risk management
│
├─ Backend Developer (1.5 FTE)
│  ├─ Core Laravel API
│  ├─ Database schema
│  └─ Audit logging
│
├─ Frontend Developer (1.5 FTE)
│  ├─ Public portal
│  ├─ PWA + offline logic
│  └─ Admin panel
│
├─ DevOps Engineer (1 FTE)
│  ├─ Infrastructure (Terraform)
│  ├─ CI/CD pipeline
│  └─ Monitoring
│
├─ QA / Tester (0.5 FTE)
│  ├─ Testing plan
│  ├─ E2E testing
│  └─ Bug reporting
│
└─ Security / Compliance (0.25 FTE)
   ├─ LSPP checklist
   ├─ Encryption audit
   └─ Access control review
```

**Total Person-Days:** ~55 days (~280 hours)  
**Calendar Days:** 11 days (assuming 8h/day parallel work)

---

## 🎯 Hitos Clave (Burndown)

```
Día 1  │ ███░░░░░░░░░░░░░░░░░░ 15% ✓ Infrastructure
Día 2  │ ██████░░░░░░░░░░░░░░░ 25% ✓ Database + Cache
Día 3  │ █████████░░░░░░░░░░░░ 35% ✓ Networking
Día 4  │ ████████████░░░░░░░░░ 50% ✓ Container + ECS
Día 5  │ ███████████████░░░░░░ 60% ✓ API Search
Día 6  │ ██████████████████░░░ 70% ⏳ Auth + Inventory API
Día 7  │ ████████████████████░ 80% ✓ Public Portal
Día 8  │ █████████████████████ 90% ⏳ PWA + Admin
Día 9  │ ██████████████████████ 98% ⏳ Testing + Fixes
Día 10 │ ██████████████████████ 99% ⏳ Pre-launch
Día 11 │ ███████████████████████ 100% 🚀 LIVE
```

---

## 🚨 Riesgos Identificados

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|-------------|--------|-----------|
| AWS account delays | Media | Alto | ✅ Crear hoy |
| DB sync issues offline | Media | Medio | ✅ Heavy testing día 9 |
| Turnstile integration fails | Baja | Medio | ✅ Fallback: manual contact |
| Performance under load | Baja | Medio | ✅ Load test día 9 |
| Security vulnerability | Baja | Alto | ✅ Security review día 9 |
| Team member unavailable | Baja | Medio | ✅ Cross-train antes de día 6 |
| Scope creep from stakeholders | Alta | Medio | ✅ Firm "MVP only" communication |

**Contingency Plan:**
- Si se atrasan 2+ días → Postpone "funciones adicionales" (Geo, Reportes) a Fase II
- Si hay bugs críticos en día 10 → Delay go-live 2-3 días

---

## ✅ Checklist de Go-Live

### **Día 10 (Viernes) — Pre-Launch**

- [ ] Staging environment fully tested
- [ ] Production environment built in Terraform
- [ ] Database backups working (daily snapshots)
- [ ] SSL/TLS certificate installed and valid
- [ ] CloudWatch dashboards configured
- [ ] Alerts and thresholds set
- [ ] Runbooks written (scaling, failover, rollback)
- [ ] On-call rotation assignments (3 people)
- [ ] User credentials prepared (admin, test operators)
- [ ] Documentation complete (API, PWA, admin panel)
- [ ] Stakeholder training completed
- [ ] PR/Communications drafted

### **Día 11 (Lunes) — Launch**

- [ ] Final production deployment
- [ ] Smoke tests pass (health checks)
- [ ] Monitoring shows normal baseline
- [ ] Users can login and search
- [ ] Operators can register entries offline/online
- [ ] Admin can approve items
- [ ] Audit logs recording correctly
- [ ] No critical errors in logs
- [ ] Backup jobs running
- [ ] Public announcement sent

---

## 📊 Estimación de Esfuerzo por Módulo

| Módulo | Backend (h) | Frontend (h) | DevOps (h) | Testing (h) | Total |
|--------|-----------|------------|-----------|-----------|-------|
| 1. Public Search | 8 | 10 | 0 | 4 | 22h |
| 2. Authentication | 6 | 5 | 0 | 3 | 14h |
| 3. Inventory Entry | 8 | 10 | 0 | 4 | 22h |
| 4. Master Item Approval | 5 | 6 | 0 | 2 | 13h |
| 5. Auditing | 8 | 2 | 0 | 2 | 12h |
| 6. PWA + Offline | 3 | 15 | 0 | 5 | 23h |
| Infrastructure | 0 | 0 | 16 | 2 | 18h |
| Monitoring | 0 | 0 | 8 | 1 | 9h |
| Documentation | 2 | 0 | 1 | 0 | 3h |
| Testing (general) | 0 | 0 | 0 | 8 | 8h |
| **TOTAL** | **40h** | **48h** | **25h** | **31h** | **144h** |

**Distribución de esfuerzo:**
- Backend: 28% (40h)
- Frontend: 33% (48h)
- DevOps: 17% (25h)
- Testing: 22% (31h)

**Con 11 días y ~8h/día por persona:**
- Backend (1.5 FTE): 12h/día × 11 = 132h disponibles → 40h usado = **30% utilización** ✅
- Frontend (1.5 FTE): 12h/día × 11 = 132h disponibles → 48h usado = **36% utilización** ✅
- DevOps (1 FTE): 8h/día × 11 = 88h disponibles → 25h usado = **28% utilización** ✅
- QA (0.5 FTE): 4h/día × 11 = 44h disponibles → 31h usado = **70% utilización** ✅

**Conclusión:** Timeline es FACTIBLE con capacidad de buffer

---

## 🔄 Metodología de Entrega

**Agile Sprint (Micro-sprints de 1 día):**

```
Daily standup: 09:00 UTC
├─ What did you finish?
├─ What are you working on?
└─ Any blockers?

Daily deployment: 18:00 UTC
├─ Code review (30 min)
├─ Deploy to staging (15 min)
└─ Quick smoke test (15 min)

Production deployment: Día 11 (Lunes 09:00 UTC)
```

**Definition of Done (por tarea):**
- ✅ Code written + reviewed
- ✅ Tests pass (unit + integration)
- ✅ Deployed to staging
- ✅ Tested in staging by QA
- ✅ Documentation updated
- ✅ Zero known critical bugs

---

## 📞 Escalación y Decisiones

**Decision Matrix:**

| Issue | Owner | Escalate to |
|-------|-------|-----------|
| Technical blocker | Dev team | Tech Lead |
| Timeline slip > 1 day | PM | Project Sponsor |
| Security vulnerability | Security | CTO + Legal |
| Go-live decision | PM | Project Sponsor + Stakeholder |
| Feature requests (day 1-10) | PM | REJECT (Fase II) |

**Approval Gate (Day 10, 16:00 UTC):**
```
PM:        "Ready for production? [YES / NO]"
DevOps:    "Infrastructure stable? [YES / NO]"
QA:        "All critical tests pass? [YES / NO]"
Security:  "Security audit clear? [YES / NO]"
Sponsor:   "Go-live approved? [YES / NO]"

If ALL = YES → Proceed to launch
If ANY = NO → Hold for fixes (max 24h buffer)
```

---

## 📋 Post-Launch (Semanas 2-4)

### **Semana 2 (Sept 2-6) — Stabilization**

| Tarea | Owner | Priority |
|-------|-------|----------|
| Monitor system 24/7 | On-call | 🔴 Crítica |
| Hotfixes for bugs | Dev team | 🔴 Crítica |
| User feedback loop | PM | 🟡 Alta |
| Performance optimization | DevOps | 🟡 Alta |

### **Semana 3-4 (Sept 9-20) — Fase II Planning**

- Adicionar "funciones propuestas" (Geolocalización, Reportes, etc.)
- Escalar a 2-3 municipalidades
- Integraciones Telegram/WhatsApp

---

## 🎓 Lessons Learned Template

**Después de go-live, documentar:**

| Aspecto | What Went Well | What Could Improve |
|--------|---------------|--------------------|
| Timeline | ? | ? |
| Quality | ? | ? |
| Communication | ? | ? |
| Testing | ? | ? |
| Infrastructure | ? | ? |

---

**¡A por ello! 🚀**

**Próximas acciones:** Revisar matriz de cumplimiento (archivo 08) y crear plan de acción.
