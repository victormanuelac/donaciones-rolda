> ℹ️ **Nota de vigencia:** La cifra de costo mensual de este documento (~$419/mes, ~$1,548-$1,210 en 3 meses) no coincide con [`06-Estimacion-Costos-3Meses.md`](06-Estimacion-Costos-3Meses.md) ($1,748) ni con el presupuesto oficial vigente en [`ANALISIS-COSTOS-REALES.md`](ANALISIS-COSTOS-REALES.md) ($8,256 con margen 200%) — ver [`docs/00-INDICE.md`](00-INDICE.md). Se conserva por su análisis arquitectónico (comparación ECS/EC2/App Runner, Terraform), no por sus cifras.

# 🏗️ Análisis de Infraestructura AWS — Donaciones Rolda

**Versión:** 1.0  
**Región:** us-east-1 (N. Virginia) + Failover a us-west-2  
**Timeline:** MVP 7-14 días, Escalable a multimunicipalidad  
**Audiencia:** Tech Lead, DevOps, Comité técnico

---

## 📋 Índice

1. [Arquitectura de Referencia](#arquitectura-de-referencia)
2. [Comparativa de Opciones](#comparativa-de-opciones)
3. [Servicios AWS Seleccionados](#servicios-aws-seleccionados)
4. [Estrategia de Datos](#estrategia-de-datos)
5. [Seguridad y Cumplimiento](#seguridad-y-cumplimiento)
6. [Observabilidad](#observabilidad)
7. [Estrategia de Costos](#estrategia-de-costos)

---

## 🏛️ Arquitectura de Referencia

```
┌─────────────────────────────────────────────────────────────────┐
│                    DONACIONES ROLDA — ARQUITECTURA AWS           │
└─────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────┐
│                        CDN & EDGE (CloudFront)                 │
│  • Assets estáticos (JS, CSS, imágenes)                        │
│  • TTL: 1 semana (assets), 5 min (HTML)                        │
└──────────────┬──────────────────────────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────────────────────┐
│                    APPLICATION LOAD BALANCER                    │
│  • Health checks cada 30s                                       │
│  • Sticky sessions para PWA                                    │
│  • SSL/TLS 1.3 terminación                                      │
└──────────────┬──────────────────────────────────────────────────┘
               │
┌──────────────▼──────────────────────────────────────────────────┐
│                  COMPUTE TIER (ECS + Fargate)                   │
│                                                                  │
│  Task Definition: docker-image:latest                           │
│  • CPU: 1 vCPU (0.5 prod, 0.25 dev)                            │
│  • Memoria: 2 GB (1 GB prod, 512MB dev)                        │
│  • Réplicas: 3 (prod) / 1 (staging) / 0 (dev por demanda)     │
│  • Auto Scaling: 50% CPU utilization target                    │
│                                                                  │
│  Contenedor: PHP-FPM 8.3 + Nginx 1.26                          │
│  • Laravel 11 app                                              │
│  • Entrypoint: php-fpm + nginx reverse proxy                   │
│  • Health endpoint: /health (returns 200)                      │
│  • Logs → CloudWatch (JSON)                                    │
└──────────────┬──────────────────────────────────────────────────┘
               │
      ┌────────┴─────────────────────────┐
      │                                  │
      ▼                                  ▼
┌─────────────────┐          ┌──────────────────────┐
│  RDS Aurora     │          │ ElastiCache Redis    │
│  PostgreSQL/    │          │ (t3.micro prod)      │
│  MySQL 8.0      │          │                      │
│                 │          │ Cache TTL:           │
│ Cluster:        │          │ • search results: 30s│
│ • Primary       │          │ • rate limiters: 1h │
│ • Read replica  │          │ • sessions: 24h      │
│                 │          │ • master_items: 1h  │
│ Storage:        │          │                      │
│ • 20GB (MVP)    │          │ Failover: Automatic  │
│ • Auto scaling  │          │ Node expirations: RDB
│                 │          │                      │
│ Backups: Daily  │          │ Monitoramiento:      │
│ Retention: 7d   │          │ • CPU < 70%          │
└────────┬────────┘          │ • Evictions: 0       │
         │                   └──────────────────────┘
         │
         └──────────────────────┬───────────────────┘
                                │
                 ┌──────────────┴──────────────┐
                 │                             │
                 ▼                             ▼
        ┌────────────────┐           ┌─────────────────┐
        │  S3 (Backups)  │           │  SQS (Queues)   │
        │                │           │                 │
        │ • DB backups   │           │ • Email jobs    │
        │ • Logs archive │           │ • Export reports│
        │ • PDFs/exports │           │ • Notifications │
        │ • Versioning:  │           │                 │
        │   Enabled      │           │ Retention: 4d   │
        │ • Encryption:  │           │ DLQ: Enabled    │
        │   AES-256      │           │                 │
        │ • Lifecycle:   │           │ Consumer:       │
        │   Glacier 30d  │           │ Lambda workers  │
        └────────────────┘           └─────────────────┘
                 │
                 └────────────────┬────────────────┘
                                  │
                                  ▼
                        ┌──────────────────────┐
                        │  CloudWatch Logs +   │
                        │  X-Ray Tracing       │
                        │                      │
                        │ • App logs: JSON     │
                        │ • Retention: 30d     │
                        │ • Alarms: CPU, 404s  │
                        │ • Traces: API calls  │
                        └──────────────────────┘
```

---

## 🆚 Comparativa de Opciones

### **Opción A: ECS Fargate (RECOMENDADA para MVP)**

| Aspecto | Detalles |
|---------|----------|
| **Servicio** | ECS (Elastic Container Service) + Fargate |
| **Gestión de Infraestructura** | Sin servidores (AWS maneja scaling automático) |
| **Inicio rápido** | 5-7 días (push Docker image, configure ALB) |
| **Costo MVP** | ~$200-250/mes (3 tasks × 0.5 vCPU + RDS + Redis) |
| **Escalabilidad** | Automática (target CPU 50%) |
| **DevOps** | Minimal (ECR, task definitions, ALB) |
| **Monitoreo** | CloudWatch integrado |
| **Ventajas** | ✅ Rápido, económico, sin OPS de servidores |
| **Desventajas** | ❌ Cold starts (lentos si no hay traffic), menos control fino |

---

### **Opción B: EC2 (Auto Scaling Group)**

| Aspecto | Detalles |
|---------|----------|
| **Servicio** | EC2 + ALB + ASG |
| **Gestión de Infraestructura** | Manual (patching, AMI, security groups) |
| **Inicio rápido** | 10-14 días (setup instances, scaling policies) |
| **Costo MVP** | ~$150-200/mes (3 × t3.small + RDS + Redis) |
| **Escalabilidad** | Manual o ASG policies |
| **DevOps** | Moderado (SSM, patching, backups) |
| **Monitoreo** | CloudWatch + custom agents |
| **Ventajas** | ✅ Más control, costo similar, performance predecible |
| **Desventajas** | ❌ Overhead de mantenimiento, patching, más lento de desplegar |

---

### **Opción C: App Runner (Simple pero limitada)**

| Aspecto | Detalles |
|---------|----------|
| **Servicio** | AWS App Runner (managed container) |
| **Gestión de Infraestructura** | Totalmente manejado por AWS |
| **Inicio rápido** | 3-5 días (deploy desde ECR/GitHub) |
| **Costo MVP** | ~$250-300/mes (1 vCPU + 2GB RAM + RDS) |
| **Escalabilidad** | Automática pero limitada |
| **DevOps** | Mínimo |
| **Monitoreo** | CloudWatch |
| **Ventajas** | ✅ Súper fácil, 0 configuración |
| **Desventajas** | ❌ Menos flexible, cold starts más largos, costo mayor |

---

### **Decisión ArchiPM: Opción A (ECS Fargate)**

**Razones:**
- ✅ MVP en 7-14 días (timeline crítico)
- ✅ Costo optimizado ($200-250/mes)
- ✅ Escalable automáticamente
- ✅ Facilita multi-municipalidad (adicionar recursos sin complejidad)

---

## 🛠️ Servicios AWS Seleccionados

### **1. ECS (Elastic Container Service)**

```yaml
Cluster: donaciones-rolda-prod
Launch Type: Fargate
Platform Version: LATEST

Task Definition:
  Name: donaciones-rolda-app
  CPU: 1024 (1 vCPU)
  Memory: 2048 (2 GB)
  Containers:
    - Name: app
      Image: AWS_ACCOUNT.dkr.ecr.us-east-1.amazonaws.com/donaciones-rolda:latest
      Port: 9000 (PHP-FPM)
      Essential: true
      LogConfiguration:
        LogDriver: awslogs
        Options:
          awslogs-group: /ecs/donaciones-rolda
          awslogs-region: us-east-1
          awslogs-stream-prefix: app

  Networking:
    AssignPublicIp: ENABLED (Fargate requirement)
    SecurityGroups: [sg-app-ecs]
    Subnets: [subnet-public-a, subnet-public-b]

Service:
  Name: donaciones-rolda-svc
  LaunchType: FARGATE
  TaskCount: 3 (para HA)
  LoadBalancer: app-alb
  AutoScaling:
    MinCapacity: 2
    MaxCapacity: 10
    TargetCPU: 50%
    Scale-up threshold: 60% CPU (1 min)
    Scale-down threshold: 30% CPU (5 min)
```

**Estimado de costo:**
- 3 tasks × 1 vCPU × $0.04696/hora = $3.38/día = **$101.40/mes**

---

### **2. RDS Aurora (MySQL 8.0)**

```yaml
Cluster: donaciones-rolda-aurora
Engine: MySQL 8.0.39
Instances:
  - Primary (db.t3.small):
      Storage: 20GB (auto-scaling enabled, max 100GB)
      IOPS: 3000 (included with t3)
  - Read Replica (db.t3.small):
      Available Zone: us-east-1b
      Failover priority: 1

Backups:
  Retention period: 7 days
  Backup window: 03:00-04:00 UTC
  Automated backup: enabled
  
High Availability:
  Multi-AZ: enabled
  Failover time: < 30s
  Enhanced monitoring: enabled

Performance Insights:
  Enabled: true
  Retention: 7 days

Encryption:
  At Rest: enabled (AES-256)
  In Transit: TLS 1.3
```

**Estimado de costo:**
- Primary db.t3.small: **$0.176/hora** = $126.72/mes
- Read Replica db.t3.small: **$0.176/hora** = $126.72/mes
- Storage 20GB: **$2/mes**
- **Total: ~$255/mes**

---

### **3. ElastiCache Redis**

```yaml
Cluster: donaciones-rolda-redis
Engine: Redis 7.0
Node Type: cache.t3.micro (prod) / t3.small (si carga crece)
Number of Cache Nodes: 1 (+ 1 read replica para failover)
Automatic Failover: enabled
Encryption at Rest: enabled
Encryption in Transit: enabled

Parameter Group:
  maxmemory-policy: "allkeys-lru" (evict oldest on memory full)
  timeout: 300 (disconnect idle clients)

Backups:
  Snapshot retention: 7 days
  Automatic snapshots: enabled

Monitoring:
  CloudWatch: enabled
  EnhancedMonitoring: enabled
```

**Estimado de costo:**
- t3.micro × 2 nodes (primary + replica): **$0.017/hora × 2** = $24.48/mes
- Data transfer: **$2/mes**
- **Total: ~$27/mes**

---

### **4. Application Load Balancer**

```yaml
Name: donaciones-rolda-alb
Scheme: internet-facing
Subnets: [us-east-1a, us-east-1b]
Security Groups: [sg-alb]

Listeners:
  - Protocol: HTTP (port 80)
    Actions: Redirect to HTTPS
  - Protocol: HTTPS (port 443)
    Certificate: *.donaciones-rolda.com (ACM)
    DefaultActions: Forward to ecs-target-group

Target Group:
  Name: donaciones-rolda-tg
  Protocol: HTTP (port 9000, PHP-FPM via Nginx)
  Health Checks:
    Path: /health
    Interval: 30s
    Timeout: 5s
    HealthyThreshold: 2
    UnhealthyThreshold: 2
    Matcher: 200
  Stickiness: enabled (1 day, for PWA sessions)

Logging:
  Enabled: true
  S3 Bucket: donaciones-rolda-alb-logs
```

**Estimado de costo:**
- ALB: **$0.0225/hora** = $16.2/mes
- Processed bytes (avg): **$0.006/GB** (estimate ~10GB/month = $0.06)
- **Total: ~$16/mes**

---

### **5. Route 53 (DNS)**

```yaml
HostedZone: donaciones-rolda.com
Records:
  - Name: donaciones-rolda.com
    Type: A
    Alias: true
    AliasTarget: donaciones-rolda-alb.elb.us-east-1.amazonaws.com
    
  - Name: api.donaciones-rolda.com
    Type: A
    Alias: true
    AliasTarget: (same ALB)

  - Name: admin.donaciones-rolda.com
    Type: A
    Alias: true
    AliasTarget: (same ALB)

HealthChecks:
  - Endpoint: https://donaciones-rolda.com/health
    Protocol: HTTPS
    Interval: 30s
    FailureThreshold: 3
    Alarm: SNS notification
```

**Estimado de costo:**
- Hosted zone: **$0.50/mes**
- Queries: **~$0.40/mes** (estimate 1M queries/month at $0.4/M)
- **Total: ~$1/mes**

---

### **6. S3 (Almacenamiento: Backups, Logs, Reportes)**

```yaml
Buckets:
  - donaciones-rolda-backups:
      Versioning: enabled
      Encryption: AES-256 (SSE-S3)
      Lifecycle:
        - Days: 30, transition to GLACIER
        - Days: 90, delete
  
  - donaciones-rolda-logs:
      Encryption: AES-256
      Lifecycle:
        - Days: 7, transition to GLACIER
        - Days: 30, delete
  
  - donaciones-rolda-reports:
      Public access: blocked
      Encryption: AES-256
      Lifecycle:
        - Days: 90, delete

CORS:
  Enabled for PWA offline sync (IndexedDB cloud backup)
```

**Estimado de costo:**
- Storage ~50GB/mes (backups + logs): **$1.15/mes**
- Requests: **~$0.50/mes**
- Transition to Glacier: **~$0.30/mes**
- **Total: ~$2/mes**

---

### **7. CloudWatch (Monitoreo y Logs)**

```yaml
Log Groups:
  - /ecs/donaciones-rolda (app logs)
    Retention: 30 days
  - /aws/rds/instance/... (DB logs)
    Retention: 7 days
  - /aws/elasticache/... (Redis logs)
    Retention: 7 days

Dashboards:
  - Main: CPU, Memory, Network, Requests
  - Database: Connections, Queries/sec, Replica lag
  - Cache: Evictions, Hit rate, CPU
  - Application: Error rate, P99 latency, 404s

Alarms:
  - ECS CPU > 70% → Slack notification
  - RDS CPU > 80% → Pagerduty alert
  - Redis Memory > 90% → Email warning
  - HTTP 5xx > 1% requests → Immediate alert
  - ALB target unhealthy → Slack

Metric Filters:
  - ERROR logs → Count and alert if > 10/min
  - SQL slow queries → Alert if > 1s
  - Unauthorized access attempts → Track and alert
```

**Estimado de costo:**
- Logs ingestion (~10GB/month): **$5/mes**
- Metric storage: **~$2/mes**
- Alarms (10 alarms): **~$1/mes**
- Dashboard: Free
- **Total: ~$8/mes**

---

### **8. SQS (Queue para Async Jobs)**

```yaml
Queue: donaciones-rolda-jobs
Visibility timeout: 300s (5 min)
Message retention: 4 days (14400000 seconds)
Delay: 0s (immediate)
Dead Letter Queue: donaciones-rolda-dlq
  Max receive count: 3

Messages:
  - Email notifications (Sendgrid)
  - PDF exports (DOMPDF)
  - Report generation
  - Cache invalidation
  - Audit logs batching

Consumers:
  - Lambda function (PHP runtime via RoadRunner)
  - Triggered: every message OR batch (1000 msg, 30s timeout)
```

**Estimado de costo:**
- ~500 messages/day = **$0.25/mes**
- **Total: < $1/mes**

---

### **9. Lambda (Async Job Processing)**

```yaml
Function: donaciones-rolda-worker
Runtime: Custom (PHP RoadRunner on AL2 base)
Memory: 1024 MB
Timeout: 60s
Environment:
  QUEUE_URL: SQS queue URL
  DB_CONNECTION: RDS endpoint
  REDIS_URL: ElastiCache endpoint

Trigger:
  - SQS (batch size: 10 messages)
  - Scheduled rule: cron(0 3 * * ?) — daily cleanup job

Logging:
  CloudWatch: /aws/lambda/donaciones-rolda-worker
  Duration: 30 days
```

**Estimado de costo:**
- ~10,000 invocations/month × 1GB × 30s avg = **~$1.50/mes**
- **Total: ~$2/mes**

---

### **10. Certificate Manager (SSL/TLS)**

```yaml
Certificates:
  - donaciones-rolda.com
  - *.donaciones-rolda.com
  - api.donaciones-rolda.com

Validation: DNS (automatic renewal)
Protocol: TLS 1.3 (ALB)
Cipher suites: Modern only (no legacy)
```

**Estimado de costo:**
- Public certificates: **Free**
- **Total: $0/mes**

---

## 🔐 Estrategia de Datos

### **Modelo de Almacenamiento**

| Datos | Servicio | Estrategia | Backup |
|-------|----------|-----------|--------|
| Operacionales (usuarios, stock) | RDS Aurora MySQL | ACID, 3NF normalizado | Daily snapshots, 7d |
| Sesiones + Cache | ElastiCache Redis | Hot data, LRU eviction | RDB snapshots |
| Archivos (PDFs, fotos) | S3 | Versionado, Lifecycle | Versioning enabled |
| Logs (auditoría) | CloudWatch + S3 | 30d en memory, archive en Glacier | 90d Glacier |
| Temporal (sync queue) | SQS | FIFO reliability | DLQ (3 retries) |

### **Estrategia de Replicación**

```
Producción (us-east-1):
  ├─ RDS Primary → Read Replica (same AZ)
  ├─ Redis → Read Replica (different AZ, automatic failover)
  ├─ ECS → 3 tasks (distribudos en AZs)
  └─ S3 → Single region (backups daily a Glacier)

Staging (us-east-1):
  ├─ RDS → Restored from prod snapshot (daily)
  ├─ Redis → Fresh instance
  └─ ECS → 1 task (testing only)

Dev (Local):
  ├─ SQLite / Local MySQL
  ├─ Redis local (Docker)
  └─ Seed data de producción (anónimizado)
```

---

## 🛡️ Seguridad y Cumplimiento

### **Matriz de Cumplimiento (LSPP Colombia)**

| Requisito | Implementación | Responsable |
|-----------|-----------------|-------------|
| Encriptación en tránsito | TLS 1.3 en ALB + RDS | AWS (managed) |
| Encriptación en reposo | AES-256 (S3, RDS, Redis) | AWS (managed) |
| Auditoría de accesos | CloudWatch + RDS audit logs | App + AWS |
| Retención de logs | 30d CloudWatch, 90d Glacier | AWS (lifecycle) |
| Derecho al olvido | Script SQL para anonimizar/delete | Manual (quarterly review) |
| Notificación de brechas | SNS alert + Slack + email | CloudWatch alarms |
| Acceso mínimo privilegio | IAM roles (ECS, Lambda, RDS) | DevOps (IaC) |

### **IAM Roles y Policies**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ecs-tasks.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    },
    {
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": "arn:aws:logs:us-east-1:ACCOUNT:log-group:/ecs/donaciones-rolda:*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "ecr:GetAuthorizationToken",
        "ecr:BatchGetImage",
        "ecr:GetDownloadUrlForLayer"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "rds-db:connect"
      ],
      "Resource": "arn:aws:rds:us-east-1:ACCOUNT:db:donaciones-rolda-*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "elasticache:DescribeCacheClusters",
        "elasticache:DescribeReplicationGroups"
      ],
      "Resource": "*"
    },
    {
      "Effect": "Allow",
      "Action": [
        "s3:GetObject",
        "s3:PutObject"
      ],
      "Resource": "arn:aws:s3:::donaciones-rolda-*/*"
    }
  ]
}
```

---

## 📊 Observabilidad

### **Dashboards Clave**

**Dashboard 1: Operacional**
```
Widget 1: Requests/sec (ALB)
Widget 2: Latency (p50, p99)
Widget 3: Error rate (4xx, 5xx)
Widget 4: ECS task count + CPU %
Widget 5: Active database connections
Widget 6: Cache hit ratio
Widget 7: Queue depth (SQS)
```

**Dashboard 2: Base de Datos**
```
Widget 1: Primary CPU & memory
Widget 2: Read replica lag
Widget 3: Connections by user
Widget 4: Slow query log (> 1s)
Widget 5: Storage growth
Widget 6: Backup status
```

**Dashboard 3: Seguridad**
```
Widget 1: Unauthorized login attempts
Widget 2: API rate limit violations
Widget 3: S3 public access alarms
Widget 4: IAM policy changes
Widget 5: Certificate expiration (days)
```

### **Alertas Críticas**

| Métrica | Umbral | Acción |
|---------|--------|--------|
| HTTP 5xx rate | > 1% | Page on-call |
| ECS tasks unhealthy | > 0 | Slack + email |
| RDS CPU | > 80% | Scale up read replica |
| Redis evictions | > 0 | Investigate + potential scale |
| SSL certificate | < 30 days to expire | Automatic renewal (ACM) |
| Database growth | > 80GB | Alert to expand storage |
| Unauthorized access | > 5 attempts/min | Temporary IP block + alert |

---

## 💰 Estimación de Costos (Desglosado)

### **Resumen de Costos Mensuales**

| Servicio | Costo Base | Notas |
|----------|-----------|-------|
| **ECS Fargate** | $101.40 | 3 tasks × 1 vCPU |
| **RDS Aurora** | $255.00 | Primary + read replica + storage |
| **ElastiCache Redis** | $27.00 | t3.micro × 2 con failover |
| **ALB** | $16.20 | Load balancing + processed bytes |
| **Route 53** | $1.00 | Hosted zone + queries |
| **S3** | $2.00 | Backups + logs + storage |
| **CloudWatch** | $8.00 | Logs + metrics + alarms |
| **SQS + Lambda** | $3.00 | Job processing |
| **Data Transfer (egress)** | $5.00 | Estimate out-of-region |
| **Misc (ACM, etc)** | $0.00 | Free tier |
| **---** | **---** | **---** |
| **TOTAL MENSUAL** | **~$419/mes** | Producción MVP |
| **STAGING** | **~$150/mes** | Simplified setup |
| **DEV** | **$0/mes** | Local + free tier |

---

### **Proyección de 3 Meses (MVP Phase)**

```
Mes 1 (Agosto 2026 - Partial month, go-live late Aug):
  Producción: $419 × 0.5 = $210
  Staging: $150
  Total: $360

Mes 2 (Septiembre 2026):
  Producción: $419
  Staging: $150
  Total: $569

Mes 3 (Octubre 2026):
  Producción: $419 (+ $50 data transfer if scaling)
  Staging: $150
  Total: $619

3-MONTH TOTAL: ~$1,548 (including staging/dev)
PRODUCTION ONLY: ~$1,210
```

### **Escalamiento (Escenario: +5 municipalidades en 6 meses)**

```
Arquitectura Multi-Tenant (Fase II):
  
MVP (1 municipalidad):
  ├─ $419/mes (prod)
  └─ Total: $2,500/Q (quarterly)

Escala 2-3 municipalidades:
  ├─ ECS: +$50-100 (scale tasks)
  ├─ RDS: +$100 (larger instance)
  ├─ Redis: +$15 (upgrade node type)
  └─ Total: $600-650/mes (~$1,900-1,950/Q)

Escala 4-6 municipalidades:
  ├─ RDS: Multi-region replication (+$300)
  ├─ ECS: Larger cluster (+$200)
  ├─ Redis: Cluster mode (+$50)
  ├─ Data Transfer: +$50
  └─ Total: $1,100-1,150/mes (~$3,400-3,450/Q)
```

---

## 🚀 Plan de Despliegue (Terraform IaC)

**Estructura de archivos:**
```
terraform/
├── main.tf              # Provider + backend
├── ecs.tf               # Task definitions, services
├── rds.tf               # Aurora cluster
├── redis.tf             # ElastiCache
├── alb.tf               # Load balancer
├── route53.tf           # DNS
├── s3.tf                # Buckets
├── cloudwatch.tf        # Monitoring
├── iam.tf               # Roles & policies
├── variables.tf         # Input variables
├── outputs.tf           # Outputs
└── environments/
    ├── prod.tfvars
    ├── staging.tfvars
    └── dev.tfvars
```

**Ejemplo de despliegue:**
```bash
# 1. Preparar infraestructura
terraform init
terraform plan -var-file=environments/prod.tfvars

# 2. Crear recursos
terraform apply -var-file=environments/prod.tfvars
# (~10 minutos)

# 3. Obtener outputs (ALB URL, RDS endpoint, etc)
terraform output

# 4. Deployar aplicación
# → ECR push → ECS task update → Auto-rollout
```

---

## ✅ Checklist de Implementación

- [ ] Crear AWS account + set up billing alerts
- [ ] Configurar Terraform backend (S3 + DynamoDB lock)
- [ ] Crear VPC, subnets, security groups
- [ ] Provisionar RDS Aurora (MySQL 8.0)
- [ ] Provisionar ElastiCache Redis
- [ ] Crear ECR repository
- [ ] Build + push Docker image
- [ ] Crear ECS cluster, task definition, service
- [ ] Configurar ALB + target groups
- [ ] Setup Route 53 + ACM certificate
- [ ] Configurar CloudWatch dashboards + alarms
- [ ] Test failover (RDS, Redis, ECS tasks)
- [ ] Load testing (Apache JMeter, Locust)
- [ ] Security audit (OWASP, IAM review)
- [ ] Set up CI/CD (GitHub Actions → ECR → ECS)

---

**Próximas acciones:** Revisar estimación de costos detallados (archivo 06) y plan de entrega (archivo 07).
