# Optimizaciones desde el Código

## Resumen

Este documento lista todas las optimizaciones que se pueden implementar **desde nuestro lado** (código) para reducir los costos de procesamiento con GPU.

**Costo base actual**: $8.36/médico/mes (RunPod RTX 3090)  
**Objetivo**: Reducir a $3-5/médico/mes mediante optimizaciones de código  
**Reducción esperada**: 40-60% del costo base

---

## Optimizaciones Propuestas

### 1. Compresión de Audio Agresiva

**Descripción**: Comprimir audio localmente antes de enviar a GPU para Speech-to-Text.

**Estrategia**:
- Reducir sample rate a 16kHz (suficiente para STT, vs 44.1kHz estándar)
- Convertir a mono (vs stereo)
- Usar formato OPUS (más eficiente que WAV/MP3)
- Bitrate 32kbps (suficiente para voz)

**Ahorro estimado**: $2-3/médico/mes  
**Dificultad**: Media  
**Prioridad**: 🔴 Alta

---

### 2. Eliminación de Silencios

**Descripción**: Detectar y eliminar silencios del audio antes de procesar con Speech-to-Text.

**Estrategia**:
- Detectar silencios al inicio y final del audio
- Eliminar silencios largos en medio del audio
- Procesar solo partes con contenido de voz

**Ahorro estimado**: $1-2/médico/mes  
**Dificultad**: Baja  
**Prioridad**: 🟡 Media

---

### 3. Chunking Inteligente de Audio

**Descripción**: Dividir audio largo en chunks y procesar solo las partes con voz.

**Estrategia**:
- Dividir audio en segmentos de 30 segundos
- Detectar qué chunks tienen contenido de voz
- Procesar solo chunks con voz (saltar silencios)

**Ahorro estimado**: $1/médico/mes  
**Dificultad**: Media  
**Prioridad**: 🟡 Media

---

### 4. Procesamiento Selectivo (Solo Consultas Complejas)

**Descripción**: Detectar consultas simples y usar reglas predefinidas en lugar de IA.

**Estrategia**:
- Identificar patrones simples: "dolor de cabeza", "fiebre", "tos", etc.
- Textos muy cortos (< 50 caracteres) = simple
- Consultas simples → Reglas predefinidas (sin GPU)
- Consultas complejas → Procesar con GPU

**Ahorro estimado**: $2.50-3.50/médico/mes  
**Dificultad**: Media  
**Prioridad**: 🔴 Alta

---

### 5. Respuestas Pre-Definidas para Casos Comunes

**Descripción**: Base de datos de respuestas comunes que se reutilizan cuando hay alta similitud.

**Estrategia**:
- Guardar respuestas de IA en base de datos
- Cuando llega nueva consulta, buscar respuestas similares (similitud > 0.85)
- Si encuentra → Reutilizar respuesta (sin GPU)
- Si no encuentra → Procesar con GPU y guardar para futuro

**Ahorro estimado**: $1.50-2.50/médico/mes  
**Dificultad**: Media  
**Prioridad**: 🟡 Alta

---

### 6. Procesamiento Diferido para Embeddings SNOMED

**Descripción**: Procesar asociación a términos SNOMED de forma diferida (no bloquea al médico).

**Estrategia**:
- El médico ve la consulta procesada y formateada inmediatamente
- La asociación SNOMED se procesa en segundo plano (cola de trabajos)
- Se actualiza la consulta cuando SNOMED esté listo
- No bloquea la confirmación/aceptación del médico

**Ahorro estimado**: $1.50-2.50/médico/mes  
**Dificultad**: Alta  
**Prioridad**: 🟡 Alta

**Nota**: Esta es la única optimización diferida que aplica, ya que el médico no necesita SNOMED inmediatamente para confirmar la consulta.

---

### 7. Batch Processing para Tareas No-Críticas

**Descripción**: Agrupar múltiples requests similares y procesarlos juntos.

**Estrategia**:
- Agrupar requests de embeddings SNOMED
- Agrupar análisis de historiales antiguos
- Procesar en batch cuando se alcanza un tamaño (ej: 10 requests)
- No aplica a consultas médicas (deben ser inmediatas)

**Ahorro estimado**: $1-2/médico/mes  
**Dificultad**: Baja  
**Prioridad**: 🟢 Media

---

### 8. CPU para Tareas Simples

**Descripción**: Usar CPU para tareas simples que no requieren GPU.

**Estrategia**:
- Limpieza de texto → CPU
- Normalización → CPU
- Tokenización → CPU
- Detección de idioma → CPU
- Solo usar GPU para tareas complejas (STT, análisis, corrección compleja)

**Ahorro estimado**: $0.80-1.20/médico/mes  
**Dificultad**: Media  
**Prioridad**: 🟢 Media

---

### 9. Optimización de Memoria/VRAM

**Descripción**: Cargar y descargar modelos dinámicamente según uso.

**Estrategia**:
- Cargar solo modelos necesarios en memoria
- Descargar modelos no usados recientemente
- Mantener máximo 2-3 modelos en memoria simultáneamente
- Cargar modelos bajo demanda

**Ahorro estimado**: $1.50-2.50/médico/mes  
**Dificultad**: Alta  
**Prioridad**: 🟡 Media

**Beneficio**: Permite más médicos por GPU (20-30% más eficiencia)

---

### 10. Pipeline Optimizado (Evitar Procesamiento Redundante)

**Descripción**: Evitar procesar lo mismo múltiples veces, reutilizar resultados intermedios.

**Estrategia**:
- Si texto ya está bien formateado → Saltar corrección
- Reutilizar texto corregido para estructuración (no volver a procesar)
- SNOMED solo para términos nuevos (no repetir términos ya procesados)
- Cachear resultados intermedios del pipeline

**Ahorro estimado**: $1-1.50/médico/mes  
**Dificultad**: Media  
**Prioridad**: 🟡 Media

---

### 11. Compresión de Datos en Tránsito

**Descripción**: Comprimir datos antes de enviar a GPU para reducir transferencia.

**Estrategia**:
- Comprimir JSON antes de enviar a GPU
- Usar compresión gzip (nivel 6, balance)
- Descomprimir en el procesador GPU

**Ahorro estimado**: $0.40-0.80/médico/mes  
**Dificultad**: Baja  
**Prioridad**: 🟢 Baja

---

### 12. Pre-Procesamiento Local Sin GPU

**Descripción**: Hacer pre-procesamiento básico localmente (sin usar GPU).

**Estrategia**:
- Limpieza de texto → Local (CPU)
- Normalización → Local (CPU)
- Detección de idioma → Local (CPU)
- Tokenización → Local (CPU)
- Solo enviar a GPU lo que realmente necesita procesamiento complejo

**Ahorro estimado**: $0.80-1.20/médico/mes  
**Dificultad**: Media  
**Prioridad**: 🟢 Media

---

## Resumen de Optimizaciones

| # | Optimización | Ahorro Estimado | Dificultad | Prioridad |
|---|--------------|-----------------|------------|-----------|
| 1 | Compresión de Audio | $2-3/médico | Media | 🔴 Alta |
| 2 | Eliminación de Silencios | $1-2/médico | Baja | 🟡 Media |
| 3 | Chunking Inteligente | $1/médico | Media | 🟡 Media |
| 4 | Procesamiento Selectivo | $2.50-3.50/médico | Media | 🔴 Alta |
| 5 | Respuestas Pre-Definidas | $1.50-2.50/médico | Media | 🟡 Alta |
| 6 | Procesamiento Diferido SNOMED | $1.50-2.50/médico | Alta | 🟡 Alta |
| 7 | Batch Processing | $1-2/médico | Baja | 🟢 Media |
| 8 | CPU para Tareas Simples | $0.80-1.20/médico | Media | 🟢 Media |
| 9 | Optimización de Memoria | $1.50-2.50/médico | Alta | 🟡 Media |
| 10 | Pipeline Optimizado | $1-1.50/médico | Media | 🟡 Media |
| 11 | Compresión de Datos | $0.40-0.80/médico | Baja | 🟢 Baja |
| 12 | Pre-Procesamiento Local | $0.80-1.20/médico | Media | 🟢 Media |

**Total ahorro potencial**: $15-22/médico/mes

---

## Plan de Implementación

### Fase 1: Quick Wins (1-2 semanas de desarrollo)

**Optimizaciones**:
1. Compresión de Audio
2. Eliminación de Silencios
3. Procesamiento Selectivo
4. Batch Processing

**Ahorro esperado**: $6-9/médico/mes  
**Tiempo**: 32-46 horas de desarrollo (1 desarrollador)

---

### Fase 2: Optimizaciones Medias (2-3 semanas de desarrollo)

**Optimizaciones**:
5. Respuestas Pre-Definidas
6. Procesamiento Diferido SNOMED
7. CPU para Tareas Simples
8. Pipeline Optimizado

**Ahorro esperado**: $5-8/médico/mes  
**Tiempo**: 56-84 horas de desarrollo (1 desarrollador)

---

### Fase 3: Optimizaciones Avanzadas (3-4 semanas de desarrollo)

**Optimizaciones**:
9. Optimización de Memoria
10. Chunking Inteligente
11. Pre-Procesamiento Local
12. Compresión de Datos

**Ahorro esperado**: $4-5/médico/mes  
**Tiempo**: 74-102 horas de desarrollo (1 desarrollador)

---

## Costo Final Esperado

**Costo base**: $8.36/médico/mes (RunPod RTX 3090)

**Con todas las optimizaciones**:
- Ahorro total: $15-22/médico/mes
- **Costo final**: **$3-5/médico/mes** (o incluso menos)

**Reducción total**: 40-60% del costo base

---

## Notas Importantes

### Procesamiento Inmediato (Crítico)
Las siguientes tareas **DEBEN** ser inmediatas porque el médico está esperando:
- Speech-to-Text (audio a texto)
- Expansión de abreviaturas
- Corrección ortográfica (si es texto escrito)
- Estructuración/Formateo de consulta
- Mostrar resultado al médico para confirmar

### Procesamiento Diferido (No-Crítico)
Las siguientes tareas **PUEDEN** ser diferidas:
- **Embeddings SNOMED** (el médico no necesita esto inmediatamente para confirmar)
- Análisis de historiales antiguos
- Generación de reportes
- Búsquedas semánticas (si no bloquean la UI)

### Ejecución Automática
Una vez implementadas, **todas las optimizaciones se ejecutan automáticamente** sin intervención manual. El sistema decide cuándo aplicar cada optimización basándose en reglas predefinidas.

---

## Referencias

- [Estimación de Costos de Infraestructura](./ESTIMACION_COSTOS_INFRAESTRUCTURA.md)
- [Optimización de Costos HuggingFace](./OPTIMIZACION_COSTOS_HUGGINGFACE.md)
