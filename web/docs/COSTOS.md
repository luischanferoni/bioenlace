# Costos por Consulta - Diferentes Planes de Hosting

Este documento refleja el **costo real** de cada ítem **sin aplicar** estrategias de reducción de costos. No se incluyen aquí ahorros por caché, optimizaciones de código, elección de modelo ni otras tácticas; las **reducciones posibles** (en % sobre este costo real) se documentan en [ESTRATEGIAS_REDUCCION_COSTO.md](./ESTRATEGIAS_REDUCCION_COSTO.md).

Incluye: costos de infraestructura e IA por consulta y por médico, y **costos por capacidades adicionales** (conversación pre-consulta, agente de onboarding, medios, videollamadas). Ver [Costos por capacidades adicionales](#costos-por-capacidades-adicionales) y [CAPACIDADES_PACIENTE_MEDICO.md](./CAPACIDADES_PACIENTE_MEDICO.md).

## Supuestos Base

- **Consultas por médico**: 20/día = 600/mes (31 días)
- **Costo real (sin estrategias de reducción)**: se indica por plan y por ítem en las tablas siguientes.

---

## Plan 1: RunPod RTX 3090 (Recomendado)

### Costo real (sin estrategias de reducción)
- **Costo**: $8.36/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: $8.36 ÷ 600 = **$0.0139/consulta** ≈ **$0.014/consulta**

**Ventajas**:
- ✅ Precio fijo (no aumenta con uso)
- ✅ Sin interrupciones
- ✅ Fácil de configurar
- ✅ Facturación por hora (fácil escalar)

**Desventajas**:
- ❌ Escalado manual (agregar instancias)
- ❌ Menos servicios que AWS/GCP

---

## Plan 2: RunPod RTX 4090

### Costo real (sin estrategias de reducción)
- **Costo**: $8.43/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: $8.43 ÷ 600 = **$0.014/consulta**

**Ventajas**:
- ✅ GPU más potente (mejor rendimiento)
- ✅ Precio fijo
- ✅ Sin interrupciones

**Desventajas**:
- ❌ Ligeramente más caro que RTX 3090
- ❌ Escalado manual

---

## Plan 3: AWS g4dn.xlarge (Reserved)

### Costo real (sin estrategias de reducción)
- **Costo**: $4.56-6.84/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: 
  - Mínimo: $4.56 ÷ 600 = **$0.0076/consulta**
  - Máximo: $6.84 ÷ 600 = **$0.0114/consulta**
- **Rango**: **$0.008 - $0.011/consulta**

**Ventajas**:
- ✅ Escalado automático
- ✅ Alta disponibilidad
- ✅ 40% descuento con reserva de 1 año
- ✅ Sin interrupciones

**Desventajas**:
- ❌ Compromiso de 1 año
- ❌ Costo variable según uso

---

## Plan 4: AWS g4dn.xlarge (Spot)

### Costo real (sin estrategias de reducción)
- **Costo**: $1.52-4.56/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: 
  - Mínimo: $1.52 ÷ 600 = **$0.0025/consulta**
  - Máximo: $4.56 ÷ 600 = **$0.0076/consulta**
- **Rango**: **$0.0025 - $0.008/consulta**

**Ventajas**:
- ✅ Muy económico (60-80% descuento)
- ✅ Escalado automático
- ✅ Alta disponibilidad

**Desventajas**:
- ❌ Spot puede interrumpirse (AWS avisa 2 minutos antes)
- ❌ Costo variable (puede aumentar)
- ⚠️ **Riesgo**: Interrupciones posibles

---

## Plan 5: GCP T4 (Preemptible)

### Costo real (sin estrategias de reducción)
- **Costo**: $1.40-3.78/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: 
  - Mínimo: $1.40 ÷ 600 = **$0.0023/consulta**
  - Máximo: $3.78 ÷ 600 = **$0.0063/consulta**
- **Rango**: **$0.002 - $0.006/consulta**

**Ventajas**:
- ✅ Muy económico (50-70% descuento)
- ✅ Escalado automático
- ✅ Sustained use discounts

**Desventajas**:
- ❌ Preemptible puede interrumpirse (GCP avisa 30 segundos antes)
- ⚠️ **Riesgo**: Interrupciones más frecuentes que AWS Spot

---

## Resumen Comparativo (costo real por consulta)

| Plan de Hosting | Costo real (USD/consulta) | Costo real (USD/médico/mes, 600 consultas) |
|-----------------|---------------------------|---------------------------------------------|
| **RunPod RTX 3090** | $0.014 | $8.36 |
| **RunPod RTX 4090** | $0.014 | $8.43 |
| **AWS Reserved** | $0.008-0.011 | $4.56-6.84 |
| **AWS Spot** | $0.0025-0.008 | $1.52-4.56 |
| **GCP Preemptible** | $0.002-0.006 | $1.40-3.78 |

El **porcentaje de reducción** posible sobre este costo real se detalla en [ESTRATEGIAS_REDUCCION_COSTO.md](./ESTRATEGIAS_REDUCCION_COSTO.md).

---

## Costo por Consulta Según Volumen (costo real)

Todos los valores son **costo real** sin aplicar estrategias de reducción. Para el % de reducción posible, ver [ESTRATEGIAS_REDUCCION_COSTO.md](./ESTRATEGIAS_REDUCCION_COSTO.md).

### Escenario: 10 consultas/día (310/mes)

| Plan | Costo real (USD/consulta) | Costo real (USD/médico/mes) |
|------|---------------------------|-----------------------------|
| **RunPod RTX 3090** | $0.027 | $8.37 |
| **AWS Reserved** | $0.015-0.022 | $4.65-6.82 |
| **AWS Spot** | $0.005-0.015 | $1.55-4.65 |
| **GCP Preemptible** | $0.005-0.012 | $1.55-3.72 |

### Escenario: 20 consultas/día (600/mes) – Base

| Plan | Costo real (USD/consulta) | Costo real (USD/médico/mes) |
|------|---------------------------|-----------------------------|
| **RunPod RTX 3090** | $0.014 | $8.36 |
| **AWS Reserved** | $0.008-0.011 | $4.56-6.84 |
| **AWS Spot** | $0.0025-0.008 | $1.52-4.56 |
| **GCP Preemptible** | $0.002-0.006 | $1.40-3.78 |

### Escenario: 30 consultas/día (930/mes)

| Plan | Costo real (USD/consulta) | Costo real (USD/médico/mes) |
|------|---------------------------|-----------------------------|
| **RunPod RTX 3090** | $0.009 | $8.37 |
| **AWS Reserved** | $0.005-0.007 | $4.65-6.51 |
| **AWS Spot** | $0.002-0.005 | $1.86-4.65 |
| **GCP Preemptible** | $0.002-0.004 | $1.86-3.72 |

### Escenario: 50 consultas/día (1,550/mes)

| Plan | Costo real (USD/consulta) | Costo real (USD/médico/mes) |
|------|---------------------------|-----------------------------|
| **RunPod RTX 3090** | $0.005 | $8.36 |
| **AWS Reserved** | $0.003-0.004 | $4.65-6.20 |
| **AWS Spot** | $0.001-0.003 | $1.55-4.65 |
| **GCP Preemptible** | $0.001-0.002 | $1.55-3.10 |

---

## Notas

- **Volumen**: A mayor volumen de consultas, menor costo por consulta cuando el costo mensual del plan es fijo (RunPod).
- **Reducción posible**: El porcentaje en que se puede reducir este costo real aplicando estrategias (caché, optimizaciones de código, elección de plan, etc.) se detalla en [ESTRATEGIAS_REDUCCION_COSTO.md](./ESTRATEGIAS_REDUCCION_COSTO.md).

## Cálculo personalizado (costo real)

```
Costo real por consulta = Costo mensual real del plan (sin estrategias) ÷ Consultas por médico por mes
```

**Ejemplo**:
- Plan: RunPod RTX 3090, costo real = $8.36/médico/mes
- Consultas: 25/día = 775/mes
- Costo real por consulta: $8.36 ÷ 775 = **$0.0108/consulta**

---

## Costos por capacidades adicionales

Las siguientes estimaciones corresponden a capacidades descritas en [CAPACIDADES_PACIENTE_MEDICO.md](./CAPACIDADES_PACIENTE_MEDICO.md): conversación pre-consulta, agente de IA para onboarding y tareas del día a día, intercambio de audios/fotos/videos, y videollamadas. Los valores usan **precios oficiales de Google Cloud** (consultados en 2025) donde aplica, para acercar el presupuesto a la realidad.

### Precios de referencia Google Cloud (2025)

| Servicio | Precio (USD) | Fuente / Notas |
|----------|--------------|-----------------|
| **Speech-to-Text V2** (estándar) | \$0.016/min (0–500k min/mes) | [cloud.google.com/speech-to-text/pricing](https://cloud.google.com/speech-to-text/pricing) |
| **Speech-to-Text V1** (estándar) | 60 min/mes gratis; luego \$0.016/min (con data logging) o \$0.024/min (sin) | Misma fuente |
| **Vision API** (Label, Text, Face, etc.) | Primeras 1.000 unidades/mes gratis; luego \$1.50 por 1.000 unidades | [cloud.google.com/vision/pricing](https://cloud.google.com/vision/pricing) |
| **Cloud Storage Standard** (almacenamiento) | ~\$0.020/GB/mes (región us-central1) | [cloud.google.com/storage/pricing](https://cloud.google.com/storage/pricing) |
| **Cloud Storage** (egress a internet) | \$0.12/GB (destinos mundial; primer tramo) | Misma fuente, sección Network |

Para Vertex AI / Gemini y videollamadas (Twilio, Daily.co) se usan rangos típicos; conviene revisar el [Calculador de precios de Google Cloud](https://cloud.google.com/products/calculator) y las páginas de precios de cada proveedor.

### Supuestos base (costo real, sin estrategias de reducción)

- **Consultas por médico**: 20/día = 600/mes (mismo que el análisis principal).
- **Costo real de IA por llamada** (sin caché ni optimizaciones): según plan de hosting, p. ej. RunPod \$0.014/consulta → **~\$0.014 por interacción IA**; AWS Reserved \$0.008–0.011 → **~\$0.008–0.011 por interacción**.

---

### 1. Conversación pre-consulta (chat para despejar dudas y guiar al paciente)

Costo real = uso completo sin respuestas predefinidas ni caché (todas las interacciones que requieren IA pagan).

| Concepto | Supuesto | Costo real mensual (por médico) |
|--------|----------|----------------------------------|
| Mensajes pre-consulta estimados | 600 consultas × 5 mensajes = 3.000; 50% con IA ⇒ **1.500 llamadas IA** | — |
| **Costo real** (RunPod, \$0.014/llamada) | 1.500 × \$0.014 | **~\$21/médico/mes** |
| **Costo real** (AWS Reserved, \$0.009/llamada) | 1.500 × \$0.009 | **~\$13.50/médico/mes** |

---

### 2. Agente de IA para onboarding y tareas del día a día

Costo real = todas las interacciones que requieren IA sin flujos guiados ni caché.

| Concepto | Supuesto | Costo real mensual (por médico) |
|--------|----------|----------------------------------|
| Total llamadas IA/médico/mes | ~400 (20 nuevos × 10 + 100 activos × 2) | — |
| **Costo real** (RunPod, \$0.014/llamada) | 400 × \$0.014 | **~\$5.60/médico/mes** |
| **Costo real** (AWS Reserved, \$0.009/llamada) | 400 × \$0.009 | **~\$3.60/médico/mes** |

---

### 3. Intercambio de audios, fotos y videos (médico–paciente)

**Modelo de uso**: Los medios **no se almacenan en cloud storage**; se ven/escuchan directamente por el médico. Solo hay costo cuando se envía a la IA para analizar. Ver [CAPACIDADES_PACIENTE_MEDICO.md](./CAPACIDADES_PACIENTE_MEDICO.md).

**Costo real** = se transcribe todo el audio y se analizan todas las fotos enviadas (escenario de uso máximo sin estrategias de reducción).

| Concepto | Supuesto | Costo real mensual (por médico) |
|--------|----------|----------------------------------|
| **Almacenamiento / egress** | No se usa cloud storage | **\$0** |
| **STT** (transcribir todo el audio) | 1 min/consulta × 600 = 600 min; \$0.016/min (V2) | **\$9.60** (V1: 60 min gratis → **\$8.64**) |
| **Vision** (analizar todas las fotos) | 600 × 2 = 1.200 imágenes; 1.000 gratis + 200 × \$1.50/1.000 | **\$0.30** |
| **Costo real total medios (STT + Vision)** | — | **~\$8.95–9.60/médico/mes** |

---

### 4. Videollamadas paciente–médico

**Costo real** = uso típico sin estrategias (plan por minuto o equivalente).

| Concepto | Supuesto | Costo real mensual (por médico) |
|--------|----------|----------------------------------|
| Consultas por videollamada/mes | 30% de 600 = 180 | 180 |
| Duración media | 12 min | 12 min |
| Minutos totales/mes | 180 × 12 = 2.160 min | **2.160 min** |
| **Costo real** (Twilio Video, 2 participantes, \$0.004/min cada uno) | 2.160 × 2 × \$0.004 | **~\$17.30/médico/mes** |
| **Costo real** (plan por asiento, ej. Daily.co repartido 10 médicos) | — | **~\$10/médico/mes** (orden de magnitud) |

---

### Resumen: costo real adicional mensual por médico (sin estrategias de reducción)

Todos los valores son **costo real**. El **porcentaje de reducción** posible sobre cada ítem se documenta en [ESTRATEGIAS_REDUCCION_COSTO.md](./ESTRATEGIAS_REDUCCION_COSTO.md).

| Capacidad | Costo real (USD/médico/mes) |
|-----------|-----------------------------|
| Conversación pre-consulta (IA) | \$13.50–21 (según plan hosting) |
| Agente onboarding y día a día (IA) | \$3.60–5.60 (según plan hosting) |
| Audios, fotos, videos (STT + Vision, uso máximo) | \$8.95–9.60 |
| Videollamadas (CPaaS) | \$10–17.30 |
| **Total adicional (capacidades)** | **~\$36–53/médico/mes** |

**Costo real total por médico/mes** (infra IA/hosting + capacidades): ejemplo con RunPod (\$8.36) + pre-consulta (\$21) + onboarding (\$5.60) + medios STT+Vision (\$9.60) + video (\$17.30) ≈ **~\$62/médico/mes** (sin aplicar estrategias de reducción).

---

## Referencias

- [Capacidades paciente–médico](./CAPACIDADES_PACIENTE_MEDICO.md)
- [Optimizaciones desde el Código](./OPTIMIZACIONES_CODIGO.md)
- [Estrategias de Reducción de Costo](./ESTRATEGIAS_REDUCCION_COSTO.md)

