# 🔀 Diagramas de Flujo por Módulo — Donaciones Rolda

**Versión:** 1.0  
**Formato:** ASCII + Explicaciones  
**Audiencia:** Técnicos, analistas, coordinadores

---

## 📑 Índice de Flujos

1. [Flujo Público: Ciudadano Busca Insumo](#flujo-1-ciudadano-busca-insumo)
2. [Flujo Operativo: Entrada de Insumo (Offline-First)](#flujo-2-entrada-de-insumo-offline-first)
3. [Flujo Administrativo: Aprobación de Ítems](#flujo-3-aprobación-de-ítems-maestros)
4. [Flujo de Sincronización Offline](#flujo-4-sincronización-offline)
5. [Flujo de Alertas de Vencimiento](#flujo-5-alertas-de-vencimiento)

---

## **FLUJO 1: Ciudadano Busca Insumo**

```
┌────────────────────────────────────────────────────────────────────┐
│                    CIUDADANO BUSCA INSUMO URGENTE                  │
└────────────────────────────────────────────────────────────────────┘

     START
       │
       ▼
   ┌─────────────────────────────────────┐
   │ Ciudadano abre portal público       │
   │ donaciones-rolda.example.com        │
   └──────────────┬──────────────────────┘
                  │
                  ▼
   ┌─────────────────────────────────────┐
   │ Captura geolocalización (opcional)  │
   │ "Usar mi ubicación" vs "Todas zonas"│
   └──────────────┬──────────────────────┘
                  │
                  ▼
   ┌─────────────────────────────────────┐
   │ Ingresa búsqueda:                   │
   │ "antibiótico" / "arroz" / "gasas"   │
   │ Selecciona categoría (opcional)     │
   │ Selecciona zona (opcional)          │
   └──────────────┬──────────────────────┘
                  │
                  ▼
   ┌─────────────────────────────────────┐
   │ BACKEND: GET /api/public/search     │
   │ ├─ keyword search en master_items   │
   │ ├─ JOIN con stock_entries (available)
   │ ├─ Calcula distancia (Haversine)   │
   │ ├─ Calcula semáforo por cantidad    │
   │ └─ Ordena por distancia/disponibilidad
   └──────────────┬──────────────────────┘
                  │
                  ▼
   ┌─────────────────────────────────────┐
   │ Muestra resultados en tarjetas:     │
   │ ┌──────────────────────────────────┐ │
   │ │ Suero Oral Sachet 20.5g   🟢      │ │
   │ │ Ubicación: Albergue Central (2km) │ │
   │ │ Cantidad: Alta (> 20 unidades)    │ │
   │ │ Caducidad: 10/11/2027              │ │
   │ │ [ Contactar ] [ Ver en Mapa ]    │ │
   │ └──────────────────────────────────┘ │
   └──────────────┬──────────────────────┘
                  │
           ¿Quiere contactar? 
              /        \
            SÍ          NO
            │            │
            ▼            ▼
      ┌──────────┐  END (vuelve a buscar)
      │ Modal de │
      │ Contact  │
      │ Unlock   │
      └────┬─────┘
           │
           ▼
    ┌────────────────────────────┐
    │ Cloudflare Turnstile:      │
    │ "No soy un robot"          │
    │ (Valida challenge)         │
    └────────────┬───────────────┘
                 │
            ¿Validó?
            /       \
          SÍ        NO
          │          │
          ▼          ▼
    ┌─────────┐  Rechaza
    │ Muestra │  Modal cierra
    │ datos:  │
    │ Nombre  │
    │ Teléfono│
    │ Link WA │
    └────┬────┘
         │
         ▼
    ┌────────────────────────────┐
    │ Ciudadano hace clic         │
    │ "Abrir en WhatsApp"         │
    │ Deep-link a WhatsApp        │
    │ (Pre-redactado:             │
    │ "Hola, busco [INSUMO]")     │
    └────────────┬────────────────┘
                 │
                 ▼
    ┌────────────────────────────┐
    │ BACKEND: Log de acceso     │
    │ - session_id               │
    │ - search_query             │
    │ - turnstile_validated      │
    │ - timestamp                │
    │ (Para analytics/LSPP)      │
    └────────────┬────────────────┘
                 │
                 ▼
    ┌────────────────────────────┐
    │ Ciudadano contacta         │
    │ directamente via WhatsApp  │
    │ Coordinador responde       │
    │ "Sí, tenemos disponible"   │
    │ Se coordina entrega        │
    └────────────┬────────────────┘
                 │
                 ▼
              END
```

### **Puntos Críticos del Flujo 1:**

| Paso | Latencia Crítica | Optimización |
|------|------------------|--------------|
| Búsqueda full-text | Redis caché (30s TTL) | Pre-calentar índices |
| Cálculo de distancia | Haversine (rápido) | Índice geoespacial |
| Turnstile | Red call (100-200ms) | Timeout 5s + retry |
| Logging | Async queue | No bloquea UI |

---

## **FLUJO 2: Entrada de Insumo (Offline-First)**

```
┌────────────────────────────────────────────────────────────────────┐
│         OPERADOR DE CAMPO REGISTRA ENTRADA (PWA Offline)           │
└────────────────────────────────────────────────────────────────────┘

     START
       │
       ▼
   ┌─────────────────────────────────────┐
   │ Operador abre PWA en tableta        │
   │ /app/inventory/entry-form           │
   │ (App guardada en caché local)        │
   └──────────────┬──────────────────────┘
                  │
                  ▼
   ┌─────────────────────────────────────┐
   │ Sistema detecta estado de conexión: │
   │ - ONLINE:  Envía datos a API real   │
   │ - OFFLINE: Guarda en IndexedDB      │
   │ (Service Worker activo)             │
   └──────────────┬──────────────────────┘
                  │
                  ▼
   ┌─────────────────────────────────────┐
   │ Formulario de entrada aparece:      │
   │ ├─ Bodega actual: [Bodega X ▼]     │
   │ ├─ Insumo: [Buscar...]             │
   │ ├─ Cantidad: [50] [Cajas]          │
   │ ├─ Lote: [LOT-2026-X] (opt)        │
   │ ├─ Vencimiento: [2026-12-31]       │
   │ ├─ Cadena frío: [☐]                │
   │ └─ [💾 Guardar]                     │
   └──────────────┬──────────────────────┘
                  │
        ¿Insumo existe?
           /        \
         SÍ          NO
         │            │
         ▼            ▼
      (Opción A)  ┌─────────────────┐
                  │ Hacer clic:     │
                  │ "➕ Nuevo ítem" │
                  └────────┬────────┘
                           │
                           ▼
                  ┌──────────────────────────┐
                  │ Modal de solicitud:      │
                  │ Nombre: [_______]        │
                  │ Categoría: [▼]           │
                  │ Unidad: [▼]              │
                  │ Cadena frío: [☐]        │
                  │ [✓ Solicitar]            │
                  └────────┬─────────────────┘
                           │
         (vuelve a opción A con ítem en revisión)
         │
         ▼
   ┌─────────────────────────────────────┐
   │ Operador hace clic [💾 Guardar]     │
   └──────────────┬──────────────────────┘
                  │
         ¿Conexión? 
         /        \
       ONLINE   OFFLINE
         │         │
         ▼         ▼
    (Rama A)   (Rama B)
    
    ═════ RAMA A: ONLINE ═════
    │
    ▼
┌──────────────────────────────────┐
│ Envía a: POST /api/stock/entries │
│ Body: {                           │
│   item_id: 42,                    │
│   warehouse_id: 5,                │
│   quantity: 50,                   │
│   unit: "cajas",                  │
│   lot_number: "LOT-2026-X",       │
│   expiry_date: "2026-12-31",      │
│   requires_cold_chain: false      │
│ }                                 │
└────────────┬─────────────────────┘
             │
             ▼
   ┌───────────────────────────────────┐
   │ BACKEND: Validar y crear registro │
   │ ├─ Validar FormRequest            │
   │ ├─ CreateStockEntryAction         │
   │ ├─ INSERT stock_entries           │
   │ │  (status = pending_arrival)     │
   │ ├─ Invalidar caché Redis          │
   │ └─ Notificación interna a admin   │
   └────────────┬──────────────────────┘
                │
                ▼
   ┌───────────────────────────────────┐
   │ RESPUESTA: HTTP 201 + json        │
   │ {                                 │
   │   "success": true,                │
   │   "stock_entry_id": 1234,         │
   │   "message": "Entrada registrada" │
   │ }                                 │
   └────────────┬──────────────────────┘
                │
                ▼
   ┌───────────────────────────────────┐
   │ Frontend: Muestra "✅ Sincronizado"│
   │ Limpia formulario                  │
   │ Opción: "➕ Otra entrada"          │
   └────────────┬──────────────────────┘
                │
                └─────→ FIN RAMA A
    
    ═════ RAMA B: OFFLINE ═════
    │
    ▼
┌──────────────────────────────────┐
│ Dexie.js (IndexedDB local):      │
│ db.syncQueue.add({               │
│   id: uuid(),                    │
│   action: 'create_entry',        │
│   data: {...},                   │
│   status: 'pending',             │
│   timestamp: now()               │
│ })                               │
└────────────┬─────────────────────┘
             │
             ▼
   ┌───────────────────────────────────┐
   │ UI muestra:                        │
   │ "⏳ Pendiente sincronizar"         │
   │ (Badge amarillo, no ✓ verde)      │
   │ Operador puede seguir trabajando   │
   └────────────┬──────────────────────┘
                │
    ... Operador registra más entradas ...
                │
    ... Conexión vuelve (ONLINE) ...
                │
                ▼
   ┌───────────────────────────────────┐
   │ Service Worker detecta cambio de  │
   │ estado (online event)             │
   │ Abre sync queue de IndexedDB      │
   └────────────┬──────────────────────┘
                │
                ▼
   ┌───────────────────────────────────┐
   │ Por cada entrada en queue:        │
   │ ├─ POST /api/stock/entries        │
   │ ├─ ¿Respuesta OK?                 │
   │ │  SÍ: delete de IndexedDB        │
   │ │  NO: retry exponencial (3x)     │
   │ └─ Muestra notificación           │
   │   "Sincronizadas X entradas"      │
   └────────────┬──────────────────────┘
                │
                └─────→ FIN RAMA B
                
        (Ambas ramas convergen)
                │
                ▼
            END
```

### **Notas Técnicas de Flujo 2:**

**IndexedDB Estructura (Dexie.js):**
```javascript
const db = new Dexie('DonacionesRolda');
db.version(1).stores({
    syncQueue: 'id, status, timestamp',
    masterItems: 'id, name',
    warehouses: 'id, name'
});
```

**Service Worker Sync:**
```javascript
// Detecta conexión restaurada
window.addEventListener('online', async () => {
    const queue = await db.syncQueue.where('status').equals('pending').toArray();
    for (const item of queue) {
        try {
            await api.post('/stock/entries', item.data);
            await db.syncQueue.update(item.id, { status: 'synced' });
        } catch (err) {
            // Retry logic
        }
    }
});
```

---

## **FLUJO 3: Aprobación de Ítems Maestros**

```
┌────────────────────────────────────────────────────────────────────┐
│            ADMINISTRADOR APRUEBA ÍTEMS NUEVOS                      │
└────────────────────────────────────────────────────────────────────┘

     START
       │
       ▼
   ┌──────────────────────────────────────┐
   │ Admin recibe notificación interna:  │
   │ "Nuevo ítem en revisión: Antibiótico│
   │  Solicitado por: Juan (Bodega X)"   │
   └────────────┬───────────────────────┘
                │
                ▼
   ┌──────────────────────────────────────┐
   │ Admin va a Panel:                   │
   │ /admin/master-items/pending         │
   │ GET /api/admin/master-items/pending │
   │ Status: under_review                │
   └────────────┬───────────────────────┘
                │
                ▼
   ┌──────────────────────────────────────┐
   │ Ve tabla de ítems en revisión:      │
   │ ┌────────────────────────────────┐  │
   │ │ Ítem | Categoría | Solicitante│  │
   │ │────────────────────────────────│  │
   │ │Antibiót... | Medicinas | Juan  │  │
   │ │Gasas 10x.. | Insumos   | María │  │
   │ └────────────────────────────────┘  │
   │ Admin hace clic en fila             │
   └────────────┬───────────────────────┘
                │
                ▼
   ┌──────────────────────────────────────┐
   │ Abre detalle del ítem:               │
   │ Nombre: "Antibiótico Amorxicilina"  │
   │ Solicitado por: Juan (Bodega Bomberos)
   │ Unidad de medida: [Frascos]         │
   │ Categoría: [Medicinas] ▼            │
   │ Cadena de frío: [☑] Sí              │
   │ Cantidad registrada: 50              │
   │ Vencimiento: 2026-12-31              │
   │ Historial: [Ver trazabilidad]       │
   │                                      │
   │ [❌ Rechazar] [✓ Aprobar y Asignar]│
   └────────────┬───────────────────────┘
                │
         ¿Admin elige?
          /        \
      RECHAZAR   APROBAR
       /            \
      ▼              ▼
  ┌────────┐   ┌────────────────────┐
  │ Modal: │   │ Actualiza:         │
  │ Motivo │   │ master_item.status │
  │ rechazo│   │  = approved        │
  │ ......│   │                    │
  │ [✓ OK]│   │ stock_entry.status │
  └───┬────┘   │  = available      │
      │        │                    │
      │        └────────┬──────────┘
      │                 │
      ▼                 ▼
  ┌────────────────────────────────────┐
  │ BACKEND: Action:                   │
  │ ├─ UPDATE master_items             │
  │ │  status = 'rejected'             │
  │ │  rejection_reason = '...'        │
  │ ├─ Notificación a operador:        │
  │ │  "Tu solicitud fue rechazada:..." │
  │ ├─ Auditar: admin_id, motivo       │
  │ └─ FIN                             │
  └────────────────────────────────────┘
      │
      └─────────────────────────────────┐
                                        │
                                        ▼
                          ┌───────────────────────┐
                          │ RAMA APROBACIÓN:      │
                          │ ├─ UPDATE master_item │
                          │ │  status = 'approved'│
                          │ ├─ UPDATE stock_entry │
                          │ │  status = 'available
                          │ ├─ DELETE caché Redis │
                          │ ├─ NOTIFICACIÓN:      │
                          │ │  "Tu ítem fue       │
                          │ │   aprobado ✓"       │
                          │ ├─ AUDIT LOG:         │
                          │ │  admin_id,          │
                          │ │  action = 'approve' │
                          │ └─ FIN                │
                          └───────────────────────┘
                                      │
                                      ▼
                          ┌───────────────────────┐
                          │ Ítem aparece ahora:  │
                          │ ✓ En catálogo público│
                          │ ✓ En búsquedas       │
                          │ ✓ Disponible en stock│
                          │ ✓ Visible en portal  │
                          └───────────────────────┘
                                      │
                                      ▼
                                    END
```

---

## **FLUJO 4: Sincronización Offline**

```
┌──────────────────────────────────────────────────────────────┐
│        SINCRONIZACIÓN DE DATOS OFFLINE → ONLINE              │
└──────────────────────────────────────────────────────────────┘

ESTADO INICIAL: Operador registró 3 entradas sin conexión
IndexedDB contiene:
{
  id: uuid1,
  action: 'create_entry',
  data: { item_id: 42, qty: 50 },
  status: 'pending',
  timestamp: 14:23:45
}
x 3 registros


    TRIGGER DE SINCRONIZACIÓN
         /       |        \
     Manual  OnlineEvent  Scheduled
     (botón)  (automático) (cada 5min)
        │          │           │
        └──────┬───┴───────────┘
               │
               ▼
    ┌──────────────────────────┐
    │ Service Worker awakens   │
    │ Abre sync queue          │
    │ COUNT: 3 pendiente       │
    │ Estado UI: "Sincronizando
    │             3 entradas..."
    └────────────┬─────────────┘
                 │
                 ▼
    ┌──────────────────────────┐
    │ LOOP: Para cada entrada: │
    └────────────┬─────────────┘
                 │
     ┌───────────┴───────────┐
     │                       │
     ▼ (Entrada 1)           ▼ (Entrada 2)
   ┌─────────┐             ┌─────────┐
   │POST /api│             │POST /api│
   │entries  │             │entries  │
   └────┬────┘             └────┬────┘
        │                       │
        ▼                       ▼
   ¿Status 201? ─── SÍ ──────→ DELETE de IndexedDB
        │ NO                    UPDATE: status='synced'
        │                       Notif: "✓ Sincronizado"
        ▼
    Retry 1/3
    (wait 2s)
        │
        ▼
    ¿Status 201? ─── SÍ ──────→ (como arriba)
        │ NO
        ▼
    Retry 2/3
    (wait 5s)
        │
        ▼
    ¿Status 201? ─── SÍ ──────→ (como arriba)
        │ NO
        ▼
    Retry 3/3 FALLA
    │
    ▼
┌──────────────────────────────┐
│ Mantener en IndexedDB        │
│ Status: 'failed'             │
│ Error: '502 Server Error'    │
│ Retry mañana                 │
│ Notificación: "⚠️ No se      │
│ sincronizó entrada 1. Será   │
│ reintentado."                │
└──────────────────────────────┘

... (resto de entradas) ...

                │
        CONVERGENCIA
                │
                ▼
    ┌────────────────────────────┐
    │ Resumen final:             │
    │ ✓ Sincronizadas: 2/3       │
    │ ⚠️ Pendiente: 1/3           │
    │ Mostrar en UI              │
    └────────────┬───────────────┘
                 │
                 ▼
            END
```

---

## **FLUJO 5: Alertas de Vencimiento**

```
┌───────────────────────────────────────────────────────────────┐
│             PROCESO DE ALERTAS DE VENCIMIENTO                 │
└───────────────────────────────────────────────────────────────┘

CRON JOB: Cada 6 horas (00:00, 06:00, 12:00, 18:00)

    START (Scheduler ejecuta)
       │
       ▼
    ┌─────────────────────────────────┐
    │ CheckExpiryAlertsJob::handle()  │
    │ Laravel Queue worker             │
    └────────────┬────────────────────┘
                 │
                 ▼
    ┌─────────────────────────────────┐
    │ Query:                          │
    │ SELECT * FROM stock_entries     │
    │ WHERE status = 'available'      │
    │ AND expiry_date BETWEEN         │
    │   NOW()                         │
    │   AND NOW() + INTERVAL 30 DAY   │
    │ ORDER BY expiry_date ASC        │
    └────────────┬────────────────────┘
                 │
                 ▼
    ┌─────────────────────────────────┐
    │ Resultados: [100 registros]     │
    │ (100 insumos con ≤30 días)      │
    └────────────┬────────────────────┘
                 │
         FOR EACH resultado:
         │
         ├─ Calcular días_hasta_vencimiento
         │
         ▼
    ┌─────────────────────────────────────────┐
    │ Determinar tipo de alerta:              │
    │ • Si ≤ 7 días   → alert_type = '7_days'│
    │ • Si ≤ 14 días  → alert_type = '14_day'│
    │ • Si ≤ 30 días  → alert_type = '30_day'│
    │ • Si < 0        → alert_type = 'expired│
    └────────────┬────────────────────────────┘
                 │
                 ▼
    ┌─────────────────────────────────────────┐
    │ Verificar si ya existe alerta:          │
    │ SELECT * FROM expiry_alerts             │
    │ WHERE stock_entry_id = ? AND            │
    │       alert_type = ?                    │
    │       AND resolved_at IS NULL           │
    └────────────┬────────────────────────────┘
                 │
         ¿Ya existe?
         /         \
       NO          SÍ
       │            │
       ▼            └─ SKIP (ya notificado)
    ┌──────────────────────────┐
    │ CREATE alerta:           │
    │ INSERT expiry_alerts     │
    │ (stock_entry_id,         │
    │  alert_type,             │
    │  detected_at = NOW())    │
    └────────────┬─────────────┘
                 │
                 ▼
    ┌──────────────────────────┐
    │ Notificar a admin:       │
    │ POST internal_notificatio│
    │ title: "⚠️ Item próx.    │
    │  a vencer"               │
    │ message: "[Item] vence   │
    │  en [X] días en Bodega Y"│
    └────────────┬─────────────┘
                 │
                 ▼
    ┌──────────────────────────────────────┐
    │ Si alert_type = '7_days' Y cold_chain│
    │ → Notificar TAMBIÉN al operador:     │
    │   "Insumo crítico próximo a vencer"  │
    └────────────┬────────────────────────┘
                 │
     FIN DE LOOP (próximo registro)
                 │
                 ▼
    ┌──────────────────────────────────────┐
    │ CRON Finaliza                        │
    │ Resumen:                             │
    │ • Alertas creadas: 45                │
    │ • Ya notificadas: 55                 │
    │ • Log: Job completado 00:15:23       │
    └──────────────────────────────────────┘
                 │
                 ▼
            END (próx. ejecución en 6h)


REPORTE VISUAL PARA ADMIN:
┌──────────────────────────────────────┐
│ Panel > Alertas > Vencimientos       │
├──────────────────────────────────────┤
│ 🔴 Vence Hoy (0 días)                │
│   └─ Suero oral × 5 unidades         │
│      Bodega: Bomberos                │
│      Acción: [Retiro] [Usar Ahora]   │
│                                      │
│ 🟠 Vence en 7 días                   │
│   └─ Antibiótico × 20 cajas          │
│      Bodega: Centro                  │
│      Acción: [Retiro] [Donar]        │
│                                      │
│ 🟡 Vence en 14 días                  │
│   └─ Gasas 10×10 × 100 paquetes      │
│      Bodega: Guayabal                │
│      Acción: [OK, monitoreando]      │
└──────────────────────────────────────┘
```

---

## 📊 Tabla Resumen de Flujos

| Flujo | Actores | Crítico | Offline | Auditable |
|-------|---------|---------|---------|-----------|
| 1. Búsqueda Ciudadano | Público, Admin | NO | Parcial (UI) | NO |
| 2. Entrada Insumo | Operador, Admin | SÍ | SÍ (IndexedDB) | SÍ |
| 3. Aprobación Ítems | Admin | SÍ | NO | SÍ |
| 4. Sincronización | Service Worker | SÍ | SÍ (Núcleo) | SÍ |
| 5. Alertas Vencimiento | Scheduler, Admin | NO | NO | SÍ |

---

## 🔗 Relaciones entre Flujos

```
Flujo 2 (Entrada)
      │
      ├─ Crea stock_entry
      │
      ▼
Flujo 3 (Aprobación)
      │
      ├─ Si es nuevo ítem, aprueba
      │
      ▼
Flujo 1 (Búsqueda)
      │
      ├─ Item disponible aparece en portal
      │
      ▼
Flujo 5 (Alertas)
      │
      ├─ Monitorea vencimiento
      │
      ▼
END (Ciclo cerrado)

Transversal:
Flujo 4 (Sincronización) ◄─── Aplica a Flujo 2 (cuando offline)
```

---

**Próximas acciones:** Revisar análisis de infraestructura (archivo 05) y estimación de costos (archivo 06).
