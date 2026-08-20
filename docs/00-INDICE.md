# 📚 Índice — Documentación Donaciones Rolda

**Versión:** 3.0 (índice único, consolidado)
**Fecha de esta consolidación:** 17 de agosto de 2026
**Reemplaza a:** `archive/00-INDICE-MAESTRO.md` (v1.0) y `archive/00-INDICE-MAESTRO-ACTUALIZADO.md` (v2.0)

> El roadmap de desarrollo vivo (módulos, funciones, checklists por equipo) se gestiona en **Notion** — "Kit de desarrollo de productos de software". Este índice es la puerta de entrada a la documentación de diseño/negocio en `/docs`; el roadmap operativo día a día vive en Notion, no aquí.

---

## ⚠️ Cifras y decisiones vigentes (léelo antes que cualquier otro documento)

La documentación de este repositorio se escribió en varias pasadas el mismo día (17-ago-2026) y contenía **tres narrativas de costo/timeline distintas y contradictorias**, más un diseño duplicado del módulo de beneficiarios. Se resolvieron así:

| Decisión | Vigente | Superado / archivado |
|---|---|---|
| **Presupuesto (3 meses)** | **$8,256 USD** (incl. margen de contingencia 200%) — ver [`ANALISIS-COSTOS-REALES.md`](ANALISIS-COSTOS-REALES.md) y [`OPCIONES-DE-FINANCIAMIENTO.md`](OPCIONES-DE-FINANCIAMIENTO.md) | $1,748 (solo AWS, serie técnica en `06-Estimacion-Costos-3Meses.md`), $1,548/$1,210 (`05-Analisis-Infraestructura-AWS.md`), $6,050 (`archive/01-Material-Bolsillo-Fichas.md`, obsoleto) |
| **Timeline / lanzamiento** | **7 días, lanzamiento 23 de agosto de 2026** — ver [`00-Presentacion-Ejecutiva-Donaciones-Rolda.md`](00-Presentacion-Ejecutiva-Donaciones-Rolda.md) | 11 días / 2 de septiembre (`07-Plan-Entrega-MVP.md`, `Especificaciones_Técnicas_y_Arquitectura_v2.md`); 30 de julio (obsoleto, ya pasado) |
| **Diseño del módulo de Beneficiarios** | **Documento 11** (`11-Modulo-7-Beneficiarios-Estadisticas-Inteligentes.md`) — scoring 0-100 en 3 niveles, 8 tablas | `archive/10-Modulo-Estadisticas-Census-Priorization.md` (algoritmo y esquema de datos incompatibles, descartado) |
| **Especificación técnica base** | **`Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md`** (integra Módulo 7) | `archive/01-Especificaciones-Tecnicas-Expandidas.md` (v1, pre-Módulo 7) |

El desglose de esfuerzo por módulo y equipo más granular sigue siendo el de `07-Plan-Entrega-MVP.md` + `11-Modulo-7-...md` — es la base numérica usada para poblar el roadmap en Notion, aunque su calendario de 11 días no es el compromiso público vigente (7 días). Ver la nota de vigencia al inicio de cada documento afectado.

---

## 1. Empezar aquí

| Documento | Para quién |
|---|---|
| [`00-Presentacion-Ejecutiva-Donaciones-Rolda.md`](00-Presentacion-Ejecutiva-Donaciones-Rolda.md) | Autoridades, ONG, donantes — qué es, impacto, costo, cronograma |
| [`Presentacion-Visual-Donaciones-Rolda.html`](Presentacion-Visual-Donaciones-Rolda.html) | Versión visual/interactiva de lo anterior (14 slides) |
| [`Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md`](Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md) | Equipo técnico — visión, stack, 7 módulos, modelo de datos |
| Roadmap en Notion (Product Roadmap + Engineering Tasks) | Equipo de desarrollo — qué construir, checklist, equipos asignados |

## 2. Especificación técnica y arquitectura

- [`14-Modulos-y-Funcionalidades.md`](14-Modulos-y-Funcionalidades.md) — **catálogo de los 7 módulos**: objetivo, funcionalidad, endpoints y pantallas de cada uno (documento de referencia funcional)
- [`Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md`](Especificaciones_Técnicas_y_Arquitectura_-_Donaciones_Rolda_v2.md) — spec vigente (Módulos 1-7 + Módulo 7 integrado)
- [`02-Modelo-Datos-MER-DDL.md`](02-Modelo-Datos-MER-DDL.md) — MER + DDL completo de Módulos 1-6 (12 tablas). *Nota: aún no incluye los roles `coordinator/doctor/donor/municipal` ni las 8 tablas del Módulo 7 — pendiente de fusionar con el modelo de `11-Modulo-7...md`.*
- [`03-Funciones-Adicionales-Propuestas.md`](03-Funciones-Adicionales-Propuestas.md) — 5 funciones Fase II (geolocalización, reportes, QR, etc.)
- [`11-Modulo-7-Beneficiarios-Estadisticas-Inteligentes.md`](11-Modulo-7-Beneficiarios-Estadisticas-Inteligentes.md) — diseño vigente del módulo de beneficiarios/scoring/recomendaciones
- [`09-Analisis-Integracion-Apps-Existentes.md`](09-Analisis-Integracion-Apps-Existentes.md) / [`09B-Resumen-Integracion-RAPIDO.md`](09B-Resumen-Integracion-RAPIDO.md) — evaluación de reutilizar código de apps voluntarias existentes (ahorro 27-34h)
- [`15-Sistema-de-Diseno-Visual.md`](15-Sistema-de-Diseno-Visual.md) — estándar de diseño visual normativo ("Stark Dim"): tokens, tipografía, componentes, reglas de rendimiento (censo offline-first)

## 3. Diagramas

- [`INDICE-DIAGRAMAS.md`](INDICE-DIAGRAMAS.md) — mapa de los 16 diagramas de `12` y `13` (no indexa `04`, ver abajo)
- [`04-Diagramas-Flujos-Modulos.md`](04-Diagramas-Flujos-Modulos.md) — 5 flujos de Módulos 1-6 (búsqueda, entrada offline, aprobación, sync, alertas)
- [`12-Diagramas-Flujo-Detallados.md`](12-Diagramas-Flujo-Detallados.md) — 8 flujos del Módulo 7 (scoring, recomendación, alertas, derivación, entrega, end-to-end)
- [`13-Diagramas-Arquitectura.md`](13-Diagramas-Arquitectura.md) — 8 diagramas de arquitectura/capas/despliegue AWS del Módulo 7

## 4. Infraestructura, costos y entrega

- [`05-Analisis-Infraestructura-AWS.md`](05-Analisis-Infraestructura-AWS.md) — arquitectura AWS de referencia (ECS/RDS/Redis/ALB), Terraform. *Cifra de costo desactualizada, ver tabla de vigencia arriba.*
- [`06-Estimacion-Costos-3Meses.md`](06-Estimacion-Costos-3Meses.md) — metodología de cálculo de costo AWS por servicio. *Cifra total desactualizada, ver tabla de vigencia arriba.*
- [`ANALISIS-COSTOS-REALES.md`](ANALISIS-COSTOS-REALES.md) — **presupuesto vigente** ($8,256 USD con margen 200%)
- [`OPCIONES-DE-FINANCIAMIENTO.md`](OPCIONES-DE-FINANCIAMIENTO.md) — **estrategias de financiamiento vigentes** (recomendado: Alcaldía 50% + ONG 30% + Donantes 20%)
- [`LO-QUE-NECESITAMOS-SIN-COSTOS.md`](LO-QUE-NECESITAMOS-SIN-COSTOS.md) — compromisos operativos por entidad (sin dinero)
- [`07-Plan-Entrega-MVP.md`](07-Plan-Entrega-MVP.md) — plan día a día y desglose de esfuerzo por módulo/equipo (base numérica del roadmap en Notion). *Calendario de 11 días no es el compromiso público vigente (7 días), ver tabla arriba.*

## 5. Cumplimiento y seguridad

- [`08-Matriz-Compliance-Privacy-LSPP.md`](08-Matriz-Compliance-Privacy-LSPP.md) — cumplimiento Ley 1581/2012 (Colombia), retención de datos, derechos del titular, protocolo de brechas

## 5B. Pruebas QA

- [`16-Plan-de-Pruebas-QA.md`](16-Plan-de-Pruebas-QA.md) — guía paso a paso para QA: casos de prueba manuales con resultado esperado y checklist de cumple/no cumple, por módulo ya construido (hoy: Módulo 2 y Formulario de Encuestas). Se amplía a medida que se agregan módulos.

## 6. Roadmap de desarrollo (vive en Notion, no aquí)

El desglose por **módulos, funciones y características**, con checklist de cada uno y asignación de equipos (Backend/Frontend/DevOps/QA/Seguridad), se gestiona en la base de datos **Product Roadmap** + **Engineering Tasks** del workspace de Notion "Kit de desarrollo de productos de software". Este repositorio es la fuente de las decisiones de diseño y negocio; Notion es la fuente de verdad operativa del día a día.

## 7. Histórico (`docs/archive/`)

Documentos superados, conservados por trazabilidad — cada uno indica en su encabezado qué lo reemplaza:

- `00-INDICE-MAESTRO.md`, `00-INDICE-MAESTRO-ACTUALIZADO.md` — índices v1/v2, reemplazados por este archivo
- `01-Especificaciones-Tecnicas-Expandidas.md` — spec v1, reemplazada por la v2
- `01-Material-Bolsillo-Fichas.md` — material de campo con cifras obsoletas ($6,050, nunca actualizado)
- `10-Modulo-Estadisticas-Census-Priorization.md` — diseño alternativo de scoring, descartado en favor del documento 11
- `RESUMEN-CAMBIOS.md`, `RESUMEN-ACTUALIZACION-MODULO-7.md` — changelogs de las revisiones del 17-ago-2026, ya consolidados en este índice

---

## Contradicciones conocidas sin resolver (para transparencia)

- `02-Modelo-Datos-MER-DDL.md` no define los roles `coordinator/doctor/donor/municipal` que sí usa `Especificaciones_Técnicas_y_Arquitectura_v2.md` y el Módulo 7 — falta fusionar el DDL de roles.
- El esfuerzo de desarrollo del Módulo 7 se cita como "donado ($0)" en la línea comercial y como "$2,100-$2,250 a $50/h" en `archive/RESUMEN-ACTUALIZACION-MODULO-7.md` — tratar esta última cifra como no vigente.
