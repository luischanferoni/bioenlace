# Estimación de Costos de Infraestructura - Procesamiento 100% Local

## Resumen Ejecutivo

Este documento proporciona una estimación detallada de los costos mensuales para el hosting e infraestructura del sistema médico Bioenlace, considerando **procesamiento 100% local** (sin fallback a HuggingFace API).

**Escenario base**: 100 médicos con procesamiento local usando Ollama y modelos de GPU.

**IMPORTANTE**: 
- La mayoría de las consultas médicas (80-90%) se realizan por audio (dictado)
- Flujo: Audio → Texto (Speech-to-Text) → Formateo/Estructuración → Asociación SNOMED
- **NO se incluye fallback a HuggingFace API** - Todo el procesamiento es local

---

## Costo Real Sin Fallback (100% Local)

### Escenario: 100 Médicos, Solo GPU (Sin HuggingFace)

| Componente | Costo por Médico (USD/mes) |
|------------|----------------------------|
| **GPU (procesamiento local)** | $3-12/médico |
| **HuggingFace API** | $0 (sin fallback) |
| **TOTAL** | **$3-12/médico/mes** |

---

## Opciones de GPU y Costos Detallados

### Opción 1: RunPod (Recomendado para Precio Fijo)

#### RTX 3090
- **Costo**: ~$209/mes por instancia
- **Capacidad**: 20-30 médicos por instancia
- **Para 100 médicos**: 4 instancias = $836/mes
- **Costo por médico**: **$8.36/médico/mes**

#### RTX 4090
- **Costo**: ~$281/mes por instancia
- **Capacidad**: 25-35 médicos por instancia
- **Para 100 médicos**: 3 instancias = $843/mes
- **Costo por médico**: **$8.43/médico/mes**

#### A100 (40GB)
- **Costo**: ~$1,001/mes por instancia
- **Capacidad**: 50-70 médicos por instancia
- **Para 100 médicos**: 2 instancias = $2,002/mes
- **Costo por médico**: **$20.02/médico/mes**

**Ventajas**:
- ✅ Precio fijo (no aumenta con uso)
- ✅ Sin interrupciones
- ✅ Fácil de configurar Ollama
- ✅ Facturación por hora (fácil escalar)

**Desventajas**:
- ❌ Escalado manual (agregar instancias)
- ❌ Menos servicios que AWS/GCP

---

### Opción 2: AWS GPU (Auto-Scaling)

#### g4dn.xlarge (1x NVIDIA T4)
- **On-Demand**: $0.526/hora = $380/mes
- **Reserved (1 año)**: $228/mes (40% descuento)
- **Spot**: $76-152/mes (60-80% descuento)

#### Para 100 Médicos
- **Auto-scaling**: 2-3 instancias promedio
- **On-Demand**: $760-1,140/mes = **$7.60-11.40/médico**
- **Reserved**: $456-684/mes = **$4.56-6.84/médico**
- **Spot**: $152-456/mes = **$1.52-4.56/médico**

**Ventajas**:
- ✅ Escalado automático
- ✅ Alta disponibilidad
- ✅ Spot instances muy baratas

**Desventajas**:
- ❌ Spot puede interrumpirse (AWS avisa 2 minutos antes)
- ❌ Costo variable (puede aumentar)

---

### Opción 3: Vultr GPU Cloud

#### NVIDIA A100
- **Costo**: ~$1.20/hora = $864/mes
- **Capacidad**: 50-70 médicos
- **Para 100 médicos**: 2 instancias = $1,728/mes
- **Costo por médico**: **$17.28/médico/mes**

#### NVIDIA A40
- **Costo**: ~$1.00/hora = $720/mes
- **Capacidad**: 40-60 médicos
- **Para 100 médicos**: 2 instancias = $1,440/mes
- **Costo por médico**: **$14.40/médico/mes**

---

### Opción 4: Google Cloud Platform (GCP)

#### n1-standard-4 + 1x NVIDIA T4
- **On-Demand**: $0.35/hora = $252/mes
- **Preemptible**: $70-126/mes (50-70% descuento)
- **Sustained Use**: $176/mes (30% descuento)

#### Para 100 Médicos
- **Auto-scaling**: 2-3 instancias
- **On-Demand**: $504-756/mes = **$5.04-7.56/médico**
- **Preemptible**: $140-378/mes = **$1.40-3.78/médico**
- **Sustained Use**: $352-528/mes = **$3.52-5.28/médico**

**Ventajas**:
- ✅ Preemptible muy barato
- ✅ Sustained use discounts
- ✅ Auto-scaling

**Desventajas**:
- ❌ Preemptible puede interrumpirse (30 segundos de aviso)
- ❌ Configuración más compleja

---

## Comparación de Costos (100 Médicos, Sin Fallback)

| Proveedor | GPU | Costo Total | Costo/Médico | Escalabilidad | Riesgo |
|-----------|-----|-------------|--------------|---------------|--------|
| **RunPod RTX 3090** | 4 instancias | $836/mes | **$8.36** | Manual | Bajo |
| **RunPod RTX 4090** | 3 instancias | $843/mes | **$8.43** | Manual | Bajo |
| **AWS g4dn (Reserved)** | 2-3 instancias | $456-684/mes | **$4.56-6.84** | Automática | Bajo |
| **AWS g4dn (Spot)** | 2-3 instancias | $152-456/mes | **$1.52-4.56** | Automática | Medio |
| **GCP T4 (Preemptible)** | 2-3 instancias | $140-378/mes | **$1.40-3.78** | Automática | Medio |
| **GCP T4 (Sustained)** | 2-3 instancias | $352-528/mes | **$3.52-5.28** | Automática | Bajo |

---

## Reducciones Adicionales Posibles

### Estrategia 1: Spot/Preemptible Instances

**Impacto**: 50-80% descuento

#### AWS Spot
- **Costo**: $1.52-4.56/médico (vs $7.60-11.40)
- **Ahorro**: 60-80%

#### GCP Preemptible
- **Costo**: $1.40-3.78/médico (vs $5.04-7.56)
- **Ahorro**: 50-70%

**Riesgo**: Las instancias pueden interrumpirse (AWS avisa 2 min, GCP 30 seg).

---

### Estrategia 2: Modelos Cuantizados (4-bit)

**Impacto**: 50-70% menos recursos GPU

**Cómo**:
- Llama 3.1 8B → Llama 3.1 8B 4-bit
- Whisper-medium → Whisper-small
- Menos VRAM = más médicos por GPU

**Costo resultante**:
- RunPod RTX 3090: 40-50 médicos por instancia (vs 20-30)
- Para 100 médicos: 2-3 instancias = $418-627/mes
- **Costo por médico**: **$4.18-6.27/médico**

---

### Estrategia 3: Optimización de Carga

**Impacto**: 20-30% más eficiencia

**Cómo**:
- Batch processing inteligente
- Procesamiento diferido para no-crítico
- Balanceador de carga optimizado

**Costo resultante**:
- 20-30% más médicos por GPU
- RunPod RTX 3090: 25-40 médicos por instancia
- Para 100 médicos: 3 instancias = $627/mes
- **Costo por médico**: **$6.27/médico**

---

### Estrategia 4: Caché Ultra-Agresivo

**Impacto**: 30-50% menos procesamiento

**Cómo**:
- TTL extendido (30 días)
- Caché de fragmentos médicos
- Similitud 0.80 (más permisivo)

**Costo resultante**:
- 30-50% menos requests a GPU
- Efectivamente: 30-50% más médicos por GPU
- RunPod RTX 3090: 30-45 médicos por instancia
- Para 100 médicos: 3 instancias = $627/mes
- **Costo por médico**: **$6.27/médico**

---

## Escenarios Optimizados (Sin Fallback)

### Escenario A: Conservador (Recomendado)

| Estrategia | Costo |
|------------|-------|
| RunPod RTX 3090 (4 instancias) | $8.36/médico |
| Caché optimizado | Incluido |
| **TOTAL** | **$8.36/médico/mes** |

**Ventaja**: Precio fijo, sin interrupciones.

---

### Escenario B: Agresivo

| Estrategia | Costo |
|------------|-------|
| AWS Spot Instances | $1.52-4.56/médico |
| Modelos cuantizados | Incluido |
| Caché ultra-agresivo | Incluido |
| **TOTAL** | **$1.52-4.56/médico/mes** |

**Riesgo**: Posibles interrupciones de instancias.

---

### Escenario C: Extremo (Máximo Ahorro)

| Estrategia | Costo |
|------------|-------|
| GCP Preemptible | $1.40-3.78/médico |
| Modelos cuantizados 4-bit | Incluido |
| Caché ultra-agresivo | Incluido |
| Optimización de carga | Incluido |
| **TOTAL** | **$1.40-3.78/médico/mes** |

**Riesgo**: Alta complejidad, interrupciones posibles.

---

## Resumen: Costos Sin Fallback

| Escenario | Proveedor | Costo/Médico | Reducción vs Base | Riesgo |
|-----------|-----------|--------------|-------------------|--------|
| **Base** | RunPod RTX 3090 | $8.36 | - | Bajo |
| **Conservador** | RunPod RTX 3090 | $8.36 | 0% (estable) | Bajo |
| **Agresivo** | AWS Spot | $1.52-4.56 | 45-82% | Medio |
| **Extremo** | GCP Preemptible | $1.40-3.78 | 55-83% | Medio |

---

## Recomendación Final (Sin Fallback)

### Para 100 Médicos

#### Opción 1: RunPod RTX 3090 (Recomendado)
- **Costo**: $8.36/médico/mes
- **Ventaja**: Precio fijo, sin interrupciones
- **Escalabilidad**: Manual (agregar instancias)
- **Riesgo**: Bajo

#### Opción 2: AWS Spot Instances
- **Costo**: $1.52-4.56/médico/mes
- **Ventaja**: Más barato, auto-scaling
- **Riesgo**: Interrupciones posibles (2 min aviso)

#### Opción 3: GCP Preemptible
- **Costo**: $1.40-3.78/médico/mes
- **Ventaja**: Más barato, auto-scaling
- **Riesgo**: Interrupciones más frecuentes (30 seg aviso)

---

## Respuesta Directa

### 1. Costo Sin Fallback (Solo GPU)

**Opciones principales**:
- **RunPod**: $8.36/médico/mes
- **AWS Reserved**: $4.56-6.84/médico/mes
- **AWS Spot**: $1.52-4.56/médico/mes
- **GCP Preemptible**: $1.40-3.78/médico/mes

### 2. ¿Cuánto Más Se Puede Reducir?

**Sí, se puede reducir más**:

- **Conservador**: $8.36/médico (RunPod, estable)
- **Agresivo**: $1.52-4.56/médico (AWS Spot, 45-82% reducción)
- **Extremo**: $1.40-3.78/médico (GCP Preemptible, 55-83% reducción)

### 3. ¿Es Posible Llegar a $1.40/médico?

**Sí, con**:
- GCP Preemptible Instances
- Modelos cuantizados
- Caché ultra-agresivo
- Optimización de carga
- **Riesgo**: Interrupciones de instancias

---

## Conclusión

**Sin fallback, el costo es solo GPU**:
- **Mínimo realista**: $1.40-3.78/médico/mes (GCP Preemptible)
- **Recomendado**: $4.56-8.36/médico/mes (AWS Reserved o RunPod)

**Modelos Recomendados para Procesamiento Local**:
- **Speech-to-Text**: `whisper.cpp` (C++ optimizado) o `whisper-medium` en Ollama
- **Corrección**: `llama3.1:8b` o `mistral:7b`
- **Análisis**: `llama3.1:8b` o `phi-3:medium`
- **Embeddings**: `nomic-embed-text` (gratis, local)

---

## Referencias

- [Documentación de Optimización de Costos HuggingFace](./OPTIMIZACION_COSTOS_HUGGINGFACE.md)
- [RunPod Pricing](https://www.runpod.io/pricing)
- [AWS GPU Pricing](https://aws.amazon.com/ec2/pricing/)
- [Google Cloud GPU Pricing](https://cloud.google.com/compute/gpus-pricing)
- [Ollama Documentation](https://ollama.ai/docs)
