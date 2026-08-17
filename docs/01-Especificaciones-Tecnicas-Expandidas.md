# 📋 Especificaciones Técnicas Expandidas — Donaciones Rolda

**Versión:** 1.0  
**Fecha:** Agosto 2026  
**Audiencia:** Comité técnico, municipalidad, donantes, equipo de desarrollo  
**Estado:** Preparado para desarrollo MVP (7-14 días)

---

## 📑 Índice

1. [Visión General](#visión-general)
2. [Stack Tecnológico](#stack-tecnológico)
3. [Módulos del Sistema](#módulos-del-sistema)
4. [Funciones por Módulo](#funciones-por-módulo)
5. [Flujos de Negocio Clave](#flujos-de-negocio-clave)
6. [Integraciones Futuras](#integraciones-futuras)

---

## 🎯 Visión General

**Donaciones Rolda** es una plataforma colaborativa diseñada para gestionar la disponibilidad de medicamentos, alimentos e insumos durante emergencias o catástrofes. Su filosofía es:

✅ **Liviana y rápida** — Funciona con conexión lenta/inestable  
✅ **Resiliente** — Offline-first con sincronización automática  
✅ **Segura** — Protección de datos privados con auditoría completa  
✅ **Escalable** — Preparada para múltiples municipalidades  
✅ **Colaborativa** — Públicos + privados trabajando en tiempo real  

**Actores:**
- **Ciudadanos**: Buscan insumos urgentes
- **Operadores de Campo**: Registran entradas/salidas en bodegas
- **Líderes Comunitarios**: Coordinan entregas, supervisan almacenes
- **Administradores**: Aprueban ítems, gestiona usuarios, monitorean sistema
- **Donantes/ONGs**: Contribuyen recursos y visibilidad

---

## 🛠️ Stack Tecnológico

### Backend
- **Framework:** Laravel 11/12 (PHP 8.3+)
- **Base de Datos:** MySQL 8.0 / MariaDB 10.11
- **Caché:** Redis 7.0
- **Queue:** Laravel Queue (Redis driver)
- **Notificaciones:** Laravel Notification Channels (escalable)

### Frontend
- **HTML/Blade:** Laravel Blade templating
- **CSS:** Tailwind CSS 3.x
- **JavaScript:** Alpine.js 3.x
- **PWA:** Service Worker nativo + IndexedDB (Dexie.js)

### Seguridad
- **Antibot:** Cloudflare Turnstile
- **Autenticación:** Laravel Sanctum (session + API tokens)
- **Encriptación:** AES-256 (datos sensibles), TLS 1.3 (tránsito)

### Infraestructura
- **Servidor:** AWS (EC2/App Runner)
- **Almacenamiento:** S3 (backups, reportes)
- **CDN:** CloudFront (assets estáticos)
- **DNS:** Route 53
- **Monitoreo:** CloudWatch + SNS

---

## 📦 Módulos del Sistema

### **Módulo 1: Portal Público de Búsqueda**

**Objetivo:** Ciudadanos buscan insumos disponibles sin autenticación.

**Funcionalidades:**
- Búsqueda por palabra clave (sugerencias en tiempo real)
- Filtros por zona geográfica, categoría, disponibilidad
- Visualización de tarjetas con semáforo de disponibilidad (🟢🟡🔴⚫)
- Mapa interactivo de bodegas (Leaflet.js + OpenStreetMap)
- Modal de contacto protegido por Turnstile
- Compartir ubicación por WhatsApp/llamada

**Endpoints REST:**
- `GET /api/public/search` — Buscar insumos
- `GET /api/public/warehouses` — Listar bodegas
- `POST /api/public/contact-unlock` — Validar Turnstile y obtener contacto

**Vistas:**
- `public.search` — Página principal
- `public.results` — Tarjetas de resultados
- `public.map` — Mapa interactivo
- `public.contact-modal` — Modal de desbloqueo Turnstile

---

### **Módulo 2: Autenticación, Registro y Roles**

**Objetivo:** Gestionar usuarios, permisos y acceso según rol.

**Roles:**
| Rol | Permisos | Caso de Uso |
|-----|----------|-----------|
| `admin` | Todo: usuarios, ítems, bodegas, reportes, auditoría | Municipalidad / Coordinador central |
| `acopio_operator` | Registrar entradas/salidas, buscar en catálogo, sincronizar offline | Operador en bodega |
| `field_leader` | Coordinar operadores, ver reportes simples, validar entregas | Líder comunitario / ONG |
| `public` | Solo portal búsqueda público, sin autenticación | Ciudadano |

**Funcionalidades:**
- Login con email + contraseña
- Registro híbrido: manual + con código de organización (fast-path)
- Panel de aprobación de usuarios pendientes (admin)
- Gestión de permisos por organización
- Cambio de contraseña, recuperación de cuenta
- Auditoría de login (IP, hora, dispositivo)

**Endpoints REST:**
- `POST /api/auth/login` — Iniciar sesión
- `POST /api/auth/register` — Registro nuevo usuario
- `POST /api/auth/logout` — Cerrar sesión
- `GET /api/auth/me` — Datos del usuario autenticado
- `POST /api/admin/users/approve/:id` — Aprobar usuario pendiente
- `PATCH /api/auth/change-password` — Cambiar contraseña

**Vistas:**
- `auth.login` — Formulario de login
- `auth.register` — Registro con/sin código de org
- `admin.users.pending` — Panel de aprobaciones
- `admin.users.manage` — Gestión de usuarios activos

---

### **Módulo 3: Gestión de Inventario (Operativo)**

**Objetivo:** Operadores registran entradas y salidas de insumos.

**Funcionalidades:**

#### 3.1 Registro de Entrada
- Selección de insumo (búsqueda + sugerencias del catálogo)
- Opción de solicitar nuevo ítem si no existe
- Ingreso de cantidad, unidad de medida, lote, vencimiento
- Marcado de cadena de frío (si aplica)
- Guardado automático con sincronización offline
- Foto de entrada (opcional, mejora trazabilidad)

#### 3.2 Registro de Salida
- Selección de insumo disponible
- Cantidad a despachar
- Destino/beneficiario (nombre, zona, contacto)
- Razón de entrega (donativo, venta subsidiada, emergencia)
- Firma digital o confirmación de operador
- Actualización automática de stock

#### 3.3 Recepción y Confirmación
- Notificación cuando llega insumo esperado
- Confirmación manual de llegada física
- Cambio de estado de `pending_arrival` a `available`
- Registro de daños o discrepancias

#### 3.4 Vista de Bodega
- Dashboard con stock actual por bodega
- Filtros por categoría, estado, vencimiento próximo
- Gráfico simple de niveles de inventario
- Alertas de baja existencia

**Endpoints REST:**
- `POST /api/stock/entries` — Crear entrada
- `POST /api/stock/exits` — Crear salida
- `GET /api/stock/warehouse/:id` — Ver stock de bodega
- `PATCH /api/stock/entry/:id/confirm` — Confirmar llegada
- `POST /api/stock/entries/request-new-item` — Solicitar ítem nuevo

**Vistas:**
- `operator.entry-form` — Formulario de entrada
- `operator.exit-form` — Formulario de salida
- `operator.warehouse-dashboard` — Dashboard de bodega
- `operator.sync-status` — Estado de sincronización offline

---

### **Módulo 4: Catálogo Maestro y Aprobación**

**Objetivo:** Administradores aprueban ítems nuevos y consolidan duplicados.

**Funcionalidades:**
- Cola de ítems en revisión (`under_review`)
- Vista de solicitudes con detalles del operador y contexto
- Edición: nombre, categoría, unidad de medida
- Consolidación: marcar como duplicado + vincular a ítem existente
- Aprobación: cambio a `approved` + notificación a operador
- Rechazamiento: con comentario de motivo

**Endpoints REST:**
- `GET /api/admin/master-items/pending` — Ítems en revisión
- `PATCH /api/admin/master-items/:id/approve` — Aprobar ítem
- `PATCH /api/admin/master-items/:id/consolidate` — Marcar como duplicado
- `PATCH /api/admin/master-items/:id/reject` — Rechazar con motivo

**Vistas:**
- `admin.items-review.queue` — Cola de revisión
- `admin.items-review.detail` — Detalle de ítem + historial de solicitudes
- `admin.master-items.index` — Catálogo aprobado

---

### **Módulo 5: Alertas, Auditoría y Reportes**

**Objetivo:** Monitoreo, trazabilidad y análisis del sistema.

**Funcionalidades:**

#### 5.1 Centro de Notificaciones
- Notificaciones internas por rol
- Alertas críticas: ítem próximo a vencer, bodega sin operador, stock bajo
- Notificaciones de aprobación de ítems
- Historial de notificaciones por usuario

#### 5.2 Auditoría de Accesos
- Registro de toda transacción (quién, qué, cuándo)
- Tabla `audit_logs` con: usuario, acción, tabla afectada, valor_anterior, valor_nuevo
- Filtros por rango de fechas, usuario, tipo de acción
- Exportación para cumplimiento LSPP

#### 5.3 Reportes Operacionales
- **Reporte de Trazabilidad:** Entrada → Salida de cada insumo
- **Reporte de Vencimientos:** Próximos a caducar (7, 14, 30 días)
- **Reporte de Stock:** Niveles actuales por bodega, categoría
- **Reporte de Usuarios:** Actividad, permisos, último acceso
- **Reporte de Transacciones:** Entradas/salidas por período

#### 5.4 Exportación de Datos
- CSV para Excel (reporte de stock, trazabilidad)
- PDF para imprimir (certificados de bodega, manifiestos)

**Endpoints REST:**
- `GET /api/notifications` — Mis notificaciones
- `GET /api/audit-logs` — Historial de auditoría (admin)
- `GET /api/reports/traceability` — Reporte de trazabilidad
- `GET /api/reports/expiring` — Ítems próximos a vencer
- `GET /api/reports/stock` — Estado actual de stock
- `POST /api/reports/export` — Exportar en CSV/PDF

**Vistas:**
- `dashboard.notifications` — Centro de alertas
- `admin.audit-logs` — Log de accesos
- `reports.index` — Panel de reportes
- `reports.detail` — Detalle de reporte con gráficos

---

### **Módulo 6: Administración de Bodegas y Organizaciones**

**Objetivo:** Configurar y mantener la red de centros de acopio.

**Funcionalidades:**
- CRUD de bodegas (crear, editar, eliminar)
- Asignación de operadores a bodega
- Configuración de horarios de atención
- Contactos de emergencia
- Capacidad máxima de almacenamiento
- Historial de cambios

**Endpoints REST:**
- `GET /api/warehouses` — Listar todas
- `POST /api/warehouses` — Crear bodega
- `PATCH /api/warehouses/:id` — Editar bodega
- `POST /api/warehouses/:id/assign-operator` — Asignar operador

**Vistas:**
- `admin.warehouses.index` — Listado de bodegas
- `admin.warehouses.form` — Crear/editar bodega
- `admin.warehouses.map` — Mapa de ubicaciones

---

## 🔄 Funciones por Módulo (Detalle Operativo)

### **MÓDULO 1: Portal Público**

| Función | Descripción | Entrada | Salida |
|---------|-------------|---------|--------|
| `searchItems()` | Búsqueda full-text en insumos disponibles | Keyword, zona, categoría | Array de resultados |
| `getAvailability()` | Calcula semáforo (🟢🟡🔴⚫) por cantidad | stock_quantity, min_threshold | Severity enum |
| `reverseGeocode()` | Convierte lat/lng a zona geográfica | latitude, longitude | geographic_zone |
| `generateWhatsAppLink()` | Crea deep-link de WhatsApp protegido | warehouse_id, item_id, turnstile_token | URL |
| `logPublicAccess()` | Registra visitas anónimas para analytics | session_id, search_query, result_count | audit_log entry |

---

### **MÓDULO 2: Autenticación**

| Función | Descripción | Entrada | Salida |
|---------|-------------|---------|--------|
| `registerUser()` | Crea usuario nuevo (pendiente aprobación) | email, name, phone, document_id, org_code | User (status=pending) |
| `approveUser()` | Admin aprueba y asigna rol | user_id, role, organization_id | User (status=active) |
| `loginUser()` | Valida credenciales, crea sesión segura | email, password | Session token + User data |
| `auditLogin()` | Registra intentos de login (éxito/fallo) | user_id, ip_address, user_agent | audit_log entry |
| `validateAccessCode()` | Verifica código de organización para fast-path | access_code | Organization data |

---

### **MÓDULO 3: Inventario**

| Función | Descripción | Entrada | Salida |
|---------|-------------|---------|--------|
| `createStockEntry()` | Registra entrada de insumo | item_id, quantity, warehouse_id, expiry_date, lot_number | StockEntry (status=pending_arrival) |
| `confirmArrival()` | Confirma llegada física | stock_entry_id, confirmed_by_user | Cambio status → available |
| `createStockExit()` | Registra salida/despacho | item_id, quantity, warehouse_id, destination, reason | StockExit (status=completed) |
| `updateStockQuantity()` | Ajusta cantidad disponible en caché + DB | warehouse_id, item_id, delta_quantity | New quantity |
| `checkExpiryAlert()` | Identifica ítems próximos a vencer | days_threshold (7, 14, 30) | Array de alertas |
| `requestNewItem()` | Crea registro maestro en revisión | item_name, unit, category, operator_id | MasterItem (status=under_review) |

---

### **MÓDULO 4: Aprobación Maestro**

| Función | Descripción | Entrada | Salida |
|---------|-------------|---------|--------|
| `approveMasterItem()` | Aprueba ítem nuevo + cambia stock asociado | master_item_id, category_id_final | MasterItem (status=approved) + notificación |
| `consolidateDuplicates()` | Marca ítem como duplicado de otro | original_item_id, duplicate_item_id | Vínculo + auditoría |
| `rejectMasterItem()` | Rechaza solicitud con motivo | master_item_id, rejection_reason | MasterItem (status=rejected) + notificación |
| `getPendingApprovals()` | Lista ítems en revisión | — | Paginated array |

---

### **MÓDULO 5: Auditoría y Reportes**

| Función | Descripción | Entrada | Salida |
|---------|-------------|---------|--------|
| `logAuditTrail()` | Registra cualquier cambio de datos sensibles | user_id, action, table, old_value, new_value | AuditLog entry |
| `getTraceability()` | Historial completo de un insumo (entrada → salida) | item_id, warehouse_id | Array de transacciones |
| `generateExpiryReport()` | Reporte de vencimientos próximos | days_threshold | CSV/PDF |
| `generateStockReport()` | Snapshot actual de inventario por bodega | warehouse_id, date_range | CSV/PDF |
| `exportAuditLogs()` | Exporta logs para auditoría externa (LSPP) | date_from, date_to | CSV |

---

### **MÓDULO 6: Administración**

| Función | Descripción | Entrada | Salida |
|---------|-------------|---------|--------|
| `createWarehouse()` | Registra nuevo centro de acopio | name, address, lat, lng, contact_person | Warehouse |
| `assignOperatorToWarehouse()` | Vincula operador a bodega | operator_id, warehouse_id | Assignment record |
| `createOrganization()` | Registra ONG/ente público | org_name, type, access_code | Organization |
| `deactivateUser()` | Desactiva acceso sin eliminar datos | user_id, reason | User (status=inactive) |

---

## 🔀 Flujos de Negocio Clave

### **Flujo 1: Ciudadano Busca Insumo Urgente**

```
1. Ciudadano entra a portal público
   ↓
2. Busca por palabra clave (ej: "antibiótico")
   ↓
3. Sistema busca en BD y calcula semáforos en tiempo real
   ↓
4. Muestra resultados con:
   - Nombre del insumo
   - Ubicación de bodega (mapa)
   - Semáforo de disponibilidad (🟢 Alta / 🟡 Media / 🔴 Baja / ⚫ Agotado)
   - Datos de contacto encargado (protegido)
   ↓
5. Ciudadano hace clic "Contactar Encargado"
   ↓
6. Modal aparece: "Valida que no eres bot" (Turnstile)
   ↓
7. Si valida exitosamente:
   - Muestra datos de contacto (nombre, teléfono)
   - Botón "Abrir en WhatsApp" (deep-link pre-redactado)
   ↓
8. Ciudadano abre WhatsApp y contacta directamente
   ↓
9. Encargado coordina entrega
```

**Datos enviados a auditoría:**
- session_id, search_query, ip_address, resultado_clickeado, turnstile_validated

---

### **Flujo 2: Operador Registra Entrada de Insumo**

```
1. Operador abre app PWA en tablet/teléfono
   ↓
2. Selecciona bodega (dropdown pre-filtrado)
   ↓
3. Hace clic en "+ Registrar Entrada"
   ↓
4. Formulario de entrada aparece:
   - Búsqueda dinámica de insumo (sugerencias del catálogo)
   - Si no aparece → "Solicitar nuevo ítem"
   - Cantidad + unidad de medida
   - Número de lote (opcional)
   - Fecha de vencimiento (date picker)
   - ¿Requiere cadena de frío? (checkbox)
   - Foto de caja/documentación (opcional)
   ↓
5. Operador hace clic "Guardar"
   ↓
6A. Si ONLINE:
    - Envía datos a API
    - API valida y crea StockEntry (status = pending_arrival)
    - Calcula nuevo semáforo
    - Invalida caché de Redis
    - Notificación interna: "Entrada registrada por [Operador]"
   ↓
6B. Si OFFLINE (sin conexión):
    - Guarda en IndexedDB localmente
    - Muestra badge "⏳ Pendiente sincronizar"
    - Crea entrada en cola de sync
   ↓
7. Cuando vuelve conexión (ONLINE):
    - Service Worker detecta cambio de estado
    - Ejecuta cola de sincronización automática
    - Marca como "✓ Sincronizado"
   ↓
8. Admin ve notificación: "Entrada llegará a Bodega X"
   ↓
9. Cuando llega físicamente:
    - Operador confirma "Llegada confirmada"
    - Stock cambia a `available`
    - Semáforo se actualiza automáticamente
```

**Datos enviados a auditoría:**
- user_id, warehouse_id, item_id, quantity, timestamp, online/offline

---

### **Flujo 3: Operador Solicita Ítem Nuevo**

```
1. Operador busca insumo en catálogo
   ↓
2. No lo encuentra
   ↓
3. Hace clic "➕ Solicitar Creación de Nuevo Ítem"
   ↓
4. Modal aparece:
   - Nombre del insumo (obligatorio)
   - Categoría (dropdown)
   - Unidad de medida (dropdown: cajas, kg, frascos, etc)
   - ¿Requiere cadena de frío?
   - Justificación breve (opcional)
   ↓
5. Operador envía solicitud
   ↓
6. Sistema crea MasterItem con status = "under_review"
   ↓
7. Stock entry asociado queda con status = "pending_arrival"
   ↓
8. Admin recibe notificación:
    "Nuevo ítem en revisión: [Nombre]"
    "Solicitado por: [Operador] en Bodega [X]"
   ↓
9. Admin va a Panel > Ítems en Revisión
   ↓
10. Revisa solicitud:
    - Validar que no sea duplicado
    - Editar nombre/categoría si es necesario
    - Asignar categoría final
   ↓
11. Admin hace clic "Aprobar y Asignar"
   ↓
12. Sistema:
    - MasterItem → status = "approved"
    - StockEntry asociado → status = "available"
    - Redis caché se invalida
    - Notificación a operador: "Tu ítem fue aprobado"
   ↓
13. Ítem aparece ahora en búsqueda pública y catálogo
```

**Datos enviados a auditoría:**
- user_id (operador), master_item_name, admin_id (aprobador), cambios_realizados

---

### **Flujo 4: Administrador Monitorea Vencimientos**

```
1. Admin abre Dashboard
   ↓
2. Ve widget "⚠️ Ítems próximos a vencer"
   ↓
3. Hace clic para ver detalles
   ↓
4. Sistema corre query:
   SELECT * FROM stock_entries
   WHERE expiry_date BETWEEN NOW() AND NOW() + INTERVAL 7 DAY
   ORDER BY expiry_date ASC
   ↓
5. Muestra tabla con:
   - Item name
   - Quantity
   - Warehouse
   - Days until expiry
   - ¿Cold chain required?
   ↓
6. Admin puede:
   - Ver historial de este ítem (trazabilidad)
   - Marcar para retiro de bodega
   - Notificar operador de bodega
   ↓
7. Admin hace clic "Generar Orden de Retiro"
   ↓
8. Sistema:
    - Crea ExpiryAlert record
    - Notifica operador de bodega
    - Crea tarea en su cola
   ↓
9. Operador de bodega:
    - Recibe notificación
    - Confirma retiro/destrucción
    - Carga foto de comprobante
   ↓
10. Auditoría registra: quién retiró, cuándo, comprobante
```

**Datos enviados a auditoría:**
- admin_id, item_id, warehouse_id, action (alert_created, removal_confirmed)

---

## 🔮 Integraciones Futuras

### **Fase II: Notificaciones Multicanal**

El sistema está preparado (decoupled vía Laravel Notification Channels) para agregar:

- **Telegram:** Alertas a admin en canal privado
- **WhatsApp Business API:** Notificaciones proactivas a líderes comunitarios
- **SMS:** Alertas críticas (sin conexión a internet)
- **Email:** Reportes diarios/semanales

**Implementación mínima:**
```php
// app/Notifications/ItemUnderReviewNotification.php
public function via($notifiable): array
{
    return ['database']; // Phase 1
    // return ['database', 'telegram', 'whatsapp']; // Phase 2
}
```

### **Fase III: Análisis Predictivo**

- **Pronóstico de demanda:** ML para predecir qué insumos se necesitarán
- **Optimización de rutas:** Algoritmo de entrega más eficiente
- **Clustering de beneficiarios:** Identificar zonas críticas

---

## 📞 Contacto y Soporte

**¿Dudas sobre las especificaciones?**
- Comité técnico: revisar DDL y endpoints
- Municipalidad: enfocarse en módulos 1 (portal público) y 5 (auditoría)
- ONGs/Donantes: enfocarse en módulos 3 (operativo) e impacto de flujo 1

**Próximos pasos:** Revisar modelo de datos (archivo 02) y propuestas de funciones adicionales (archivo 03).
