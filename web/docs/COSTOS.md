# Costos por Consulta - Diferentes Planes de Hosting

## Supuestos Base

- **Consultas por médico**: 20/día = 600/mes (31 días)
- **Costo base sin optimizaciones**: $8.36/médico/mes (RunPod RTX 3090)
- **Costo con optimizaciones**: $3-5/médico/mes

---

## Plan 1: RunPod RTX 3090 (Recomendado)

### Sin Optimizaciones
- **Costo**: $8.36/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: $8.36 ÷ 600 = **$0.0139/consulta** ≈ **$0.014/consulta**

### Con Optimizaciones (Todas Implementadas)
- **Costo**: $3-5/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: 
  - Mínimo: $3 ÷ 600 = **$0.005/consulta**
  - Máximo: $5 ÷ 600 = **$0.0083/consulta**
- **Rango**: **$0.005 - $0.008/consulta**

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

### Sin Optimizaciones
- **Costo**: $8.43/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: $8.43 ÷ 600 = **$0.014/consulta**

### Con Optimizaciones
- **Costo**: ~$3-5/médico/mes (similar a RTX 3090)
- **Costo por consulta**: **$0.005 - $0.008/consulta**

**Ventajas**:
- ✅ GPU más potente (mejor rendimiento)
- ✅ Precio fijo
- ✅ Sin interrupciones

**Desventajas**:
- ❌ Ligeramente más caro que RTX 3090
- ❌ Escalado manual

---

## Plan 3: AWS g4dn.xlarge (Reserved)

### Sin Optimizaciones
- **Costo**: $4.56-6.84/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: 
  - Mínimo: $4.56 ÷ 600 = **$0.0076/consulta**
  - Máximo: $6.84 ÷ 600 = **$0.0114/consulta**
- **Rango**: **$0.008 - $0.011/consulta**

### Con Optimizaciones
- **Costo**: ~$1.50-3/médico/mes (estimado)
- **Costo por consulta**: 
  - Mínimo: $1.50 ÷ 600 = **$0.0025/consulta**
  - Máximo: $3 ÷ 600 = **$0.005/consulta**
- **Rango**: **$0.0025 - $0.005/consulta**

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

### Sin Optimizaciones
- **Costo**: $1.52-4.56/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: 
  - Mínimo: $1.52 ÷ 600 = **$0.0025/consulta**
  - Máximo: $4.56 ÷ 600 = **$0.0076/consulta**
- **Rango**: **$0.0025 - $0.008/consulta**

### Con Optimizaciones
- **Costo**: ~$0.60-2/médico/mes (estimado)
- **Costo por consulta**: 
  - Mínimo: $0.60 ÷ 600 = **$0.001/consulta**
  - Máximo: $2 ÷ 600 = **$0.0033/consulta**
- **Rango**: **$0.001 - $0.003/consulta**

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

### Sin Optimizaciones
- **Costo**: $1.40-3.78/médico/mes
- **Consultas/mes**: 600
- **Costo por consulta**: 
  - Mínimo: $1.40 ÷ 600 = **$0.0023/consulta**
  - Máximo: $3.78 ÷ 600 = **$0.0063/consulta**
- **Rango**: **$0.002 - $0.006/consulta**

### Con Optimizaciones
- **Costo**: ~$0.50-1.50/médico/mes (estimado)
- **Costo por consulta**: 
  - Mínimo: $0.50 ÷ 600 = **$0.0008/consulta**
  - Máximo: $1.50 ÷ 600 = **$0.0025/consulta**
- **Rango**: **$0.0008 - $0.0025/consulta**

**Ventajas**:
- ✅ Muy económico (50-70% descuento)
- ✅ Escalado automático
- ✅ Sustained use discounts

**Desventajas**:
- ❌ Preemptible puede interrumpirse (GCP avisa 30 segundos antes)
- ⚠️ **Riesgo**: Interrupciones más frecuentes que AWS Spot

---

## Resumen Comparativo

| Plan de Hosting | Sin Optimizaciones | Con Optimizaciones | Ahorro |
|-----------------|-------------------|-------------------|--------|
| **RunPod RTX 3090** | $0.014/consulta | $0.005-0.008/consulta | 43-64% |
| **RunPod RTX 4090** | $0.014/consulta | $0.005-0.008/consulta | 43-64% |
| **AWS Reserved** | $0.008-0.011/consulta | $0.0025-0.005/consulta | 55-69% |
| **AWS Spot** | $0.0025-0.008/consulta | $0.001-0.003/consulta | 60-88% |
| **GCP Preemptible** | $0.002-0.006/consulta | $0.0008-0.0025/consulta | 58-88% |

---

## Costo por Consulta Según Volumen

### Escenario: 10 consultas/día (310/mes)

| Plan | Sin Optimizaciones | Con Optimizaciones |
|------|-------------------|-------------------|
| **RunPod RTX 3090** | $0.027/consulta | $0.010-0.016/consulta |
| **AWS Reserved** | $0.015-0.022/consulta | $0.005-0.010/consulta |
| **AWS Spot** | $0.005-0.015/consulta | $0.002-0.006/consulta |
| **GCP Preemptible** | $0.005-0.012/consulta | $0.002-0.005/consulta |

**Costo mensual por médico**:
- RunPod RTX 3090: $3.10-4.96/mes (con optimizaciones)
- AWS Reserved: $1.55-3.10/mes (con optimizaciones)
- AWS Spot: $0.62-1.86/mes (con optimizaciones)
- GCP Preemptible: $0.62-1.55/mes (con optimizaciones)

---

### Escenario: 20 consultas/día (600/mes) - Base

| Plan | Sin Optimizaciones | Con Optimizaciones |
|------|-------------------|-------------------|
| **RunPod RTX 3090** | $0.014/consulta | $0.005-0.008/consulta |
| **AWS Reserved** | $0.008-0.011/consulta | $0.0025-0.005/consulta |
| **AWS Spot** | $0.0025-0.008/consulta | $0.001-0.003/consulta |
| **GCP Preemptible** | $0.002-0.006/consulta | $0.0008-0.0025/consulta |

**Costo mensual por médico**:
- RunPod RTX 3090: $3-5/mes (con optimizaciones)
- AWS Reserved: $1.50-3/mes (con optimizaciones)
- AWS Spot: $0.60-2/mes (con optimizaciones)
- GCP Preemptible: $0.50-1.50/mes (con optimizaciones)

---

### Escenario: 30 consultas/día (930/mes)

| Plan | Sin Optimizaciones | Con Optimizaciones |
|------|-------------------|-------------------|
| **RunPod RTX 3090** | $0.009/consulta | $0.003-0.005/consulta |
| **AWS Reserved** | $0.005-0.007/consulta | $0.002-0.003/consulta |
| **AWS Spot** | $0.002-0.005/consulta | $0.0006-0.002/consulta |
| **GCP Preemptible** | $0.002-0.004/consulta | $0.0005-0.002/consulta |

**Costo mensual por médico**:
- RunPod RTX 3090: $2.79-4.65/mes (con optimizaciones)
- AWS Reserved: $1.86-2.79/mes (con optimizaciones)
- AWS Spot: $0.56-1.86/mes (con optimizaciones)
- GCP Preemptible: $0.47-1.86/mes (con optimizaciones)

---

### Escenario: 50 consultas/día (1,550/mes)

| Plan | Sin Optimizaciones | Con Optimizaciones |
|------|-------------------|-------------------|
| **RunPod RTX 3090** | $0.005/consulta | $0.002-0.003/consulta |
| **AWS Reserved** | $0.003-0.004/consulta | $0.001-0.002/consulta |
| **AWS Spot** | $0.001-0.003/consulta | $0.0004-0.001/consulta |
| **GCP Preemptible** | $0.001-0.002/consulta | $0.0003-0.001/consulta |

**Costo mensual por médico**:
- RunPod RTX 3090: $3.10-4.65/mes (con optimizaciones)
- AWS Reserved: $1.55-3.10/mes (con optimizaciones)
- AWS Spot: $0.62-1.55/mes (con optimizaciones)
- GCP Preemptible: $0.47-1.55/mes (con optimizaciones)

---

## Recomendaciones

### 🏆 Más Económico
**GCP Preemptible o AWS Spot con optimizaciones**
- Costo: **$0.0008-0.003/consulta**
- Ideal para: Startups, proyectos con presupuesto limitado
- ⚠️ Consideración: Riesgo de interrupciones

### 🛡️ Más Estable
**RunPod RTX 3090 con optimizaciones**
- Costo: **$0.005-0.008/consulta**
- Ideal para: Producción crítica, sin tolerancia a interrupciones
- ✅ Ventaja: Precio fijo, sin sorpresas

### ⚖️ Balance Precio/Estabilidad
**AWS Reserved con optimizaciones**
- Costo: **$0.0025-0.005/consulta**
- Ideal para: Producción con presupuesto medio
- ✅ Ventaja: Escalado automático + precio razonable

---

## Notas Importantes

1. **Optimizaciones**: Los costos con optimizaciones asumen que todas las 12 optimizaciones del documento `OPTIMIZACIONES_CODIGO.md` están implementadas, lo que reduce el costo base en 40-60%.

2. **Volumen**: A mayor volumen de consultas, menor costo por consulta (economías de escala).

3. **Tier Gratuito HuggingFace**: Si usas HuggingFace API en lugar de procesamiento local, el tier gratuito de 30K requests/mes puede reducir significativamente los costos adicionales.

4. **Caché**: Con caché agresivo (TTL extendido), el costo real puede ser aún menor ya que muchas consultas similares se reutilizan sin procesamiento.

5. **Consultas Simples**: Con procesamiento selectivo, las consultas simples no usan GPU, reduciendo aún más el costo promedio.

---

## Cálculo Personalizado

Para calcular el costo exacto para tu caso:

```
Costo por consulta = (Costo mensual del plan ÷ Número de médicos) ÷ Consultas por médico por mes
```

**Ejemplo**:
- Plan: RunPod RTX 3090 con optimizaciones = $3-5/médico/mes
- Consultas: 25/día = 775/mes
- Costo por consulta: $3 ÷ 775 = $0.0039/consulta (mínimo)
- Costo por consulta: $5 ÷ 775 = $0.0065/consulta (máximo)

---

## Referencias

- [Optimizaciones desde el Código](./OPTIMIZACIONES_CODIGO.md)
- [Estrategias de Reducción de Costo](./ESTRATEGIAS_REDUCCION_COSTO.md)

