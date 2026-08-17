# 💡 Funciones Adicionales Propuestas — Donaciones Rolda

**Versión:** 1.0  
**Tiempo de lectura:** 10 minutos  
**Audiencia:** Product Manager, Comité técnico, ONGs

---

## 🎯 Propósito

Esta documentación presenta **5 funciones de alto impacto y bajo esfuerzo de implementación** que amplían el valor del MVP sin comprometer el timeline de 7-14 días.

Cada propuesta incluye:
- 📋 Descripción breve
- ✅ Beneficiarios
- ⏱️ Esfuerzo estimado (horas)
- 💰 ROI (retorno de inversión)
- 🔧 Complejidad técnica

---

## 1️⃣ Búsqueda Geolocalizada con Distancia

### Descripción

Cuando un ciudadano busca un insumo, el sistema **calcula automáticamente la distancia** desde su ubicación actual a cada bodega que lo tiene disponible. Muestra primero los más cercanos.

### Casos de Uso

**Emergencia médica urgente:**
- Ciudadano necesita antibióticos
- Busca en la app
- Ve: "Disponible a 2.3 km en Bodega Centro" + "Disponible a 8.5 km en Corregimiento Guayabal"
- Elige el más cercano inmediatamente

### Beneficiarios

- **Ciudadanos:** Acceso más rápido a recursos
- **Sistema:** Distribuye demanda equitativamente
- **ONGs:** Reduce transporte, ahorra combustible

### Implementación

**Frontend:**
```javascript
// Alpine.js - usar Geolocation API
navigator.geolocation.getCurrentPosition((pos) => {
    userLat = pos.coords.latitude;
    userLng = pos.coords.longitude;
    // Enviar a backend con search
});
```

**Backend:**
```php
// Laravel - Haversine formula para distancia
$results = StockEntry::available()
    ->selectRaw("
        master_items.name,
        warehouses.latitude,
        warehouses.longitude,
        (6371 * acos(
            cos(radians(?)) * cos(radians(warehouses.latitude)) *
            cos(radians(warehouses.longitude) - radians(?)) +
            sin(radians(?)) * sin(radians(warehouses.latitude))
        )) AS distance_km
    ", [
        $userLatitude,
        $userLongitude,
        $userLatitude
    ])
    ->orderBy('distance_km')
    ->get();
```

### Esfuerzo Estimado

| Tarea | Horas |
|-------|-------|
| Captura de geolocalización (frontend) | 1 |
| Endpoint con Haversine | 1.5 |
| Testing en dispositivos reales | 1 |
| **Total** | **3.5 horas** |

### ROI

- **Reducción de tiempo de búsqueda:** 50%
- **Satisfacción ciudadano:** +30%
- **Fácil de comunicar:** "La app te muestra lo más cercano"

### Complejidad Técnica

⭐ **Baja** — Geolocation API estándar + query SQL simple

---

## 2️⃣ Búsqueda Inteligente: Historial y Sugerencias

### Descripción

La app **aprende de búsquedas previas** del usuario y le sugiere insumos relacionados.

Ej: Si alguien busca "antibiótico" frecuentemente, la app sugiere: "También disponible: Suero oral", "Vendas estériles".

### Casos de Uso

**Líder comunitario abasteciendo albergue:**
- Busca "arroz"
- Sistema sugiere: "También hay disponible en bodega vecina: Sal, Aceite, Azúcar"
- Aprovecha un solo viaje para múltiples compras
- Reduce logística en 40%

### Beneficiarios

- **Operadores de campo:** Coordinación más inteligente
- **Líderes comunitarios:** Abastecimiento más eficiente
- **Sistema:** Mejor utilización de inventario

### Implementación

**Backend (Redis + PHP):**
```php
// app/Services/SearchSuggestionService.php
class SearchSuggestionService {
    public function getSuggestions(User $user, string $query): array {
        // 1. Obtener búsquedas previas del usuario
        $userHistory = Redis::lrange("user:{$user->id}:search_history", 0, 50);
        
        // 2. Buscar ítems relacionados (mismo operador, misma categoría)
        $suggestions = MasterItem::whereIn('category_id',
            MasterItem::whereIn('name', $userHistory)->pluck('category_id')
        )->where('status', 'approved')->limit(5)->get();
        
        // 3. Guardar búsqueda actual
        Redis::lpush("user:{$user->id}:search_history", $query);
        Redis::ltrim("user:{$user->id}:search_history", 0, 50);
        
        return $suggestions->toArray();
    }
}
```

### Esfuerzo Estimado

| Tarea | Horas |
|-------|-------|
| Servicio de sugerencias | 2 |
| UI para mostrar sugerencias | 1 |
| Testing | 1 |
| **Total** | **4 horas** |

### ROI

- **Viajes de abastecimiento reducidos:** 30%
- **Eficiencia de logística:** +25%
- **Fácil de escalar:** Solo agregamos ítems a sugerir

### Complejidad Técnica

⭐⭐ **Baja-Media** — Redis + relaciones SQL simples

---

## 3️⃣ Reportes Rápidos (PDF/Excel) con Un Clic

### Descripción

Admin u operador genera reportes sin tocar SQL:
- "Stock actual en todas las bodegas"
- "Entregas realizadas en los últimos 7 días"
- "Ítems próximos a vencer"

### Casos de Uso

**Reunión de coordinación con municipalidad:**
- Admin abre app → "Reportes" → Selecciona "Stock por Bodega"
- En 2 segundos genera PDF listo para presentar
- No necesita Excel ni programador

**Auditoria de ONGs:**
- ONG solicita historial de donaciones
- Admin selecciona rango de fechas
- Genera CSV con: Item, Cantidad, Bodega, Beneficiario, Firma

### Beneficiarios

- **Municipalidad:** Toma decisiones con datos actualizados
- **ONGs:** Transparencia automática
- **Admin:** Menos manual, más tiempo para tareas críticas

### Implementación

**Librería:** `barryvdh/laravel-dompdf` + `maatwebsite/excel`

```php
// ReportController.php
public function generateStockReport(Request $request)
{
    $stocks = StockEntry::with(['masterItem', 'warehouse'])
        ->where('status', 'available')
        ->get();
    
    return PDF::loadView('reports.stock', ['stocks' => $stocks])
        ->download('stock-report-' . now()->format('Y-m-d') . '.pdf');
}

// Para Excel:
public function exportStockExcel()
{
    return Excel::download(
        new StockExport,
        'stock-' . now()->format('Y-m-d') . '.xlsx'
    );
}
```

### Esfuerzo Estimado

| Tarea | Horas |
|-------|-------|
| Setup librerías (dompdf + Maatwebsite) | 1 |
| 3-4 templates de reportes | 3 |
| Endpoints REST | 1.5 |
| Testing | 1 |
| **Total** | **6.5 horas** |

### ROI

- **Tiempo en reportes manuales reducido:** 80%
- **Errores humanos:** -95% (menos copiar/pegar)
- **Profesionalismo:** Alto (PDFs con logo, formato)

### Complejidad Técnica

⭐ **Baja** — Laravel + librerías probadas

---

## 4️⃣ Dashboard de Operaciones en Tiempo Real

### Descripción

Pantalla única que muestra:
- 📊 Total de insumos en el sistema (cantidad)
- 🟢🟡🔴 Semáforo agregado (disponibilidad general)
- 📈 Gráfico de entradas vs salidas (últimas 24h)
- ⚠️ Alertas críticas (vencimientos próximos, bodegas sin operador)
- 🌍 Mapa mini de bodegas y su estado

### Casos de Uso

**Crisis coordinada:**
- Coordinador abre dashboard
- Ve de un vistazo: "Hay suministro crítico de medicinas, pero alimentos bajo"
- Toma decisión: "Movilizar camión a Guayabal"

**Reportes diarios a donantes:**
- Director de ONG ve dashboard
- Toma screenshot para reporte de impacto
- Comunica: "Hoy atendimos X ciudadanos con Y insumos"

### Beneficiarios

- **Coordinador central:** Visibilidad instantánea
- **Donantes/Municipalidad:** Impacto visible en números
- **Operadores:** Saben dónde hay crítico

### Implementación

**Frontend:** Chart.js + Alpine.js con actualizaciones via WebSocket (Laravel Reverb, o polling cada 30s)

```php
// DashboardController.php
public function getStats()
{
    return response()->json([
        'total_items' => StockEntry::where('status', 'available')->sum('quantity'),
        'severity' => $this->calculateSemaphore(),
        'entries_24h' => StockEntry::where('created_at', '>=', now()->subDay())->count(),
        'exits_24h' => StockExit::where('release_date', '>=', now()->subDay())->count(),
        'critical_alerts' => ExpiryAlert::where('resolved_at', null)
            ->where('alert_type', '7_days')
            ->count(),
    ]);
}

// Frontend (Alpine + Chart.js)
<div x-data="dashboard()" x-init="loadStats()">
    <div class="grid grid-cols-4 gap-4">
        <div>📦 <span x-text="stats.total_items"></span> Insumos</div>
        <div :style="`color: ${getSeverityColor(stats.severity)}`">
            <span x-text="stats.severity"></span>
        </div>
        <canvas id="activityChart"></canvas>
        <div x-show="stats.critical_alerts > 0" class="alert">
            ⚠️ <span x-text="stats.critical_alerts"></span> Alertas
        </div>
    </div>
</div>
```

### Esfuerzo Estimado

| Tarea | Horas |
|-------|-------|
| Endpoints de estadísticas | 2 |
| Vistas y gráficos (Chart.js) | 3 |
| Realtime updates (WebSocket o polling) | 2 |
| Testing | 1.5 |
| **Total** | **8.5 horas** |

### ROI

- **Tiempo de toma de decisiones:** -70%
- **Comunicación con donantes:** Automatizada
- **Confianza en sistema:** +50% (datos visuales)

### Complejidad Técnica

⭐⭐ **Baja-Media** — Chart.js + queries SQL agrupa

---

## 5️⃣ QR/Barcode para Trazabilidad de Lotes

### Descripción

Cada lote de insumo recibe un **código QR único** que puede escanearse en:
1. Entrada (operador escanea al recibir)
2. Salida (operador escanea al despachar)
3. Beneficiario (QR en comprobante de entrega)

### Casos de Uso

**Auditoría criminal de donativo falso:**
- Se denuncia que un insumo es falsificado
- Admin escanea QR del lote
- Ve historial completo: quién donó, cuándo llegó, quién lo entregó
- Tiene pruebas para denuncia

**Cadena de custodia en medicinas críticas:**
- Antibiótico llega a bodega
- Se escanea: registra entrada automáticamente
- Se despacha: se escanea de nuevo
- Beneficiario recibe con QR visible: prueba de entrega

### Beneficiarios

- **Municipalidad:** Transparencia total
- **Donantes:** Prueba de entrega
- **Sistema:** Automatiza confirmaciones
- **Ciudadano:** Confía más en la cadena

### Implementación

**Librería:** `simple-qrcode` + `quasar.dev` (barcode scanner)

```php
// InventoryController.php
public function generateQRForEntry(StockEntry $entry)
{
    $data = [
        'entry_id' => $entry->id,
        'warehouse_id' => $entry->warehouse_id,
        'item_name' => $entry->masterItem->name,
        'quantity' => $entry->quantity,
        'expiry_date' => $entry->expiry_date,
    ];
    
    $qr = QrCode::format('png')
        ->size(300)
        ->generate(json_encode($data));
    
    $entry->update(['qr_code_path' => $qr]);
    return $qr;
}

// Frontend: Scanner
<video id="scanner-video"></video>
<script>
    Quasar.scan((decodedText) => {
        const data = JSON.parse(decodedText);
        // Procesar entrada/salida automáticamente
        fetch('/api/stock/qr-action', {
            method: 'POST',
            body: JSON.stringify({ qr_data: data, action: 'exit' })
        });
    });
</script>
```

### Esfuerzo Estimado

| Tarea | Horas |
|-------|-------|
| Generar QRs en entrada | 1.5 |
| Scanner mobile (Quasar) | 2 |
| Endpoints de procesamiento | 1.5 |
| Trazabilidad visual | 1 |
| Testing | 1.5 |
| **Total** | **8 horas** |

### ROI

- **Confirmaciones manuales reducidas:** 90%
- **Falsificaciones detectadas:** Probabilidad +80%
- **Confianza en sistema:** Máxima

### Complejidad Técnica

⭐⭐ **Baja-Media** — QR + Quasar scanner

---

## 📊 Tabla Comparativa de Propuestas

| # | Función | Esfuerzo | ROI | Complejidad | Prioridad |
|---|---------|----------|-----|-------------|-----------|
| 1 | Geolocalización | 3.5h | Alto | Baja | 🔴 ALTA |
| 2 | Sugerencias inteligentes | 4h | Medio | Baja | 🟡 MEDIA |
| 3 | Reportes PDF/Excel | 6.5h | Alto | Baja | 🔴 ALTA |
| 4 | Dashboard tiempo real | 8.5h | Muy Alto | Media | 🟡 MEDIA |
| 5 | QR/Barcode | 8h | Muy Alto | Media | 🔴 ALTA |

---

## 🎯 Recomendación ArchiPM

### Para MVP (7-14 días):
Implementar **funciones 1 y 3** (Geolocalización + Reportes PDF/Excel)
- **Esfuerzo total:** ~10 horas
- **Impacto:** Altísimo para ciudadano + admin
- **Tiempo:** Quedan 6-8 días para testing + debugging

### Para Fase II (semana 3-4):
Agregar **funciones 4 y 5** (Dashboard + QR)
- **Impacto:** Profesionalización total
- **Timeline:** Menos presión, más calidad

### Mantener en backlog para después:
Función 2 (Sugerencias inteligentes)
- **Razón:** Requiere datos históricos acumulados
- **Mejor momento:** Después de 4-6 semanas de operación

---

## ✅ Checklist de Implementación

- [ ] **Función 1 (Geolocalización):** Frontend + Backend + Testing
- [ ] **Función 3 (Reportes):** Librerías + Templates + Endpoints
- [ ] **Documentación:** Cómo usar en manual de usuario
- [ ] **Capacitación:** Video de 2 minutos por función
- [ ] **Monitoreo:** Alertas si geolocalización falla, si reportes demoran

---

## 💬 Preguntas Frecuentes

**P: ¿Afectan el MVP las nuevas funciones?**  
R: No. Recomendamos solo 2 funciones (Geo + Reportes) para MVP. El resto va en semana 3-4.

**P: ¿Se puede omitir una función si se atrasan?**  
R: Sí. Orden de omisión: 2 (Sugerencias) → 4 (Dashboard) → 5 (QR).

**P: ¿Requieren datos históricos?**  
R: Solo función 2 (sugerencias). Las otras funcionan desde día 1.

---

**Próximas acciones:** Revisar diagrama de flujos (archivo 04) y análisis de infraestructura (archivo 05).
