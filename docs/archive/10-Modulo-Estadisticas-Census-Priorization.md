> ⚠️ **ARCHIVADO — DISEÑO DESCARTADO:** Esta propuesta de scoring/priorización de beneficiarios es incompatible con el diseño vigente en [`11-Modulo-7-Beneficiarios-Estadisticas-Inteligentes.md`](../11-Modulo-7-Beneficiarios-Estadisticas-Inteligentes.md) (algoritmo de scoring distinto, 4 niveles de riesgo vs. 3, esquema de tablas SQL mutuamente excluyente). Se conserva como referencia histórica de una alternativa de diseño descartada.

# 📊 Módulo 10: Panel de Estadísticas y Análisis del Censo — Donaciones Rolda

**Versión:** 1.0  
**Tipo:** Nuevo módulo (expansión de Módulo 7 - Beneficiarios)  
**Complejidad:** Alta  
**Timeline:** +40-50 horas  
**Audiencia:** Coordinadores, Admin, Líderes comunitarios, Personal médico

---

## 📑 Índice

1. [Visión General](#visión-general)
2. [Componentes Principales](#componentes-principales)
3. [Perfiles de Riesgo](#perfiles-de-riesgo)
4. [Indicaciones Médicas](#indicaciones-médicas)
5. [Dashboards y Alertas](#dashboards-y-alertas)
6. [Funcionalidades Complementarias](#funcionalidades-complementarias)
7. [Arquitectura de Datos](#arquitectura-de-datos)

---

## 🎯 Visión General

### **Objetivo**

Crear un **sistema inteligente de priorización** que:

- ✅ Analiza automáticamente perfil de cada beneficiario
- ✅ Clasifica vulnerabilidad (Crítica / Alta / Media / Baja)
- ✅ Sugiere medicinas y artículos específicos por persona
- ✅ Genera alertas de casos críticos
- ✅ Proporciona estadísticas en tiempo real
- ✅ Facilita decisiones de distribución de recursos escasos

### **Caso de Uso**

```
ESCENARIO: Crisis humanitaria en Roldanillo

1. Coordinador abre Panel de Estadísticas
2. Ve:
   ├─ Total beneficiarios: 1,247
   ├─ Riesgo crítico: 89 (7%)
   ├─ Riesgo alto: 234 (19%)
   ├─ Necesidad médica urgente: 45 casos
   └─ Medicinas más solicitadas: Antibióticos, analgésicos, antidiabéticos

3. Hace clic en "Casos Críticos"
4. Ve lista ordenada:
   ├─ Rosa García (67 años)
   │  ├─ Riesgo: 🔴 CRÍTICO
   │  ├─ Razón: Adulta mayor + Diabetes + Sin medicinas
   │  ├─ Familia: 4 personas (1 menor de 5 años)
   │  ├─ Vivienda: Dañada
   │  ├─ Medicinas urgentes: Insulina, Metformina, Bandas glucosa
   │  ├─ Alimentos: Dieta baja en azúcar
   │  └─ Botón: "Crear orden de entrega"
   │
   ├─ Juan Mendoza (4 años)
   │  ├─ Riesgo: 🔴 CRÍTICO
   │  ├─ Razón: Menor con desnutrición + Sin vivienda
   │  ├─ Medicinas: Multivitamínicos, suplemento proteína
   │  ├─ Alimentos: Leche en polvo, papillas nutritivas
   │  └─ Botón: "Crear orden de entrega"
   │
   └─ ... (más casos)

5. Para Rosa García:
   - Clic en "Crear orden de entrega"
   - Sistema sugiere automáticamente:
     ├─ 3 bolsas insulina
     ├─ 1 caja Metformina 500mg (30 tabs)
     ├─ 1 medidor de glucosa
     ├─ 50 bandas de glucosa
     ├─ Kit alimentos sin azúcar
     └─ Agua embotellada
   
   - Coordinador ajusta según disponibilidad:
     ├─ ✓ Todos los items disponibles
     └─ Genera orden + imprime
```

---

## 🔍 Componentes Principales

### **Componente 1: Perfil de Riesgo (Risk Scoring)**

**Algoritmo de cálculo automático:**

```python
def calcular_riesgo_beneficiario(beneficiary):
    """
    Calcula puntuación de riesgo (0-100)
    0-25: BAJO
    26-50: MEDIO
    51-75: ALTO
    76-100: CRÍTICO
    """
    
    puntos = 0
    
    # Factor 1: Edad (20 puntos máx)
    if beneficiary.age < 5:
        puntos += 20  # Menores criticamente vulnerables
    elif beneficiary.age > 65:
        puntos += 15  # Adultos mayores
    
    # Factor 2: Composición familiar (15 puntos máx)
    if beneficiary.household['menores_5'] > 0:
        puntos += 8   # Tiene menores
    if beneficiary.household['adultos_mayores'] > 0:
        puntos += 7   # Tiene mayores
    
    # Factor 3: Condiciones médicas (30 puntos máx)
    condiciones_criticas = ['diabetes', 'hipertension', 'asma', 'VIH', 'cancer']
    for cond in beneficiary.medical_conditions:
        if cond in condiciones_criticas:
            puntos += 10  # Condición crónica crítica
        else:
            puntos += 5   # Otra condición
    
    # Factor 4: Embarazo/Lactancia (15 puntos máx)
    if beneficiary.pregnant:
        puntos += 15
    if beneficiary.nursing:
        puntos += 10
    
    # Factor 5: Situación de vivienda (15 puntos máx)
    if beneficiary.housing_status == 'destroyed':
        puntos += 15
    elif beneficiary.housing_status == 'damaged':
        puntos += 10
    
    # Factor 6: Acceso a recursos (10 puntos máx)
    if beneficiary.lost_employment:
        puntos += 5
    if not beneficiary.has_medical_access:
        puntos += 5
    
    # Factor 7: Tiempo sin atención (hasta 10 puntos extra)
    if beneficiary.days_without_aid > 7:
        puntos += min(10, beneficiary.days_without_aid / 7)
    
    return min(100, int(puntos))


def clasificar_riesgo(puntos):
    if puntos >= 76:
        return {'nivel': 'CRÍTICO', 'color': '🔴', 'acción': 'INMEDIATA'}
    elif puntos >= 51:
        return {'nivel': 'ALTO', 'color': '🟠', 'acción': 'HOY'}
    elif puntos >= 26:
        return {'nivel': 'MEDIO', 'color': '🟡', 'acción': '24-48h'}
    else:
        return {'nivel': 'BAJO', 'color': '🟢', 'acción': 'NORMAL'}
```

**Resultado para ejemplo:**

```
ROSA GARCÍA (67 años):
├─ Edad 67: +15 pts (adulta mayor)
├─ Composición: +7 pts (adultos mayores)
├─ Diabetes + Hipertensión: +20 pts (2 condiciones críticas × 10)
├─ Vivienda dañada: +10 pts
├─ Sin acceso médico: +5 pts
├─ 12 días sin atención: +10 pts (12/7 ≈ 1.7 → capped at 10)
│
├─ TOTAL: 15+7+20+10+5+10 = 67 puntos
├─ CLASIFICACIÓN: 🔴 ALTO (51-75)
└─ ACCIÓN: Atender HOY
```

---

### **Componente 2: Indicaciones Médicas Personalizadas**

**Motor de recomendaciones:**

#### **Tabla: medical_condition_prescriptions**

```sql
CREATE TABLE medical_condition_prescriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Condición médica
    medical_condition VARCHAR(100) NOT NULL,  -- 'diabetes', 'hipertension', etc
    
    -- Medicinas recomendadas
    medicine_item_id BIGINT UNSIGNED,
    medicine_name VARCHAR(150),
    dosage VARCHAR(50),                      -- "500mg", "100 IU", etc
    frequency VARCHAR(100),                  -- "2 veces/día", "cada 8h"
    quantity_typical INT,                    -- Cantidad estándar a enviar
    unit VARCHAR(30),                        -- tabs, cajas, botellas
    
    -- Información
    priority ENUM('critica', 'importante', 'complementaria') DEFAULT 'importante',
    notes TEXT,
    
    -- Control
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (medicine_item_id) REFERENCES master_items(id) ON DELETE SET NULL,
    INDEX idx_condition (medical_condition),
    INDEX idx_priority (priority)
);

-- Ejemplos de datos:
INSERT INTO medical_condition_prescriptions VALUES
-- DIABETES
(1, 'diabetes', 5, 'Insulina NPH', '100 IU/mL', '1 inyección/día', 3, 'botellas', 'critica', 'Essential para diabetes tipo 1', TRUE, NOW()),
(2, 'diabetes', 6, 'Metformina', '500mg', '2 veces/día', 1, 'caja', 'critica', 'Diabetes tipo 2', TRUE, NOW()),
(3, 'diabetes', 7, 'Medidor glucosa', '—', 'Diario', 1, 'unidad', 'importante', 'Para monitoreo', TRUE, NOW()),
(4, 'diabetes', 8, 'Bandas glucosa', '—', 'Hasta 8/día', 1, 'tira', 'critica', 'Para medición', TRUE, NOW()),
(5, 'diabetes', NULL, 'Dieta baja azúcar', '—', 'Permanente', NULL, NULL, 'complementaria', 'Educación nutricional', TRUE, NOW()),

-- HIPERTENSION
(6, 'hipertension', 10, 'Amlodipina', '5mg', '1 vez/día', 1, 'caja', 'critica', NULL, TRUE, NOW()),
(7, 'hipertension', 11, 'Enalapril', '10mg', '1-2 veces/día', 1, 'caja', 'critica', NULL, TRUE, NOW()),
(8, 'hipertension', 12, 'Hidroclorotiazida', '25mg', '1 vez/día', 1, 'caja', 'importante', NULL, TRUE, NOW()),

-- ASMA
(9, 'asma', 15, 'Salbutamol', 'Inhalador', 'PRN (según necesidad)', 2, 'inhaladores', 'critica', NULL, TRUE, NOW()),
(10, 'asma', 16, 'Corticoide', 'Inhalador', 'Diario', 1, 'inhalador', 'critica', NULL, TRUE, NOW()),

-- DESNUTRICIÓN (Menores)
(11, 'desnutricion', 20, 'Leche en polvo', 'Enfamil/Similar', '5 veces/día', 5, 'bolsas', 'critica', NULL, TRUE, NOW()),
(12, 'desnutricion', 21, 'Multivitamínicos', 'Pediátrico', '1 vez/día', 1, 'frasco', 'importante', NULL, TRUE, NOW()),
(13, 'desnutricion', 22, 'Papillas nutritivas', 'Cereal + proteína', '3 veces/día', 3, 'cajas', 'importante', NULL, TRUE, NOW());
```

**Lógica de recomendación:**

```php
// app/Services/MedicalRecommendationService.php

class MedicalRecommendationService {
    
    public function generateRecommendations(Beneficiary $beneficiary): array {
        $recommendations = [];
        
        // 1. Por cada condición médica del beneficiario
        foreach ($beneficiary->medical_conditions as $condition) {
            $prescriptions = MedicalConditionPrescription::where(
                'medical_condition', $condition
            )
            ->where('is_active', true)
            ->orderBy('priority', 'DESC')
            ->get();
            
            foreach ($prescriptions as $rx) {
                $recommendations[] = [
                    'condition' => $condition,
                    'medicine' => $rx->medicine_name,
                    'dosage' => $rx->dosage,
                    'frequency' => $rx->frequency,
                    'quantity_to_send' => $rx->quantity_typical,
                    'unit' => $rx->unit,
                    'priority' => $rx->priority,
                    'stock_available' => $this->checkStock($rx->medicine_item_id),
                    'status' => 'pending', // 'pending', 'added', 'unavailable'
                ];
            }
        }
        
        // 2. Por edad/estado especial
        if ($beneficiary->age < 5) {
            $recommendations[] = [
                'condition' => 'edad',
                'medicine' => 'Multivitamínicos pediátricos',
                'priority' => 'importante',
                'quantity_to_send' => 1,
            ];
        }
        
        if ($beneficiary->pregnant) {
            $recommendations[] = [
                'condition' => 'embarazo',
                'medicine' => 'Ácido fólico',
                'priority' => 'critica',
                'quantity_to_send' => 1,
            ];
        }
        
        // 3. Alimentos especiales
        $dietary_recommendations = $this->getDietaryRecommendations($beneficiary);
        $recommendations = array_merge($recommendations, $dietary_recommendations);
        
        // 4. Ordenar por prioridad
        usort($recommendations, function($a, $b) {
            $priority_order = ['critica' => 1, 'importante' => 2, 'complementaria' => 3];
            return $priority_order[$a['priority']] - $priority_order[$b['priority']];
        });
        
        return $recommendations;
    }
    
    private function getDietaryRecommendations(Beneficiary $beneficiary): array {
        $dietary = [];
        
        foreach ($beneficiary->medical_conditions as $condition) {
            switch ($condition) {
                case 'diabetes':
                    $dietary[] = ['food' => 'Verduras frescas', 'reason' => 'Baja en carbohidratos'];
                    $dietary[] = ['food' => 'Proteína magra', 'reason' => 'Evitar procesados'];
                    break;
                case 'hipertension':
                    $dietary[] = ['food' => 'Alimentos sin sal', 'reason' => 'Reducir sodio'];
                    $dietary[] = ['food' => 'Frutas y verduras', 'reason' => 'Alto en potasio'];
                    break;
                case 'desnutricion':
                    $dietary[] = ['food' => 'Leche en polvo', 'reason' => 'Alto en proteína y calcio'];
                    $dietary[] = ['food' => 'Huevos', 'reason' => 'Proteína accesible'];
                    break;
            }
        }
        
        return $dietary;
    }
}
```

---

### **Componente 3: Dashboard de Priorización**

**Vistas principales:**

```
┌─────────────────────────────────────────────────────────┐
│          PANEL DE CONTROL — CENSO Y BENEFICIARIOS       │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  📊 ESTADÍSTICAS GENERALES                             │
│  ├─ Total beneficiarios: 1,247                         │
│  ├─ Total dependientes: 3,891 (familia)                │
│  ├─ Casos críticos: 89 (7.1%)                          │
│  ├─ Casos de alto riesgo: 234 (18.8%)                  │
│  ├─ Menores afectados: 456 (36.6% del total)           │
│  ├─ Adultos mayores: 234 (18.8%)                       │
│  ├─ Personas con condiciones médicas: 167 (13.4%)      │
│  └─ Hogares sin vivienda: 89 (7.1%)                    │
│                                                          │
│  🚨 ALERTAS CRÍTICAS                                   │
│  ├─ 12 personas sin medicinas hace > 1 semana          │
│  ├─ 5 menores malnutridos sin atención                 │
│  ├─ 3 embarazadas sin control médico                   │
│  └─ 2 casos con VIH sin tratamiento                    │
│                                                          │
│  💊 MEDICINAS MÁS SOLICITADAS                          │
│  ├─ Antibióticos: 234 personas                         │
│  ├─ Analgésicos: 189                                   │
│  ├─ Antidiabéticos: 123                                │
│  ├─ Antihipertensivos: 98                              │
│  └─ Multivitamínicos: 156                              │
│                                                          │
│  🥫 ALIMENTOS PRIORITARIOS                             │
│  ├─ Leche en polvo: 456 personas (menores)             │
│  ├─ Arroz/pasta: 1,200 personas                        │
│  ├─ Proteína: 567 personas                             │
│  └─ Alimentos sin sal: 98 hipertensos                  │
│                                                          │
│  ┌─────────────────────────────────────────────────┐   │
│  │ [VER CASOS CRÍTICOS] [VER MÉDICOS] [REPORTES]  │   │
│  └─────────────────────────────────────────────────┘   │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 🔴 Perfiles de Riesgo

### **Matriz de Clasificación**

```
┌───────────────────────────────────────────────────────────────────┐
│                    MATRIZ DE RIESGO                                │
├───────────────────────────────────────────────────────────────────┤
│                                                                    │
│  CRÍTICO (🔴 76-100 pts)    → ATENCIÓN INMEDIATA (< 24h)         │
│  ├─ Adulto mayor + comorbilidades + sin vivienda + sin meds     │
│  ├─ Menor < 5 años + desnutrición + sin hogar                   │
│  ├─ Embarazada sin control médico + complicaciones               │
│  ├─ Persona con VIH/TB sin tratamiento                           │
│  └─ Ejemplo: Rosa García (67, diabetes, hipertensión, vivienda dañada)
│
│  ALTO (🟠 51-75 pts)         → ATENCIÓN HOY (< 48h)              │
│  ├─ Adulto mayor con 1+ condición crónica + sin meds             │
│  ├─ Menor con desnutrición o enfermedad crónica                  │
│  ├─ Vivienda dañada + familia dependiente                        │
│  └─ Ejemplo: Familia con 3 menores, sin techo, 1 diabético
│
│  MEDIO (🟡 26-50 pts)        → ATENCIÓN 48-72h                   │
│  ├─ Adulto con una condición controlada                          │
│  ├─ Vivienda intacta pero dificultades económicas                │
│  └─ Ejemplo: Trabajador desempleado con familia
│
│  BAJO (🟢 0-25 pts)          → ATENCIÓN NORMAL (< 1 semana)      │
│  ├─ Adulto sin comorbilidades, vivienda intacta                  │
│  └─ Familia autosuficiente, solo necesidades puntuales
│
└───────────────────────────────────────────────────────────────────┘
```

---

## 💊 Indicaciones Médicas

### **Sistema de Prescripción Automatizada**

```
FLUJO: Beneficiario crítico → Recomendaciones automáticas

1. ADMIN abre: Beneficiario "Rosa García"
2. Sistema AUTOMÁTICAMENTE:
   ├─ Identifica condiciones: Diabetes + Hipertensión
   ├─ Busca en medical_condition_prescriptions
   ├─ Genera lista de medicinas:
   │  ├─ 🔴 CRÍTICA: Insulina NPH (3 botellas)
   │  ├─ 🔴 CRÍTICA: Metformina 500mg (1 caja)
   │  ├─ 🟠 IMPORTANTE: Medidor de glucosa (1 unidad)
   │  ├─ 🔴 CRÍTICA: Bandas de glucosa (1 tira)
   │  ├─ 🟠 IMPORTANTE: Amlodipina (1 caja)
   │  ├─ 🟠 IMPORTANTE: Enalapril (1 caja)
   │  └─ 🟢 COMPLEMENTARIA: Dieta baja azúcar (educación)
   │
   ├─ Verifica disponibilidad:
   │  ├─ Insulina: Disponible (5 botellas en stock)
   │  ├─ Metformina: Disponible (20 cajas)
   │  ├─ Medidor: Disponible (8 unidades)
   │  └─ Bandas: Bajo stock (2 tiras, necesita 1) ⚠️
   │
   └─ Crea "Orden de Entrega Sugerida"
      ├─ Items: 6 medicinas + 1 dieta
      ├─ Estado: SUGERIDA (admin confirma)
      ├─ Prioridad: 🔴 CRÍTICA
      └─ Fecha de acción: HOY

3. ADMIN revisa + confirma
4. Sistema:
   ├─ Descuenta del inventario
   ├─ Crea comprobante
   ├─ Genera QR
   └─ Notifica a operador de bodega
```

---

## 📈 Dashboards y Alertas

### **Tipos de Dashboards**

#### **Dashboard 1: Coordinador General**

```
Métricas de impacto:
├─ Beneficiarios atendidos HOY: 234 / 1,247 (18.8%)
├─ Medicinas entregadas HOY: 1,203 dosis
├─ Kits de alimentos repartidos: 89
├─ Beneficiarios críticos atendidos: 12 / 89
├─ Tasa de cobertura: 87%
└─ Pronóstico: "En 3 días, cubriremos 100%"
```

#### **Dashboard 2: Personal Médico**

```
Casos que necesitan intervención:
├─ Diabetes sin insulina: 23 casos
├─ HTA sin control: 18 casos
├─ Embarazadas sin control: 4 casos
├─ Menores malnutridos: 12 casos
├─ Casos de tuberculosis: 2 casos
└─ Seguimiento necesario: 67 casos
```

#### **Dashboard 3: Gestor de Inventario**

```
Medicinas críticas:
├─ Insulina: 2 botellas (bajo, necesita 50)
├─ Antibióticos: 5 cajas (crítico, necesita 80)
├─ Antidiabéticos: 15 cajas (ok)
└─ Alertas de reorden: 5 ítems
```

### **Sistema de Alertas Automáticas**

```
ALERTA 1: Caso Crítico Sin Atender (> 48h)
├─ Trigger: Beneficiario riesgo 🔴 y sin movimiento hace > 48h
├─ Notificación: A coordinador + equipo médico
├─ Acción: "Seguimiento urgente requerido"
└─ Escalada: Si > 72h sin respuesta, notificar supervisor

ALERTA 2: Medicina Crítica Agotada
├─ Trigger: Stock de medicina crítica = 0
├─ Notificación: A coordinador + gestor inventario
├─ Acción: "Reorden urgente de [MEDICINA]"
└─ Impacto: N beneficiarios sin acceso

ALERTA 3: Caso Médico Complejo
├─ Trigger: Beneficiario con 3+ condiciones crónicas
├─ Notificación: A personal médico
├─ Acción: "Evaluación médica urgente"
└─ Referencia: Link al perfil + historial

ALERTA 4: Menor Malnutrido Sin Seguimiento
├─ Trigger: Menor < 5 años + desnutrición + > 7 días sin revisión
├─ Notificación: A nutricionista + coordinador
├─ Acción: "Programa nutricional urgente"
└─ Seguimiento: Semanal hasta normalización
```

---

## 🔧 Funcionalidades Complementarias

### **Funcionalidad 1: Generador de Órdenes de Entrega Inteligente**

```
CARACTERÍSTICA: Al crear orden, sistema sugiere automáticamente:

1. Medicinas (basado en condiciones médicas)
2. Alimentos (basado en edad + necesidades nutricionales)
3. Artículos de higiene (basado en familia size)
4. Cantidad (basado en duración típica)
5. Urgencia (basado en risk score)

RESULTADO:
├─ Operador NO tiene que pensar qué dar
├─ Reducción de errores médicos
├─ Equidad en distribución
└─ Auditoría completa de recomendaciones
```

### **Funcionalidad 2: Seguimiento Médico Integrado**

```
CARACTERÍSTICAS:
├─ Historial médico del beneficiario
├─ Medicinas entregadas (fechas, cantidades)
├─ Seguimiento de efectividad
├─ Notas de personal médico
├─ Plan de tratamiento personalizado
└─ Alertas de cambios en estado de salud
```

### **Funcionalidad 3: Reportes Epidemiológicos**

```
REPORTES:
├─ Prevalencia de enfermedades (diabetes: 9.8%, HTA: 7.2%, etc)
├─ Tasas de desnutrición por zona
├─ Cobertura de medicinas por condición
├─ Brotes de enfermedades (si ER agregados de síntomas)
├─ Correlación: vivienda dañada ↔ enfermedades respiratorias
└─ Proyecciones: "Si no hay intervención, 150 más casos en 1 mes"
```

### **Funcionalidad 4: Planificador de Recursos**

```
ALGORITMO: "¿Dónde envío los recursos para máximo impacto?"

INPUT:
├─ Stock disponible de medicinas
├─ Beneficiarios críticos por zona
└─ Capacidad de distribución

OUTPUT:
├─ Orden de prioridad por zona
├─ Medicinas a enviar a cada bodega
├─ Cantidad recomendada
└─ Beneficiarios esperados a cubrir
```

### **Funcionalidad 5: Sistema de Derivación Médica**

```
FLUJO: Beneficiario que requiere nivel de cuidado superior

1. Personal en terreno identifica caso complejo
2. Crea "Solicitud de derivación" con:
   ├─ Síntomas observados
   ├─ Medicinas dadas
   ├─ Respuesta del paciente
   ├─ Urgencia de derivación
   └─ Centro de salud sugerido

3. Sistema:
   ├─ Notifica al coordinador médico
   ├─ Verifica disponibilidad del centro
   ├─ Genera referencia + historia clínica resumida
   └─ Contacta al centro de salud

4. Seguimiento:
   ├─ ¿Fue atendido? SÍ/NO
   ├─ ¿Requiere seguimiento? SÍ/NO
   └─ Reintegración a programa (si procede)
```

### **Funcionalidad 6: Programa de Educación Sanitaria**

```
ASOCIADO A CADA PRESCRIPCIÓN:

Ejemplo: Beneficiario con Diabetes
├─ Medicinas: Insulina + Metformina
├─ Educación sanitaria:
│  ├─ "¿Cómo inyectarse insulina?" (video)
│  ├─ "Alimentos que evitar" (infografía)
│  ├─ "Reconocer signos de alerta" (checklist)
│  ├─ "Registro de glucosa" (tabla)
│  └─ "Cuándo llamar al médico" (teléfono de emergencia)
│
└─ Material entregable:
   ├─ Impreso + QR a material multimedia
   └─ Acceso via WhatsApp (educación continuada)
```

### **Funcionalidad 7: Colaboración Inter-organizacional**

```
CARACTERÍSTICA: Múltiples ONGs + Municipalidad ven datos agregados

VISTA 1: Municipalidad
├─ Total de beneficiarios en el territorio
├─ Medicinas entregadas (todas las ONGs)
├─ Cobertura por zona
└─ Recomendaciones de donde hacer falta

VISTA 2: ONG A
├─ Beneficiarios registrados por ONG A
├─ Entregas realizadas
├─ Medicinas necesarias (no duplicar con ONG B)
└─ Coordinación: "ONG B va a Guayabal, nosotros a Centro"

PROTOCOLO DE EVITAR DUPLICADOS:
├─ Cada beneficiario tiene ID único
├─ Historial de entregas visible a todas
├─ Alerta: "Rosa García ya recibió insulina de ONG X el 18/08"
└─ Coordinación automática
```

### **Funcionalidad 8: Predicción de Demanda**

```
ALGORITMO: Predecir qué medicinas/alimentos faltan en 7 días

INPUT:
├─ Beneficiarios atendidos/día
├─ Consumo promedio por beneficiario
├─ Stock actual
└─ Datos históricos

OUTPUT:
├─ "En 5 días necesitaremos 80 cajas de antibióticos"
├─ "Leche en polvo durará 3 días más"
├─ "Reorden urgente de: Insulina, Metformina, Antibióticos"
└─ "Solicitar donación de alimentos básicos"
```

---

## 🗄️ Arquitectura de Datos (Nuevas Tablas)

### **Tabla 1: beneficiary_risk_profiles**

```sql
CREATE TABLE beneficiary_risk_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    beneficiary_id BIGINT UNSIGNED NOT NULL,
    
    -- Scoring
    risk_score INT UNSIGNED,  -- 0-100
    risk_level ENUM('bajo', 'medio', 'alto', 'critico'),
    
    -- Componentes del scoring
    age_points INT DEFAULT 0,
    family_composition_points INT DEFAULT 0,
    medical_conditions_points INT DEFAULT 0,
    housing_points INT DEFAULT 0,
    economic_points INT DEFAULT 0,
    time_without_aid_points INT DEFAULT 0,
    
    -- Metadata
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,  -- Re-calcular cada 7 días
    
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
    UNIQUE KEY unique_beneficiary (beneficiary_id),
    INDEX idx_risk_level (risk_level),
    INDEX idx_calculated (calculated_at)
);
```

### **Tabla 2: medical_condition_prescriptions** (Already defined earlier)

```sql
-- Ver sección anterior
```

### **Tabla 3: delivery_recommendations**

```sql
CREATE TABLE delivery_recommendations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    beneficiary_id BIGINT UNSIGNED NOT NULL,
    
    -- Medicinas
    medicine_item_id BIGINT UNSIGNED,
    medicine_name VARCHAR(150),
    quantity_recommended INT,
    unit VARCHAR(30),
    priority ENUM('critica', 'importante', 'complementaria'),
    
    -- Estado
    status ENUM('suggested', 'confirmed', 'delivered', 'unavailable', 'cancelled'),
    
    -- Auditoría
    suggested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_item_id) REFERENCES master_items(id) ON DELETE SET NULL,
    INDEX idx_beneficiary (beneficiary_id),
    INDEX idx_status (status, suggested_at)
);
```

### **Tabla 4: medical_follow_ups**

```sql
CREATE TABLE medical_follow_ups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    beneficiary_id BIGINT UNSIGNED NOT NULL,
    medical_provider_id BIGINT UNSIGNED NOT NULL,
    
    -- Observaciones
    observation TEXT,
    vital_signs JSON,  -- {temp: 37.5, bp: 120/80, hr: 72}
    treatment_given VARCHAR(200),
    next_followup_date DATE,
    
    -- Urgencia
    needs_urgent_followup BOOLEAN DEFAULT FALSE,
    reason_urgent TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (beneficiary_id) REFERENCES beneficiaries(id) ON DELETE CASCADE,
    FOREIGN KEY (medical_provider_id) REFERENCES users(id),
    INDEX idx_followup_date (next_followup_date),
    INDEX idx_urgent (needs_urgent_followup)
);
```

---

## ⏱️ Estimación de Esfuerzo

| Componente | Horas | Notas |
|-----------|-------|-------|
| **Backend - Risk Scoring Algorithm** | 8-10 | Lógica + testing |
| **Backend - Medical Recommendations** | 10-12 | Integración con DB |
| **Backend - Dashboard APIs** | 12-15 | Múltiples endpoints |
| **Backend - Alerting System** | 8-10 | Reglas + notificaciones |
| **Frontend - Risk Dashboard** | 10-12 | Gráficos + tablas |
| **Frontend - Medical Profile** | 8-10 | UI beneficiario |
| **Frontend - Delivery Recommendations UI** | 8-10 | Formulario inteligente |
| **Frontend - Reports/Analytics** | 12-15 | Charts + exports |
| **Testing (E2E)** | 6-8 | Scoring, alerts, recomendaciones |
| **Documentación** | 3-4 | Guías médicas, runbooks |
| **TOTAL** | **85-106 horas** | |

---

## 🎯 Recomendación Final

**Incluir en MVP:** Sí (Módulo crítico para toma de decisiones)

**Timeline:** +8-10 días (incluyen integraciones Censo + Mapa + Kardex + Census Analytics)

**Priorización en desarrollo:**
1. Risk Scoring Algorithm (2-3 días)
2. Medical Recommendations (2-3 días)
3. Dashboards + Alerts (3-4 días)
4. Complementarias (2-3 días)

**Impacto:** **Alto** — Este módulo diferencia Donaciones Rolda de apps competidoras. Sin él, la distribución es ciega. Con él, es inteligente.
