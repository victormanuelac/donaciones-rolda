> ℹ️ **Nota de vigencia:** Este documento estima solo infraestructura AWS ($1,748 USD) con un sizing más conservador que [`ANALISIS-COSTOS-REALES.md`](ANALISIS-COSTOS-REALES.md) (que usa RDS Multi-AZ, NAT Gateway y suma servicios externos + capacitación). El presupuesto oficial vigente del proyecto es **$8,256 USD** (con margen de contingencia 200%) — ver [`docs/00-INDICE.md`](00-INDICE.md). Se conserva por su metodología de cálculo detallada por servicio, útil como referencia técnica.

# 💰 Estimación de Costos AWS (3 Meses) — Donaciones Rolda

**Versión:** 1.0  
**Período:** Agosto - Octubre 2026 (MVP)  
**Audiencia:** Donantes, Municipalidad, Finance, ONGs

---

## 📑 Índice

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Desglose Detallado por Servicio](#desglose-detallado-por-servicio)
3. [Escenarios de Uso](#escenarios-de-uso)
4. [Optimización de Costos](#optimización-de-costos)
5. [Proyecciones a Largo Plazo](#proyecciones-a-largo-plazo)

---

## 🎯 Resumen Ejecutivo

### **Costo Total Proyectado: 3 Meses**

| Componente | Mes 1 | Mes 2 | Mes 3 | **3 Meses** |
|-----------|-------|-------|-------|-----------|
| Producción | $210* | $419 | $469 | **$1,098** |
| Staging | $150 | $150 | $150 | **$450** |
| Dev (local) | $0 | $0 | $0 | **$0** |
| **Total** | **$360** | **$569** | **$619** | **$1,548** |

*Mes 1 es medio mes (go-live finales de agosto)

### **Desglose Porcentual de Costos (Producción)**

```
RDS Aurora (Base de Datos)          61.5% ($258/mes)
├─ Primary db.t3.small              45%   ($189)
├─ Read Replica db.t3.small         45%   ($189)
└─ Storage 20GB + backups           10%   ($8)

ECS Fargate (Compute)               24.2% ($101/mes)
├─ 3 tasks × 1 vCPU                 100%  ($101)

ElastiCache Redis (Cache)           6.4%  ($27/mes)
├─ 2 nodes t3.micro                 100%  ($27)

ALB + Route 53 + S3                 4.2%  ($18/mes)
└─ Data transfer                    1.2%  ($5/mes)

CloudWatch + SQS + Lambda           3.8%  ($15/mes)
```

---

## 📊 Desglose Detallado por Servicio

### **1. ECS Fargate (Compute)**

#### Configuración MVP:
```
Task Definition:
- CPU Reservation: 1024 (1 vCPU)
- Memory Reservation: 2048 MB
- Tasks Replicas: 3 (HA setup)

Pricing Model:
- vCPU: $0.04696 per hour
- Memory: $0.005215 per GB per hour

Calculations:
- 3 tasks × 1 vCPU × 24h × 30d × $0.04696 = $101.40
- 3 tasks × 2GB × 24h × 30d × $0.005215 = $75.02
- Total compute: $176.42/mes

BUT: Using Fargate Savings Plan (20% discount recommended)
- With 1-year commitment: $176.42 × 0.8 = $141.14/mes
- With 3-year commitment: $176.42 × 0.7 = $123.49/mes

Recommendation (MVP): Pay as you go ($176/mes), upgrade after 3 months if stable
```

**Línea de costo:**
- Mes 1: $88 (50% first 2 weeks) + $88 (second 2 weeks) = $88
- Mes 2: $176
- Mes 3: $176 (+ $50 if scaling to 4 tasks) = $226

**3-Month Total ECS: $490**

---

### **2. RDS Aurora MySQL**

#### Configuración MVP:
```
Instance 1: Primary (db.t3.small, us-east-1a)
Instance 2: Read Replica (db.t3.small, us-east-1b)

Hourly Rates:
- db.t3.small: $0.176 per hour

Calculations:
- Primary: $0.176 × 24h × 30d = $126.72/mes
- Read Replica: $0.176 × 24h × 30d = $126.72/mes
- Storage: 20GB × $0.10/GB = $2/mes
- Backup Storage: ~5GB (included in storage allocation)
- Data Transfer (internal, free): $0

Total RDS: $255.44/mes

AWS Pricing Note:
  - db.t3.small is on-demand (no discount available unless Savings Plan)
  - Burstable performance sufficient for MVP
  - Auto-scaling configured up to 100GB (no extra charge unless exceeded)
```

**Línea de costo:**
- Mes 1: $128 (50% due to mid-month launch)
- Mes 2: $255
- Mes 3: $255 (+ $10 if growth reaches 30GB) = $265

**3-Month Total RDS: $648**

---

### **3. ElastiCache Redis**

#### Configuración MVP:
```
Cluster: donaciones-rolda-redis
- 2 nodes (primary + replica) for HA
- Node type: cache.t3.micro

Hourly Rates:
- cache.t3.micro: $0.017 per node per hour

Calculations:
- 2 nodes × $0.017/hour × 24h × 30d = $24.48/mes
- Data transfer (inter-AZ): ~$2/mes
- Backup (RDB snapshots): Included in storage (< 1GB)

Total Redis: $26.48/mes

AWS Pricing Note:
  - t3.micro is eligible for Free Tier (but used in production, not free)
  - Automatic failover enabled (adds reliability, no cost)
  - Encryption (in transit + rest) adds negligible cost
```

**Línea de costo (stable, no growth expected):**
- Mes 1: $13 (50% first 2 weeks)
- Mes 2: $26.48
- Mes 3: $26.48

**3-Month Total Redis: $65.96**

---

### **4. Application Load Balancer**

#### Configuración MVP:
```
ALB: donaciones-rolda-alb
- Internet-facing (public)
- HTTPS/TLS 1.3 termination
- 2 AZs for redundancy

Pricing Structure:
- New ALB hourly charge: $0.0225 per hour
- Processed bytes: $0.006 per GB

Calculations (conservative estimates):
- Hourly charge: $0.0225 × 24h × 30d = $16.20/mes
- Data processed:
  * 50-100 concurrent users (MVP peak)
  * Avg request size: 50KB
  * Avg responses: 200KB
  * Daily: ~1,000 requests × 250KB = 250MB = 0.25GB
  * Monthly: 7.5GB × $0.006 = $0.045/mes

Total ALB: $16.25/mes

AWS Pricing Note:
  - Network Load Balancer would be same price but overkill for MVP
  - ALB Horizontal Scaling: Automatic, no config needed
```

**Línea de costo (stable):**
- Mes 1: $8.13 (50%)
- Mes 2: $16.25
- Mes 3: $16.25

**3-Month Total ALB: $40.63**

---

### **5. Route 53 (DNS)**

#### Configuración MVP:
```
Hosted Zones: 1
- donaciones-rolda.com

DNS Records:
- A record (donaciones-rolda.com → ALB)
- CNAME records (www, api, admin → ALB)
- TXT records (email verification, etc)

Pricing Structure:
- Hosted zone: $0.50/month (flat)
- Queries: $0.4 per million queries

Query Calculations (conservative):
- ~10 users searching/hour peak = 240 queries/day
- = 7,200 queries/month (free tier: 1B included)
- Cost: Negligible (~$0)

Alternative: CloudFlare (cheaper, $0-20/month)

Total Route 53: $0.50/mes
```

**Línea de costo (stable):**
- Mes 1-3: $0.50/mes

**3-Month Total Route 53: $1.50**

---

### **6. S3 (Almacenamiento)**

#### Configuración MVP:
```
Buckets: 3
1. donaciones-rolda-backups
   - Daily RDS snapshots: ~5GB
   - Lifecycle: 30d → GLACIER, 90d → delete
   - Versioning: Enabled

2. donaciones-rolda-logs
   - CloudWatch logs export: ~10GB/month
   - Lifecycle: 7d → GLACIER, 30d → delete

3. donaciones-rolda-reports
   - PDF exports: ~1GB/month
   - Lifecycle: 90d → delete

Total Storage Estimate: 20GB active, 30GB in Glacier

Pricing Structure (Standard):
- Storage: $0.023 per GB (first 50GB)
- Requests: $0.0005 per 1,000 PUT requests
- Glacier transition: $0.05 per 1,000 transitions

Calculations:
- S3 Standard: 20GB × $0.023 = $0.46/mes
- S3 Glacier: 30GB × $0.004 = $0.12/mes
- Requests (PUT): ~1,000/month = $0.0005
- Lifecycle transitions: 100 transitions × $0.05/1000 = $0.005

Total S3: $0.60/mes (very cheap!)

AWS Pricing Note:
  - S3 Standard-IA might be better for backups (unused 30+ days)
  - Current lifecycle (→Glacier) is good for compliance (audit logs)
```

**Línea de costo (stable, slight growth possible):**
- Mes 1: $0.30 (50%)
- Mes 2: $0.60
- Mes 3: $0.80 (if growth to 25GB active)

**3-Month Total S3: $1.70**

---

### **7. CloudWatch (Monitoreo)**

#### Configuración MVP:
```
Log Ingestion:
- ECS app logs: ~2GB/month
- RDS logs: ~1GB/month
- Redis logs: ~0.5GB/month
- ALB logs: ~2GB/month
- Lambda logs: ~0.5GB/month
Total: ~6GB/month ingestion

Metric Storage:
- 50 custom metrics
- 30-day retention

Dashboards: 3
Alarms: 12

Pricing Structure:
- Logs ingestion: $0.50 per GB
- Logs storage: $0.03 per GB per month
- Custom metrics: $0.30 each (for first 10k)
- Alarm: $0.10 each

Calculations:
- Log ingestion: 6GB × $0.50 = $3/mes
- Log storage (30d): 180GB × $0.03 = $5.40/mes
- Custom metrics: 50 × $0.30 = $15/mes (capped at $3.50 for first 10k)
- Alarms: 12 × $0.10 = $1.20/mes

Total CloudWatch: $8.60/mes

AWS Pricing Note:
  - First 5GB/month logs ingestion free (for new accounts)
  - Metrics included in each service (ec2, rds, elasticache)
```

**Línea de costo:**
- Mes 1: $4.30 (50% + free tier benefit)
- Mes 2: $8.60
- Mes 3: $8.60

**3-Month Total CloudWatch: $21.50**

---

### **8. SQS + Lambda (Async Jobs)**

#### Configuración MVP:
```
SQS Queue:
- ~500 messages/day (email notifications, PDF exports)
- 4-day retention
- No DLQ charges

Lambda:
- Function: PHP RoadRunner worker
- 10,000 invocations/month (~300/day)
- Avg execution: 1 minute
- Memory: 1024MB

Pricing Structure:
- SQS: $0.40 per million requests
- Lambda: $0.20 per million requests + $0.0000166667 per GB-second

Calculations:
- SQS: 500msg/day × 30d = 15,000/month
  Cost: 15,000 × $0.40 / 1M = $0.006/mes
- Lambda: 10,000 × $0.20 / 1M = $0.002/mes
- Lambda compute: 10,000 × 60s × 1GB × $0.0000166667 = $10/mes

Total SQS + Lambda: $10.01/mes

AWS Pricing Note:
  - Free tier: 1M SQS requests + 1M Lambda requests + 400,000 GB-seconds
  - Our usage well under free tier until scaling
```

**Línea de costo (grows with users):**
- Mes 1: $5 (50% + partial free tier)
- Mes 2: $10
- Mes 3: $12 (if 30% growth in jobs)

**3-Month Total SQS+Lambda: $27**

---

### **9. Data Transfer (EC2 + Egress)**

#### Configuración MVP:
```
Data Transfer Breakdown:
- ALB to ECS (internal, free): N/A
- ECS to RDS (internal, free): N/A
- ECS to Redis (internal, free): N/A
- ECS to S3 (internal, free in same region): N/A
- Egress out of AWS (internet): $0.09 per GB

Egress Scenarios:
- User downloads reports (PDF): ~50 downloads/month × 5MB = 250MB
- User downloads data (CSV): ~20 exports/month × 10MB = 200MB
- Third-party integrations (future): ~0MB (not MVP)
- CloudFront (not yet configured): Saves egress

Total Egress: ~450MB/month = 0.44GB

Egress Cost: 0.44GB × $0.09 = $0.04/mes

BUT: Adding 10% overhead for misc:
Total Data Transfer: $0.50/mes

AWS Pricing Note:
  - CloudFront CDN (future optimization) could save 50% on egress
  - S3 Transfer Acceleration: Not needed for MVP
```

**Línea de costo (stable, minimal):**
- Mes 1-3: $0.50/mes

**3-Month Total Data Transfer: $1.50**

---

### **10. Miscellaneous (ACM, KMS, etc)**

#### Configuración MVP:
```
ACM (Certificate Manager):
- Public certificate: FREE
- Auto-renewal: FREE

KMS (Key Management):
- Data encryption at rest (AWS Managed): FREE
- Customer Managed Keys: $1/month (optional for HIPAA compliance)
- Encryption API calls: ~1M/month (free tier)

Secrets Manager:
- Not used in MVP (env vars in container)

Total Misc: $0/mes (no custom KMS)
```

**3-Month Total Misc: $0**

---

## 📈 Resumen Tabular Completo (3 Meses)

### **Costo por Servicio**

| Servicio | Mes 1 | Mes 2 | Mes 3 | Subtotal | % |
|----------|-------|-------|-------|----------|-----|
| ECS Fargate | $88 | $176 | $226 | $490 | 15.5% |
| RDS Aurora | $128 | $255 | $265 | $648 | 20.5% |
| ElastiCache | $13 | $26.48 | $26.48 | $65.96 | 2.1% |
| ALB | $8.13 | $16.25 | $16.25 | $40.63 | 1.3% |
| Route 53 | $0.50 | $0.50 | $0.50 | $1.50 | 0.05% |
| S3 | $0.30 | $0.60 | $0.80 | $1.70 | 0.05% |
| CloudWatch | $4.30 | $8.60 | $8.60 | $21.50 | 0.7% |
| SQS + Lambda | $5 | $10 | $12 | $27 | 0.85% |
| Data Transfer | $0.50 | $0.50 | $0.50 | $1.50 | 0.05% |
| **Production Total** | **$247.73** | **$493.93** | **$555.93** | **$1,297.59** | **41%** |
| **Staging** | **$150** | **$150** | **$150** | **$450** | **14.3%** |
| **Dev (local)** | **$0** | **$0** | **$0** | **$0** | **0%** |
| **AWS Total** | **$397.73** | **$643.93** | **$705.93** | **$1,747.59** | **55%** |
| **Soporte + Misc** | **$0** | **$0** | **$0** | **$0** | **0%** |
| **GRAN TOTAL** | **$397.73** | **$643.93** | **$705.93** | **$1,747.59** | **100%** |

---

## 🎯 Escenarios de Uso

### **Escenario A: MVP Conservador (Real MVP, bajo uso)**

**Supuestos:**
- 20 operadores de campo
- 50-100 ciudadanos/día picos
- 10-20 entregas/día
- 2 horas de operación peak (7-9 AM)

**Ajustes:**
- Reducir ECS a 2 tasks: -$60/mes
- Reducir RDS a single instance (without replica): -$130/mes
- Reducir Redis a 1 node: -$13/mes
- Reduce CloudWatch alarms: -$5/mes

**Total Escenario A:**
- Mes 1-3: **~$900 total** (vs $1,748)
- Saves: **$850/3 months**

---

### **Escenario B: Growth (Mes 3 with 2 municipalities)**

**Supuestos:**
- 40 operadores (2 ciudades)
- 200-300 ciudadanos/día
- 50-100 entregas/día
- 8 horas operación peak

**Adjustments:**
- Upgrade ECS to 4 tasks (1.5 vCPU each): +$150/mes
- Upgrade RDS to db.t3.medium: +$100/mes
- Add second Redis node: +$13/mes
- Regional replication (us-west-2 standby): +$300/mes

**Total Escenario B (Mes 3):**
- **~$900-950/mes** (vs $700 base)
- Growth cost: +$200-250/mes per municipality added

---

## 🔧 Optimización de Costos

### **Recomendaciones Inmediatas (MVP)**

| Estrategia | Ahorro | Complejidad | Recomendación |
|-----------|--------|------------|--------------|
| Usar AWS Free Tier | -$20/mes | Baja | ✅ Hacer (CloudWatch, SQS, Lambda) |
| Savings Plans (1yr RDS) | -$40/mes | Baja | ✅ Hacer si presupuesto aprobado |
| Reserved Instances (EC2 alt) | -$30/mes | Media | ⚠️ Solo si no usar Fargate |
| CloudFront CDN | -$5/mes | Baja | ✅ Futura (fase II) |
| Compute Optimizer | Varies | Baja | ✅ Auto-recommendations |
| Remove unused resources | -$10/mes | Baja | ✅ Weekly cleanup |

**Savings Potenciales Mes 1-3:**
- Con Free Tier: Save $20/mes = $60 total ✅
- Con 1-year RDS Savings Plan (if approved): Save $40/mes = $120 total ✅
- **Total potential savings: $180**
- **Final cost: ~$1,567 (down from $1,748)**

---

### **Optimizaciones Futuras (Fase II+)**

| Estrategia | Ahorro | Timeline |
|-----------|--------|----------|
| Multi-region failover (cheaper) | -$150/mes | 6 months |
| Spot instances (ECS) | -$50/mes | 3 months |
| Data tiering (S3 Intelligent-Tiering) | -$5/mes | Immediate |
| Reserved Capacity (multi-year) | -$100/mes | After stability |
| Compression (logs, backups) | -$10/mes | 1 month |

---

## 📊 Proyecciones a Largo Plazo

### **Año 1 (12 meses)**

```
Q1 (MVP phase 3 months):       ~$1,700
Q2 (Stable growth):
  • +2 municipalities
  • Double users → $900/month × 3 = $2,700

Q3 (Scaling):
  • +3 more municipalities
  • Optimize → $1,100/month × 3 = $3,300

Q4 (Mature):
  • +2 more municipalities (7 total)
  • Multiregion → $1,400/month × 3 = $4,200

YEAR 1 TOTAL: ~$12,000 (avg $1,000/mes)
```

### **Multi-Municipality Cost Breakdown (Year 1 End)**

| Municipality | Dedicated Storage | Shared Compute | Total |
|--------------|------------------|-----------------|-------|
| Roldanillo | $300 | $140 | $440 |
| Ciudad 2 | $250 | $140 | $390 |
| Ciudad 3 | $250 | $140 | $390 |
| ... | ... | ... | ... |
| Ciudad 7 | $250 | $140 | $390 |
| **Shared** | — | $200 | **$200** |
| **TOTAL/month** | **$2,100** | **$1,400** | **$1,400** |

*Shared infrastructure (ALB, Route 53, CloudWatch) amortizado

---

## 💬 FAQ de Costos

**P: ¿Puedo usar AWS Free Tier?**  
R: Parcialmente. Muchos servicios tienen free tier, pero RDS Aurora y ECS Fargate no. Total savings: ~$20-30/mes (CloudWatch, Lambda, SQS).

**P: ¿Qué pasa si no tengo dinero ahora?**  
R: Opciones:
1. Solicitar a ONGs/fundaciones (presupuesto compartido)
2. Usar tier más económico (Escenario A, $900 total)
3. Delay MVP 1-2 semanas para buscar financiación

**P: ¿Cuándo alcanzaría break-even con usuarios pagos?**  
R: Si modelo SaaS a $10/usuario/mes:
- 50 usuarios × $10 = $500/mes (cubre 36% de costos)
- 150 usuarios × $10 = $1,500/mes (cubre 100%+ de costos)
- Timeline to 150 users: 3-6 meses típico

**P: ¿Hay descuentos corporativos?**  
R: Sí, AWS ofrece descuentos si:
- Compromiso > $10k/año (negotiate rates)
- Non-profit status (can apply for credits)
- Educational/government (up to 25% discount)

**P: ¿Puedo saltar RDS read replica?**  
R: Sí, ahorra $127/mes. Pero:
- ❌ Sin failover automático (downtime de 10-30 min si falla)
- ❌ Sin read scaling
- ✅ Aceptable para MVP si acepta riesgo

---

## 📅 Presupuesto por Fondo

### **Propuesta de Financiación**

```
Costo Total 3 meses: $1,748

Desglose sugerido:
├─ Municipalidad Roldanillo:    $500 (28%)
├─ Fundación ONG #1:            $400 (23%)
├─ Fundación ONG #2:            $400 (23%)
├─ Empresa Privada (sponsor):   $400 (23%)
└─ Voluntarios (gratis):        ~100h/mes dev (in-kind)

Quarterly Budget (Q1 2026):
- Solicitar: $1,750 para 3 meses
- Contingency (10%): +$175
- Total request: $1,925
```

---

## ✅ Checklist de Presupuesto

- [ ] Obtener aprobación de costo AWS ($1,750/3 meses)
- [ ] Identificar fuentes de financiación
- [ ] Configurar AWS Budgets + Alert ($2,000/month cap)
- [ ] Setup billing alerts (Slack/email si > 80% presupuesto)
- [ ] Monthly cost review (1st of each month)
- [ ] Optimization review (monthly)
- [ ] Forecast siguiente trimestre (último día de Q)

---

**Próximas acciones:** Revisar plan de entrega (archivo 07) y matriz de cumplimiento (archivo 08).
