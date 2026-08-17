# 🗄️ Modelo de Datos (MER) y DDL Completo — Donaciones Rolda

**Versión:** 1.0  
**Base de Datos:** MySQL 8.0 / MariaDB 10.11  
**Fecha:** Agosto 2026

---

## 📊 Diagrama Entidad-Relación (Conceptual)

```
┌─────────────────────────────────────────────────────────────┐
│                       DONACIONES ROLDA                       │
└─────────────────────────────────────────────────────────────┘

                    ┌──────────────────┐
                    │  GEOGRAPHIC_ZONES │
                    │  (Municipios,     │
                    │   comunas, etc)   │
                    └────────┬──────────┘
                             │
                    ┌────────▼──────────┐
                    │    WAREHOUSES     │
                    │  (Centros acopio) │
                    └────────┬──────────┘
                             │
          ┌──────────────────┼──────────────────┐
          │                  │                  │
    ┌─────▼─────┐      ┌─────▼──────┐   ┌─────▼──────┐
    │   USERS   │◄─────┤ORGANIZATIONS│   │STOCK_ENTRIES│
    │(Operadores)│      │ (ONG, Muni) │   │  (Entradas) │
    └─────┬─────┘      └─────┬──────┘   └─────┬──────┘
          │                  │               │
          └──────────┬───────┴───────────────┘
                     │
             ┌───────▼────────────┐
             │  AUDIT_LOGS        │
             │ (Trazabilidad)     │
             └────────────────────┘

    ┌──────────────┐      ┌──────────────┐
    │  CATEGORIES  │◄─────│  MASTER_ITEMS│
    │              │      │  (Catálogo)  │
    └──────────────┘      └──────┬───────┘
                                 │
                         ┌───────▼────────────┐
                         │  STOCK_EXITS       │
                         │  (Salidas/Entregas)│
                         └────────────────────┘
```

---

## 🔑 Descripción de Tablas Principales

### **1. GEOGRAPHIC_ZONES** — Divisiones Geográficas

Almacena municipios, comunas, corregimientos, veredas y barrios para georreferenciar bodegas.

```sql
CREATE TABLE geographic_zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipality VARCHAR(100) NOT NULL DEFAULT 'Roldanillo',
    zone_type ENUM('municipio', 'comuna', 'corregimiento', 'vereda', 'barrio') NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) UNIQUE NULL,
    parent_zone_id BIGINT UNSIGNED NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_zone_id) REFERENCES geographic_zones(id) ON DELETE SET NULL,
    INDEX idx_zone_lookup (municipality, zone_type, name),
    INDEX idx_active_zones (is_active, municipality)
);
```

**Ejemplo de datos:**
- Roldanillo (municipio)
  - Zona Urbana (comuna)
    - Barrio Centro
    - Barrio El Salado
  - Corregimiento Guayabal
    - Vereda La Osa

---

### **2. ORGANIZATIONS** — ONGs, Municipalidad, Entidades Públicas/Privadas

Agrupa usuarios y define permisos por organización colaboradora.

```sql
CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    org_type ENUM('municipalidad', 'ong', 'empresa_privada', 'comite_emergencias', 'otro') NOT NULL,
    description TEXT NULL,
    access_code VARCHAR(32) UNIQUE NULL, -- Código para registro rápido de voluntarios
    contact_email VARCHAR(150) NULL,
    contact_phone VARCHAR(20) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_org_type (org_type, is_active),
    INDEX idx_access_code (access_code)
);
```

---

### **3. USERS** — Usuarios del Sistema

Operadores de campo, líderes comunitarios, administradores.

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    document_id VARCHAR(30) UNIQUE NOT NULL, -- Cédula, pasaporte, etc
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'acopio_operator', 'field_leader') NOT NULL DEFAULT 'acopio_operator',
    status ENUM('pending', 'active', 'inactive', 'rejected') NOT NULL DEFAULT 'pending',
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL, -- IPv4 o IPv6
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    profile_photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    INDEX idx_user_status (status, role),
    INDEX idx_user_email (email),
    INDEX idx_user_document (document_id)
);
```

**Enums definidos en código PHP (PHP 8.2+):**
```php
enum UserRole: string {
    case ADMIN = 'admin';
    case ACOPIO_OPERATOR = 'acopio_operator';
    case FIELD_LEADER = 'field_leader';
}

enum UserStatus: string {
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case REJECTED = 'rejected';
}
```

---

### **4. WAREHOUSES** — Centros de Acopio / Bodegas

Ubicaciones físicas donde se almacenan insumos.

```sql
CREATE TABLE warehouses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    geographic_zone_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    contact_person_name VARCHAR(150) NOT NULL,
    contact_phone VARCHAR(20) NOT NULL,
    contact_email VARCHAR(150) NULL,
    max_capacity_units INT UNSIGNED NULL, -- Capacidad máxima (opcional)
    operating_hours JSON NULL, -- {"mon_open": "08:00", "mon_close": "18:00", ...}
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (geographic_zone_id) REFERENCES geographic_zones(id),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    INDEX idx_warehouse_location (geographic_zone_id, is_active),
    INDEX idx_warehouse_active (is_active)
);
```

---

### **5. CATEGORIES** — Categorías de Insumos

Medicinas, alimentos, herramientas, etc.

```sql
CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    icon_class VARCHAR(50) DEFAULT 'fa-box', -- FontAwesome class
    description TEXT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_categories (is_active, sort_order)
);
```

**Ejemplo de datos:**
- Medicinas (💊)
- Alimentos (🍞)
- Insumos Médicos (🩺)
- Herramientas (🔧)
- Artículos de Higiene (🧼)

---

### **6. MASTER_ITEMS** — Catálogo Maestro de Insumos

Registro único de cada tipo de producto. Operadores crean solicitudes; admin aprueba.

```sql
CREATE TABLE master_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL, -- Quién solicitó (si es de operador)
    name VARCHAR(150) NOT NULL,
    unit_of_measure VARCHAR(30) NOT NULL, -- cajas, kg, frascos, unidades, litros, etc
    description TEXT NULL,
    requires_cold_chain BOOLEAN DEFAULT FALSE,
    status ENUM('approved', 'under_review', 'rejected') NOT NULL DEFAULT 'approved',
    rejection_reason TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_master_item_status (status),
    INDEX idx_master_item_category (category_id),
    INDEX idx_master_item_search (name)
);
```

---

### **7. STOCK_ENTRIES** — Registros de Entradas a Bodega

Cada vez que llega un insumo, se crea un registro.

```sql
CREATE TABLE stock_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    master_item_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    registered_by_user_id BIGINT UNSIGNED NOT NULL,
    confirmed_by_user_id BIGINT UNSIGNED NULL,
    quantity INT UNSIGNED NOT NULL,
    lot_number VARCHAR(50) NULL,
    expiry_date DATE NULL,
    received_date DATE NULL, -- Fecha de llegada física real
    status ENUM('pending_arrival', 'available', 'expired', 'withdrawn') NOT NULL DEFAULT 'pending_arrival',
    notes TEXT NULL,
    photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (master_item_id) REFERENCES master_items(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (registered_by_user_id) REFERENCES users(id),
    FOREIGN KEY (confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_stock_status (warehouse_id, status),
    INDEX idx_stock_expiry (expiry_date),
    INDEX idx_stock_item (master_item_id, warehouse_id)
);
```

---

### **8. STOCK_EXITS** — Registros de Salidas / Entregas

Cuando se despacha un insumo a un beneficiario o destino.

```sql
CREATE TABLE stock_exits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_entry_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    released_by_user_id BIGINT UNSIGNED NOT NULL,
    received_by_name VARCHAR(150) NULL, -- Nombre de quien recibe
    destination_zone_id BIGINT UNSIGNED NULL,
    destination_description TEXT NULL, -- Descripción libre (ej: "Albergue temporal")
    exit_reason ENUM('donation', 'subsidized_sale', 'emergency_assistance', 'other') NOT NULL,
    quantity_released INT UNSIGNED NOT NULL,
    release_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    signed_by_receiver BOOLEAN DEFAULT FALSE,
    signature_path VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_entry_id) REFERENCES stock_entries(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (released_by_user_id) REFERENCES users(id),
    FOREIGN KEY (destination_zone_id) REFERENCES geographic_zones(id) ON DELETE SET NULL,
    INDEX idx_exit_date (release_date),
    INDEX idx_exit_warehouse (warehouse_id, release_date)
);
```

---

### **9. AUDIT_LOGS** — Registro Completo de Transacciones

Cumplimiento LSPP: quién, qué, cuándo, dónde.

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL, -- 'create', 'update', 'delete', 'approve', 'login', etc
    table_name VARCHAR(100) NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    old_value LONGTEXT NULL, -- JSON con valores anteriores (si es update)
    new_value LONGTEXT NULL, -- JSON con valores nuevos
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    context TEXT NULL, -- Info adicional (ej: "Offline sync", "Manual import")
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id, created_at),
    INDEX idx_audit_action (action, created_at),
    INDEX idx_audit_table (table_name, record_id, created_at),
    INDEX idx_audit_date (created_at)
);
```

---

### **10. INTERNAL_NOTIFICATIONS** — Centro de Notificaciones

Alertas internas por rol/usuario (base para Telegram/WhatsApp en Fase II).

```sql
CREATE TABLE internal_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL, -- 'item_under_review', 'stock_low', 'expiry_alert', etc
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    related_resource_type VARCHAR(50) NULL, -- 'master_item', 'warehouse', 'stock_entry'
    related_resource_id BIGINT UNSIGNED NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notification_user (user_id, is_read, created_at)
);
```

---

### **11. WAREHOUSE_ASSIGNMENTS** — Asignación de Operadores a Bodegas

Relación muchos-a-muchos con fecha de inicio/fin.

```sql
CREATE TABLE warehouse_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    warehouse_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unassigned_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_assignment (user_id, warehouse_id, is_active),
    INDEX idx_active_assignments (warehouse_id, is_active)
);
```

---

### **12. EXPIRY_ALERTS** — Alertas de Vencimiento

Registra cuándo se detectó un vencimiento próximo y qué acción se tomó.

```sql
CREATE TABLE expiry_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_entry_id BIGINT UNSIGNED NOT NULL,
    alert_type ENUM('7_days', '14_days', '30_days', 'expired') NOT NULL,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notified_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    resolution_action VARCHAR(100) NULL, -- 'used', 'discarded', 'returned'
    resolution_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_entry_id) REFERENCES stock_entries(id) ON DELETE CASCADE,
    INDEX idx_expiry_status (resolved_at, alert_type)
);
```

---

## 🔐 Vistas Seguras (Para Cumplimiento LSPP)

### **Vista: user_activity_summary**

Permite a admin ver actividad sin exponer datos sensibles de beneficiarios.

```sql
CREATE VIEW user_activity_summary AS
SELECT
    u.id,
    u.name,
    u.role,
    COUNT(DISTINCT al.id) as total_actions,
    MAX(al.created_at) as last_action_at,
    COUNT(DISTINCT CASE WHEN al.action = 'login' THEN 1 END) as login_count
FROM users u
LEFT JOIN audit_logs al ON u.id = al.user_id
GROUP BY u.id
ORDER BY last_action_at DESC;
```

---

## 📋 Índices de Desempeño

Tabla de índices agregados para búsquedas rápidas:

| Tabla | Índice | Columnas | Propósito |
|-------|--------|----------|----------|
| master_items | idx_master_item_status | status | Búsqueda rápida de ítems en revisión |
| master_items | idx_master_item_search | name | Full-text search de productos |
| stock_entries | idx_stock_expiry | expiry_date | Alertas de vencimiento |
| stock_entries | idx_stock_status | warehouse_id, status | Dashboard de bodega |
| stock_exits | idx_exit_date | release_date | Reportes de entregas |
| audit_logs | idx_audit_date | created_at | Queries de rango de fechas (LSPP) |
| users | idx_user_email | email | Login rápido |
| geographic_zones | idx_zone_lookup | municipality, zone_type, name | Filtros de zona |

---

## 🔄 Transacciones Críticas (ACID)

### **Transacción 1: Confirmar Llegada de Insumo**

```sql
START TRANSACTION;
  -- 1. Actualizar stock_entry a 'available'
  UPDATE stock_entries 
  SET status = 'available', received_date = NOW(), confirmed_by_user_id = ? 
  WHERE id = ?;

  -- 2. Registrar en auditoría
  INSERT INTO audit_logs (user_id, action, table_name, record_id, new_value)
  VALUES (?, 'confirm_arrival', 'stock_entries', ?, JSON_OBJECT('status', 'available'));

  -- 3. Crear notificación interna
  INSERT INTO internal_notifications (user_id, type, title, message)
  SELECT warehouse_id_from_context, 'stock_available', 'Insumo disponible', 
         CONCAT('El insumo ha llegado a la bodega');

  -- 4. Invalidar caché Redis
  COMMIT;
```

---

## 🛡️ Encriptación de Campos Sensibles

Campos que se encriptan a nivel de aplicación (AES-256):

| Campo | Tabla | Razón |
|-------|-------|-------|
| document_id | users | PII sensitivo |
| phone | users | PII sensitivo |
| email | users | PII sensitivo |
| contact_phone | warehouses | PII sensitivo |
| received_by_name | stock_exits | Beneficiario potencialmente vulnerable |

**Implementación en Laravel:**

```php
// app/Models/User.php
use Illuminate\Database\Eloquent\Casts\Encrypted;

class User extends Model {
    protected $casts = [
        'document_id' => Encrypted::class,
        'phone' => Encrypted::class,
        'email' => Encrypted::class,
    ];
}
```

---

## 📊 Estadísticas de Diseño

| Métrica | Valor | Justificación |
|---------|-------|---------------|
| Tablas | 12 principales | Normalización 3NF |
| Relaciones | 20+ FK | Integridad referencial |
| Índices | 25+ | Optimización de consultas |
| Vistas | 2+ | Seguridad de datos |
| Max registros esperados (año 1) | stock_entries: 10k | 50 entradas/día × 20 bodegas × 365 días |
| Max registros esperados (año 1) | audit_logs: 50k | Todas las operaciones auditadas |

---

## 🚀 Optimizaciones para Escalabilidad

### **Particionamiento (Fase II)**

Para años 2+ con múltiples municipalidades:

```sql
-- Particionar audit_logs por fecha
ALTER TABLE audit_logs
PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION p2027 VALUES LESS THAN (2028),
    PARTITION p2028 VALUES LESS THAN (2029),
    PARTITION pmax VALUES LESS THAN MAXVALUE
);
```

### **Índices Compuestos para Multi-Query**

```sql
-- Búsqueda común: insumos disponibles en zona X
CREATE INDEX idx_available_by_zone ON stock_entries(
    warehouse_id, 
    status, 
    expiry_date
);

-- Reportes: entregas en período X
CREATE INDEX idx_exits_by_period ON stock_exits(
    release_date, 
    warehouse_id, 
    exit_reason
);
```

---

## 💾 Estrategia de Backups

- **Diario:** Snapshot de RDS (AWS)
- **Semanal:** Backup completo a S3
- **Mensual:** Respaldo archivado en Glacier (cumplimiento LSPP)
- **Recuperación:** RTO 1 hora, RPO 15 minutos

---

## 📞 Notas Finales

Este esquema está diseñado para:
- ✅ Operaciones rápidas en conexiones lentas
- ✅ Auditoría completa (LSPP)
- ✅ Escalabilidad a múltiples municipalidades
- ✅ Protección de datos sensibles
- ✅ Sincronización offline via IndexedDB

**Próximos pasos:** Revisar archivo 03 (Funciones Adicionales) y 04 (Diagramas de Flujo).
