# Optimización de Costos con HuggingFace

## Resumen Ejecutivo

Este documento describe todas las estrategias implementadas para optimizar el uso de modelos de HuggingFace y reducir costos al mínimo posible, manteniendo la calidad del servicio.

## Estrategias de Optimización Implementadas

### 1. Sistema de Caché Multi-Nivel

**Objetivo**: Evitar llamadas redundantes a la API reutilizando respuestas anteriores.

**Implementación**:
- **Caché en memoria**: Para acceso rápido a respuestas frecuentes
- **Caché persistente (FileCache)**: Para respuestas que pueden reutilizarse entre sesiones
- **TTL configurable**: Diferentes tiempos de expiración según el tipo de operación
  - Respuestas de IA: 1 hora (configurable en `ia_cache_ttl`)
  - Correcciones de texto: 2 horas (configurable en `correccion_cache_ttl`)
  - Transcripciones de audio: 24 horas
  - Embeddings: 1 hora

**Archivos modificados**:
- `common/components/IAManager.php` - Caché de respuestas de IA
- `common/components/EmbeddingsManager.php` - Caché de embeddings
- `common/components/ProcesadorTextoMedico.php` - Ya tenía caché implementado

**Ahorro estimado**: 30-50% de reducción en llamadas a API para consultas similares.

### 2. Selección de Modelos Optimizados

**Objetivo**: Usar modelos más pequeños y eficientes que mantengan calidad suficiente.

**Modelos configurados** (en `frontend/config/params.php`):

- **Corrección de texto**: `PlanTL-GOB-ES/roberta-base-biomedical-clinical-es`
  - Especializado en español médico
  - Modelo base (más económico que large)
  
- **Análisis de consultas**: `microsoft/DialoGPT-small` o `HuggingFaceH4/zephyr-7b-beta`
  - Modelos pequeños pero efectivos
  - Alternativa a modelos 70B mucho más costosos

- **Embeddings**: `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2`
  - Gratis en Inference API
  - Optimizado para español
  - Alternativa: `PlanTL-GOB-ES/roberta-large-bne` (mejor calidad, más costoso)

- **Speech-to-Text**: `jonatasgrosman/wav2vec2-xlsr-53-spanish` (por defecto)
  - Modelo económico especializado en español
  - Alternativas: `wav2vec2-large-xlsr-53-spanish` (balanceado) o `whisper-large-v2` (premium)

**Ahorro estimado**: 60-80% comparado con modelos grandes como Llama 3.1 70B.

### 3. Optimización de Prompts

**Objetivo**: Reducir tokens procesados manteniendo la calidad.

**Estrategias aplicadas**:
- Prompts más concisos eliminando texto innecesario
- Eliminación de ejemplos extensos cuando no son críticos
- Instrucciones directas sin explicaciones largas

**Ejemplo de optimización**:

**Antes** (más largo):
```
Eres un asistente médico especializado en [servicio]. Tu tarea es analizar el texto clínico que se te proporciona y extraer información estructurada siguiendo la tabla de categorías indicada.  
Pasos a seguir:
1. **Extracción**
- Para cada categoría extrae el contenido relevante...
```

**Después** (optimizado):
```
Analiza el texto clínico y extrae información estructurada en JSON. Categorías: [lista]. Si no hay información para una categoría, usa [].
```

**Ahorro estimado**: 40-60% de reducción en tokens procesados.

### 4. Parámetros Optimizados

**Configuración en `frontend/config/params.php`**:

```php
'hf_max_length' => 500,        // Limitar longitud de respuestas
'hf_temperature' => 0.2,       // Baja temperatura para tareas determinísticas
'wait_for_model' => false      // No esperar cold starts (evita timeouts costosos)
```

**Ahorro estimado**: 20-30% en tiempo de procesamiento y costos.

### 5. Rate Limiting Inteligente

**Objetivo**: Evitar errores costosos y respetar límites de la API.

**Implementación** (`common/components/HuggingFaceRateLimiter.php`):
- Intervalo mínimo entre requests (100ms)
- Circuit breaker: se abre después de 5 errores consecutivos
- Backoff exponencial para rate limits (429)
- Priorización de requests críticos

**Ahorro estimado**: Evita costos por errores y re-intentos innecesarios.

### 6. Deduplicación de Requests

**Objetivo**: Detectar y reutilizar requests idénticos o muy similares.

**Implementación** (`common/components/RequestDeduplicator.php`):
- Cache de requests recientes (5 minutos)
- Detección de similitud (95% de similitud = duplicado)
- Algoritmo combinado: Levenshtein + comparación de palabras

**Ahorro estimado**: 10-20% adicional en casos con muchos requests similares.

### 7. Batch Processing

**Objetivo**: Agrupar múltiples requests cuando sea posible.

**Implementación** (`common/components/HuggingFaceBatchProcessor.php`):
- Cola de requests por endpoint
- Procesamiento en lotes (hasta 10 requests)
- Callbacks para procesamiento asíncrono

**Nota**: HuggingFace Inference API no soporta batch nativo, pero agrupar requests optimiza conexiones.

### 8. Optimización de Audio (Speech-to-Text)

**Estrategias** (`common/components/SpeechToTextManager.php`):

- **Modelo por defecto económico**: `wav2vec2-xlsr-53-spanish`
- **Caché de transcripciones**: 24 horas (audio raramente cambia)
- **Pre-procesamiento**: Validación de tamaño (máx 25MB)
- **Configuración**: `wait_for_model=false` para evitar cold starts

**Ahorro estimado**: 70-80% comparado con modelos premium como Whisper Large.

### 9. Migración de Embeddings a HuggingFace

**Antes**: OpenAI embeddings (`text-embedding-3-small`)
**Después**: HuggingFace embeddings (`paraphrase-multilingual-MiniLM-L12-v2`)

**Ventajas**:
- Gratis en Inference API (hasta cierto límite)
- Especializado en multilingüe (incluye español)
- Caché mejorado con TTL de 1 hora

**Ahorro estimado**: 100% en costos de embeddings (gratis vs pagado).

## Configuración

### Parámetros en `frontend/config/params.php`

```php
// API Keys
'hf_api_key' => '', // Tu API key de HuggingFace

// Modelos
'hf_model_text_gen' => 'HuggingFaceH4/zephyr-7b-beta',
'hf_model_correction' => 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es',
'hf_model_analysis' => 'microsoft/DialoGPT-small',
'hf_embedding_model' => 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2',
'hf_stt_model' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish',

// Optimización
'hf_max_length' => 500,
'hf_temperature' => 0.2,
'ia_cache_ttl' => 3600,
'correccion_cache_ttl' => 7200,
```

### Cambiar Proveedor de IA

En `frontend/config/params.php`:
```php
'ia_proveedor' => 'huggingface', // 'ollama', 'groq', 'openai', 'huggingface'
```

## Estimación de Costos

### Escenario Base (100 consultas/día)

**Sin optimizaciones**:
- Corrección de texto: ~$0.10 por consulta = $10/día
- Análisis de consulta: ~$0.15 por consulta = $15/día
- Embeddings: ~$0.05 por consulta = $5/día
- **Total: ~$30/día = ~$900/mes**

**Con optimizaciones**:
- Corrección (con caché 50%): ~$0.05 por consulta = $5/día
- Análisis (modelo pequeño + caché): ~$0.03 por consulta = $3/día
- Embeddings (HuggingFace gratis): $0/día
- **Total: ~$8/día = ~$240/mes**

**Ahorro: ~73% ($660/mes)**

### Factores que Afectan el Ahorro

1. **Tasa de caché**: Mayor reutilización = mayor ahorro
2. **Volumen**: Más consultas = más ahorro absoluto
3. **Diversidad de consultas**: Menos diversidad = más caché hits
4. **Uso de audio**: Agregar STT aumenta costos pero optimizado

## Mejores Prácticas

### Para Desarrolladores

1. **Usar caché siempre que sea posible**: Los componentes ya lo implementan automáticamente
2. **Evitar prompts largos innecesarios**: Usar prompts concisos
3. **Configurar TTL apropiados**: Ajustar según frecuencia de cambios
4. **Monitorear rate limits**: El sistema maneja esto automáticamente, pero revisar logs

### Para Administradores

1. **Configurar API key correctamente**: En `params.php` o `params-local.php`
2. **Ajustar modelos según necesidades**: Cambiar en `params.php` si se necesita más calidad
3. **Monitorear uso**: Revisar logs de Yii para detectar patrones
4. **Ajustar TTL de caché**: Según patrones de uso observados

## Troubleshooting

### Problema: Rate Limits Frecuentes

**Solución**: 
- El `HuggingFaceRateLimiter` maneja esto automáticamente
- Verificar logs para ver si hay muchos requests
- Considerar aumentar intervalo mínimo si es necesario

### Problema: Caché No Funciona

**Verificar**:
- Que `Yii::$app->cache` esté configurado correctamente
- Permisos de escritura en directorio de caché (FileCache)
- TTL no haya expirado

### Problema: Modelos No Responden

**Verificar**:
- API key válida
- Modelo existe en HuggingFace
- Internet/conectividad
- Logs de errores en Yii

## Próximas Optimizaciones (Futuro)

1. **Queue System Asíncrono**: Para procesar requests no críticos en background
2. **Pre-computación**: Pre-calcular embeddings de términos SNOMED comunes
3. **Modelos Locales**: Para tareas muy frecuentes, considerar modelos locales
4. **Análisis de Patrones**: Identificar consultas más comunes para pre-cachear

## Referencias

- [HuggingFace Inference API](https://huggingface.co/docs/api-inference/index)
- [Modelos Optimizados para Español](https://huggingface.co/models?language=es)
- [Documentación de Optimización de Costos](https://huggingface.co/docs/api-inference/performance)
