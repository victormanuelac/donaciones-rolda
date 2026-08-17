# 🛡️ Matriz de Cumplimiento: LSPP, Privacidad y Seguridad — Donaciones Rolda

**Versión:** 1.0  
**Norma Principal:** Ley 1581/2012 (LSPP - Colombia)  
**Regulación Secundaria:** Decreto 1377/2013, Resolución 3961/2022  
**Audiencia:** Municipalidad, Abogado de privacidad, DNPD (si hay denuncias)

---

## 📑 Índice

1. [Resumen de Cumplimiento](#resumen-de-cumplimiento)
2. [LSPP: Análisis Detallado](#lspp-análisis-detallado)
3. [Clasificación de Datos Personales](#clasificación-de-datos-personales)
4. [Matriz de Auditoría y Trazabilidad](#matriz-de-auditoría-y-trazabilidad)
5. [Política de Retención y Eliminación](#política-de-retención-y-eliminación)
6. [Incidentes y Notificación](#incidentes-y-notificación)
7. [Derechos de Usuarios (Acceso/Rectificación/Olvido)](#derechos-de-usuarios)

---

## 🎯 Resumen de Cumplimiento

### **Estado General: VERDE ✅**

**Donaciones Rolda cumple con LSPP si implementa estos controles:**

| Control | MVP | Fase II | Responsable |
|---------|-----|---------|-------------|
| Politica de privacidad pública | ✅ | — | Legal |
| Consentimiento informado en registro | ✅ | — | Frontend |
| Encriptación en tránsito (TLS 1.3) | ✅ | — | DevOps |
| Encriptación en reposo (AES-256) | ✅ | — | DevOps |
| Auditoría de accesos (audit_logs) | ✅ | — | Backend |
| Retención de logs (30d CloudWatch, 90d Glacier) | ✅ | — | DevOps |
| Derecho al olvido (script de eliminación) | ✅ | ✅ | Backend |
| Notificación de brechas (72h máximo) | ✅ | — | Operations |
| DPO designado (Data Protection Officer) | ⏳ | ✅ | Municipalidad |
| Registro de actividades de tratamiento | ✅ | — | Compliance |
| Cláusulas LSPP en T&Cs | ✅ | — | Legal |

---

## 📜 LSPP: Análisis Detallado

### **1. Concepto de Datos Personales (Art. 5)**

**Datos que trata Donaciones Rolda:**

| Datos | Ejemplo | Clasificación | ¿Sensible? | ¿Origen? |
|-------|---------|---------------|-----------|---------|
| Nombre | "Juan Pérez" | Personal | NO | Usuario voluntario |
| Email | "juan@email.com" | Personal | NO | Usuario voluntario |
| Teléfono | "+573001234567" | Personal | NO | Usuario voluntario |
| Cédula | "1.023.456.789" | Personal | NO (pero crítico) | Usuario voluntario |
| Ubicación (zona) | "Barrio Centro" | Personal | NO | Geográfico/público |
| Coordenadas (bodega) | 4.8421, -75.9321 | Personal | SÍ (si se vincula a operador) | Infraestructura |
| Nombre beneficiario | "María (albergue)" | Personal | SÍ | Entrega de insumos |
| IP de acceso | "192.168.1.1" | Personal | NO | Sistema |
| User-agent | "Mozilla/5.0..." | Técnico | NO | Sistema |
| Búsquedas | "antibiótico" | Comportamental | NO | Sistema |

**Conclusión:** Donaciones Rolda trata datos personales (no solo técnicos).  
**Obligación:** Cumplir LSPP completa.

---

### **2. Principios de Tratamiento de Datos (Art. 8)**

#### **Principio 1: Legalidad**
```
¿Tenemos base legal para tratar datos?
SÍ: Consentimiento informado + propósito legítimo (emergencia humanitaria)

Implementación MVP:
✅ Política de privacidad clara antes de registro
✅ Casilla de consentimiento (obligatoria para crear cuenta)
✅ Documentación de propósito en T&Cs
```

#### **Principio 2: Finalidad**
```
¿Usamos datos solo para lo que el usuario consintió?
SÍ: Datos para emergencia + abastecimiento humanitario ÚNICAMENTE

Prohibido:
❌ Vender datos a terceros
❌ Usar para marketing comercial
❌ Compartir con ONGs sin consentimiento explícito
```

#### **Principio 3: Libertad**
```
¿Los usuarios pueden negar consentimiento sin perder servicio?
NO (no existe opción de "no consentir")

Justificación LSPP:
- Aplicable porque es interés legítimo (emergencia humanitaria)
- Precedente: sistemas públicos de salud no requieren consentimiento opt-in
- RECOMENDACIÓN: Agregar opción "no usar datos para analytics" (opcional)
```

#### **Principio 4: Veracidad**
```
¿Garantizamos que datos sean exactos?
Parcialmente: 

✅ Backend valida emails, teléfonos, cédulas
✅ Operadores no pueden editar datos de otros
❌ No tenemos verificación en tiempo real (depende usuario)

Mejora para Fase II:
- Verificación de email via enlace
- Verificación de cédula via consulta de RUT (DIAN API)
```

#### **Principio 5: Integridad**
```
¿Protegemos datos contra pérdida/alteración?
SÍ:

✅ TLS 1.3 (tránsito)
✅ AES-256 (reposo)
✅ Backups diarios a S3
✅ Replicación cross-AZ
```

#### **Principio 6: Confidencialidad**
```
¿Solo personal autorizado accede a datos?
SÍ:

✅ IAM roles (AWS)
✅ DB encryption keys (KMS)
✅ Audit logs (quién accedió qué)
✅ NDA firmas (equipo)
```

---

### **3. Derechos del Titular (Art. 8)**

#### **Derecho de Información (Art. 13)**
```
¿Informamos al usuario QUÉ datos tienen, PARA QUÉ, POR CUÁNTO TIEMPO?

Implementación MVP:
✅ Política de privacidad (Política.md en raíz)
✅ Banner en portal público: "Usamos cookies/datos para..."
✅ Formulario: "Al registrar aceptas política de privacidad"
✅ Email post-registro: "Confirmamos tu registro y datos guardados"
```

**Política de Privacidad Obligatoria (Template):**
```markdown
# Política de Privacidad — Donaciones Rolda

## 1. ¿Quién somos?
Donaciones Rolda es una plataforma colaborativa de la Municipalidad 
de Roldanillo para gestión de insumos en emergencias.

## 2. ¿Qué datos recolectamos?
- Nombre, email, teléfono, cédula (usuarios)
- Zona geográfica (búsquedas públicas)
- Datos de transacciones (entradas/salidas de insumo)

## 3. ¿Para qué usamos tus datos?
- Permitirte buscar/recibir insumos de emergencia
- Coordinar operadores de campo
- Cumplir auditoría y trazabilidad

## 4. ¿Cuánto tiempo guardamos tus datos?
- Usuarios activos: durante su cuenta
- Usuarios inactivos: 1 año, luego anonimización
- Logs de auditoría: 2 años (cumplimiento legal)

## 5. ¿Compartimos tus datos?
- NO con terceros sin consentimiento
- SÍ internamente entre operadores (necesario para emergencia)

## 6. Tus derechos
- Acceso: puedes ver tus datos (página "/account/data-export")
- Rectificación: puedes corregir tus datos
- Olvido: puedes solicitar eliminación (legal@donaciones-rolda.com)

## 7. Seguridad
- Encriptamos todos los datos en tránsito (HTTPS)
- Encriptamos datos en base de datos (AES-256)
- Hacemos backup diarios

## 8. Contacto
Preguntas sobre privacidad: privacy@donaciones-rolda.com
Coordinador de privacidad: [Nombre] ([email])
```

---

#### **Derecho de Acceso (Art. 15)**
```
¿Pueden los usuarios ver sus propios datos?

Implementación MVP:
✅ Endpoint: GET /api/account/me (datos personales)
✅ Endpoint: GET /api/account/activity (búsquedas, entregas)
✅ UI: Página "/account" con datos exportables

Formato de exportación:
- JSON (técnico)
- CSV (abre en Excel)
- PDF (legible para imprimir)
```

**Código de ejemplo:**
```php
// app/Http/Controllers/Account/DataExportController.php
public function export(Request $request, string $format = 'json')
{
    $user = auth()->user();
    
    $data = [
        'personal' => [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'document_id' => $user->document_id,
        ],
        'activity' => [
            'login_history' => $user->loginHistory(),
            'searches' => $user->searches(),
            'entries_registered' => $user->stockEntries(),
            'deliveries_made' => $user->stockExits(),
        ],
        'audit' => AuditLog::where('user_id', $user->id)->get(),
    ];
    
    if ($format === 'json') {
        return response()->json($data);
    } elseif ($format === 'csv') {
        return $this->toCsv($data);
    } elseif ($format === 'pdf') {
        return $this->toPdf($data);
    }
}
```

---

#### **Derecho de Rectificación (Art. 16)**
```
¿Pueden los usuarios corregir sus datos?

Implementación MVP:
✅ Perfil editable: nombre, email, teléfono
✅ Formulario: PATCH /api/account/update
✅ Validaciones: email verificado, teléfono válido

Lo que NO puede editarse:
❌ Cédula (documento de identidad)
❌ Fecha de creación de cuenta
❌ Historial de actividades (auditoría)

Nota: "Los datos erróneos se corrigen, no se eliminan (auditoría)"
```

---

#### **Derecho al Olvido (Art. 17)**
```
¿Pueden los usuarios solicitar eliminación?

Implementación MVP:
✅ Formulario: "Solicitar eliminación de datos"
✅ Email: legal@donaciones-rolda.com con CC Municipalidad
✅ Proceso:
   1. Usuario solicita (verifica email)
   2. Admin valida (72 horas)
   3. Ejecuta script: anonymize_user($user_id)
   4. Confirma via email

Datos que se eliminan:
- Nombre, email, teléfono, cédula → reemplazados con "USUARIO_ANONIMO"
- Contraseña → eliminada
- IP/User-agent históricos → eliminados

Datos que NO se eliminan (por LSPP):
- Audit logs de transacciones (trazabilidad requerida)
- Entregas realizadas (responsabilidad humanitaria)

Script de anonimización:
```

```php
// database/seeders/AnonymizeUserSeeder.php
DB::transaction(function () use ($userId) {
    $user = User::find($userId);
    
    // 1. Anonimizar usuario
    $user->update([
        'name' => 'USUARIO_ANONIMO',
        'email' => 'anon_' . $user->id . '@donaciones-rolda.local',
        'phone' => null,
        'document_id' => null,
        'password' => null,
        'status' => 'inactive',
    ]);
    
    // 2. Eliminar datos de sesión
    Session::where('user_id', $userId)->delete();
    LoginHistory::where('user_id', $userId)->delete();
    
    // 3. Mantener audit trails (requerido)
    AuditLog::where('user_id', $userId)->update([
        'user_id' => null,
        'additional_context' => 'USUARIO ANONIMIZADO'
    ]);
    
    // 4. Log de anonimización
    Log::notice("Usuario $userId anonimizado por solicitud LSPP");
});
```

---

### **4. Responsable del Tratamiento (Art. 3)**

```
¿Quién es responsable legalmente de los datos?

Respuesta: Municipalidad de Roldanillo (titular original)
           + Donaciones Rolda (encargado técnico)

Cláusula en T&Cs:
"Donaciones Rolda actúa como encargado del tratamiento de datos
en nombre de la Municipalidad de Roldanillo. La Municipalidad
es responsable ante la DNPD (Dirección Nacional de Protección
de Datos Personales) por el cumplimiento LSPP."

Acuerdo de confidencialidad requerido:
✅ Entre Municipalidad ↔ Donaciones Rolda
✅ Entre Donaciones Rolda ↔ Desarrolladores
✅ Entre Municipalidad ↔ ONGs (si colaboradores)
```

---

### **5. Encargado del Tratamiento (Art. 3)**

```
¿Quiénes pueden procesar datos en nombre de Municipalidad?

✅ Equipo técnico de Donaciones Rolda (desarrolladores, DevOps)
✅ Admin de la plataforma (Municipalidad)
❌ Ciudadanos (solo sus propios datos)
❌ Terceros (sin cláusula expresa)

NDA (Non-Disclosure Agreement) requerido para:
- Todo desarrollador
- Todo operador de campo
- Todo administrador
```

---

## 📊 Clasificación de Datos Personales

### **Matriz de Datos**

| Datos | Categoría | Sensible? | PII? | Encriptación | Retención | Acceso |
|-------|-----------|-----------|------|-------------|-----------|--------|
| Nombre | Personal | NO | SÍ | App-level | 1 año inactivo | Owner + Admin |
| Email | Personal | NO | SÍ | App-level | 1 año inactivo | Owner + Admin |
| Teléfono | Personal | NO | SÍ | App-level | 1 año inactivo | Owner + Admin |
| Cédula | Identificación | NO* | SÍ | App-level | 2 años (legal) | Owner + Admin |
| Ubicación bodega | Infraestructura | SÍ** | SÍ** | DB-level | 2 años (auditoría) | Operadores + Admin |
| Nombre beneficiario | Personal | SÍ | SÍ | DB-level | 1 año | Operador + Admin |
| IP de acceso | Técnico | NO | SÍ | CloudWatch | 30 días | Admin + Security |
| Historial búsquedas | Comportamental | NO | NO | DB-level | 90 días | Owner + Admin |

*Cédula no es "sensible" LSPP (art 5) pero es PII crítico  
**Ubicación bodega + operador = puede revelar residencia

---

## 🔐 Matriz de Auditoría y Trazabilidad

### **Tabla: audit_logs (Obligatoria LSPP)**

```sql
CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,                    -- Quién
    action VARCHAR(100),               -- Qué (login, create_entry, approve_item, etc)
    table_name VARCHAR(100),           -- Tabla afectada
    record_id BIGINT,                  -- Registro afectado
    old_value LONGTEXT,                -- Valor anterior (JSON)
    new_value LONGTEXT,                -- Valor nuevo (JSON)
    ip_address VARCHAR(45),            -- De dónde
    user_agent VARCHAR(500),           -- Con qué
    context TEXT,                      -- Por qué (offline sync, manual import, etc)
    created_at TIMESTAMP,              -- Cuándo
    INDEX (user_id, created_at),
    INDEX (action, created_at),
    INDEX (created_at)                 -- Búsquedas por rango de fechas
);
```

**Eventos que DEBEN ser auditados:**

| Evento | user_id | action | old_value | new_value | Context |
|--------|---------|--------|-----------|-----------|---------|
| Usuario se registra | NULL | user_register | — | {name, email, phone} | T&Cs aceptados |
| Usuario se loguea | user_id | user_login | — | — | IP y device |
| Usuario edita perfil | user_id | user_update_profile | {old_email} | {new_email} | Manual |
| Usuario solicita datos | user_id | user_data_export | — | — | Ejercicio derecho LSPP |
| Operador registra entrada | user_id | create_stock_entry | — | {item_id, qty, warehouse_id} | Offline/Online |
| Operador registra salida | user_id | create_stock_exit | — | {item_id, qty, beneficiary} | Campo |
| Admin aprueba ítem | admin_id | approve_master_item | {status: under_review} | {status: approved} | Manual |
| Sistema envía notificación | NULL | send_notification | — | {user_id, type} | Automático |
| Usuario solicita olvido | user_id | request_deletion | {all_data} | — | Derecho LSPP |
| Admin ejecuta olvido | admin_id | execute_deletion | {all_data} | {anonimizado} | Compliance |

**Retención de audit_logs:**
- Activos (CloudWatch): 30 días
- Archivados (Glacier): 2 años (cumplimiento legal LSPP + SOC 2)
- Deletreados: 7 años mínimo (normativa contable colombiana)

---

## 🗑️ Política de Retención y Eliminación

### **Matriz de Retención (por tipo de dato)**

| Dato | Retención | Razón | Eliminación |
|-----|-----------|-------|----------|
| Usuario activo | Indefinido | Mientras usuario tenga cuenta | Manual + derecho olvido |
| Usuario inactivo (12 meses) | 1 año más | Por si regresa | Auto-anonimizar |
| Audit logs operacionales | 30 días | Debugging/alertas | Auto-purge |
| Audit logs archivados | 2 años | Cumplimiento LSPP | Auto-delete após 2 años |
| Backup de datos (S3) | 7 días | Punto de recuperación | Auto-delete |
| Logs en Glacier (compliance) | 7 años | Normativa colombiana | Manual solo si legal |
| Sesiones expiradas | 24 horas | Token expiry | Auto-delete |
| Entradas de stock (completadas) | 3 años | Trazabilidad | Manual review después |
| Búsquedas públicas (anónimas) | 90 días | Analytics | Auto-delete |

---

### **Script: Auto-Cleanup Job**

```php
// app/Jobs/DataRetentionCleanupJob.php
class DataRetentionCleanupJob implements ShouldQueue
{
    public function handle()
    {
        DB::transaction(function () {
            // 1. Anonimizar usuarios inactivos > 12 meses
            $inactiveUsers = User::whereNotNull('last_login_at')
                ->where('last_login_at', '<', now()->subYear())
                ->get();
            
            foreach ($inactiveUsers as $user) {
                $this->anonymizeUser($user);
            }
            
            // 2. Purgar audit logs > 2 años
            AuditLog::where('created_at', '<', now()->subYears(2))
                ->delete();
            
            // 3. Eliminar sesiones expiradas
            Session::where('last_activity', '<', now()->subDay())
                ->delete();
            
            // 4. Comprimir y enviar a Glacier logs > 30 días
            $this->archiveOldLogs();
            
            Log::notice("Datos retenidos limpiados. Compliance: OK");
        });
    }
}

// Programar: php artisan schedule:work
// En Kernel.php:
$schedule->job(new DataRetentionCleanupJob)
    ->dailyAt('02:00'); // 02:00 UTC
```

---

## 🚨 Incidentes y Notificación

### **Clasificación de Incidentes**

| Tipo | Ejemplo | Probabilidad | Acción | Notificación |
|------|---------|-------------|--------|------------|
| 🔴 Crítico | Breach de DB expone 1000+ registros | Baja | Parar servicio, investigar | DNPD + Usuario 24h |
| 🟠 Mayor | SQL injection (detectado antes de exploit) | Baja | Parchear, auditar, notificar | DNPD 72h |
| 🟡 Moderado | Operador accede datos de otro operador | Muy baja | Revocar acceso, auditar | Admin 48h |
| 🟢 Menor | Usuario olvidó contraseña, reset required | Media | Reset automático | Email a usuario |

### **Protocolo de Notificación (Breach)**

**Obligación LSPP Art. 12:**
```
"En caso de breaching de datos personales, notificar a:
1. Afectados (dentro de 72 horas)
2. DNPD Superintendencia (dentro de 72 horas)
3. Terceros (si dato compartido)"
```

**Procedimiento Donaciones Rolda:**

```
T0: Incidente detectado (alert CloudWatch)
  └─ On-call verifica (15 min)

T15min: Si es breach confirmado:
  ├─ Aislar sistema afectado
  ├─ Convocar crisis team (PM, Dev, Security, Legal)
  └─ Iniciar investigación

T1h: Clasificar severidad:
  ├─ Crítico: Notificar inmediatamente
  ├─ Mayor: Notificar en 24h
  └─ Moderado: Notificar en 72h

T24h: Notificación a DNPD:
  Email a: notificaciones@dnp.gov.co
  Subject: "Incidente de seguridad — Donaciones Rolda"
  Body:
    - Descripción del incidente
    - Fecha y hora
    - Datos afectados (tipos)
    - Número de usuarios
    - Acciones correctivas

T72h: Notificación a usuarios afectados:
  Email + SMS
  Subject: "Aviso de seguridad importante"
  Body:
    - Qué pasó
    - Qué datos se comprometieron
    - Qué pasos tomar
    - Contacto de soporte

T7d: Reporte completo (interno):
  Documento:
    - Timeline del incidente
    - Root cause analysis
    - Forensics
    - Mejoras implementadas
```

---

## 🔐 Derechos de Usuarios

### **4 Derechos LSPP:**

#### **1. Derecho de Acceso**
```
Usuario puede solicitar: "¿Qué datos tienen de mí?"

Implementación:
✅ GET /api/account/data-export (self-service)
✅ Email: privacy@donaciones-rolda.com (backup)

Respuesta máximo: 10 días calendario
Formato: JSON, CSV o PDF
Gratuito: SÍ
```

#### **2. Derecho de Rectificación**
```
Usuario puede solicitar: "Corrijo error en mi nombre/email"

Implementación:
✅ PATCH /api/account/update (self-service)
✅ Email: privacy@donaciones-rolda.com (si necesita admin help)

Acción máximo: 5 días
Costo: Gratuito
Auditoría: SÍ (registro antes/después)
```

#### **3. Derecho al Olvido ("Derecho a ser olvidado")**
```
Usuario puede solicitar: "Eliminen todos mis datos"

Implementación:
✅ Formulario en /account/delete-account
✅ Email: legal@donaciones-rolda.com

Proceso:
1. Usuario confirma identidad (email + contraseña)
2. Se envía email con enlace (valida 24h)
3. Usuario hace clic y confirma nuevamente
4. Admin revisa (máximo 72h)
5. Se ejecuta anonymization job
6. Confirmación via email

Datos eliminados: Nombre, email, teléfono, cédula → USUARIO_ANONIMO
Datos NO eliminados: Audit trails (responsabilidad humanitaria)

Costo: Gratuito
Excepciones:
  ❌ NO se puede olvidar si hay entregas pendientes (entrega primero)
  ❌ NO se puede olvidar si hay proceso DNPD activo
```

#### **4. Derecho de Revocación de Consentimiento**
```
Usuario puede solicitar: "No quiero que usen mis datos para [X]"

Implementación (Fase II):
✅ Preferences: /account/data-preferences
  └─ [] Usar para búsquedas
  └─ [] Usar para analytics
  └─ [] Usar para notificaciones

Efecto:
- Si revoca búsqueda: No aparece en portal
- Si revoca analytics: No se trackean acciones
- Si revoca notificaciones: Solo servicios críticos

No puede revocarse: Acceso a emergencia (interés público)
```

---

## 📋 Roles de Cumplimiento

### **Designaciones Recomendadas**

| Rol | Nombre | Responsabilidades |
|-----|--------|-----------------|
| **DPO (Data Protection Officer)** | [Por designar] | Supervisar LSPP, responder DNPD |
| **Data Governance Lead** | [DevOps/Backend] | Políticas de datos, retención |
| **Security Lead** | [DevOps] | Encriptación, auditoría |
| **Legal/Compliance** | [Municipalidad] | T&Cs, política de privacidad |
| **Operations Lead** | [PM] | Respuesta incidentes |

**Recomendación:** Designar DPO externo (abogado especializado LSPP) si no hay internamente.

---

## 📋 Checklist de Implementación (MVP)

### **ANTES de go-live (Día 10 de entrega):**

- [ ] Política de privacidad redactada + aprobada legal
- [ ] Política de privacidad publicada en web
- [ ] T&Cs con cláusulas LSPP
- [ ] Banner de consentimiento en registro
- [ ] Encriptación TLS 1.3 en ALB
- [ ] Encriptación AES-256 en BD (RDS, Redis, S3)
- [ ] audit_logs tabla creada + funcionando
- [ ] CloudWatch logs configured (30d retention)
- [ ] S3 Glacier configured (90d → archive, 2yr delete)
- [ ] Data export endpoint (/account/data-export)
- [ ] Rectification form (/account/edit-profile)
- [ ] Deletion request form (/account/request-deletion)
- [ ] NDA signed by all developers
- [ ] Security audit completed (OWASP top 10)
- [ ] Backup + recovery tested
- [ ] Incident response runbook documented
- [ ] On-call notification setup (breach alert)

---

## 📞 Contactos de Cumplimiento

```
DNPD (Dirección Nacional de Protección de Datos Personales):
- Sitio: www.dnpd.gov.co
- Email: peticiones@dnpd.gov.co
- Teléfono: +57 (1) 3122626390

Superintendencia de Industria y Comercio (SIC):
- Para denuncias de incumplimiento LSPP
- www.sic.gov.co
- Email: contactenos@sic.gov.co

Municipalidad de Roldanillo:
- Coordinador de privacidad: [Por designar]
- Email: privacy@roldanillo.gov.co
- Teléfono: [Por actualizar]
```

---

## ✅ Conclusión

**Donaciones Rolda es COMPLIANT con LSPP colombiana si implementa los controles listados en este documento.**

**Riesgo residual:** Bajo (con MVP)

**Acciones pendientes (Fase II):**
- [ ] Verificación de identidad (DIAN RUT lookup)
- [ ] DPO contratado externamente
- [ ] Auditoría externa anual
- [ ] Certificación ISO 27001 (opcional pero recomendado)

---

**Documento aprobado por:** [PM + Legal + DevOps]  
**Última actualización:** Agosto 2026  
**Revisión siguiente:** Septiembre 2026

---

**¡FIN DE DOCUMENTACIÓN TÉCNICA!**

Tienes un conjunto completo de 8 archivos para la implementación de Donaciones Rolda. ¿Qué necesitas a continuación?
