# Estrategias para Reducir Costo por Médico a $10 USD/mes

## Objetivo
Reducir el costo por médico de **~$186 USD/mes** a **$10 USD/mes** (reducción del ~95%).

## Resumen Ejecutivo

### Comparación de Costos

| Escenario | Costo por Médico (USD/mes) | Reducción | Tiempo de Implementación |
|-----------|---------------------------|-----------|-------------------------|
| **Actual** | $186.10 | - | - |
| **Fase 1: Quick Wins** | $100-110 | 41-46% | 1-2 semanas |
| **Fase 2: Procesamiento Local** | $20-30 | 84-89% | 2-4 semanas |
| **Fase 3: Optimización Extrema** | **$10** | **95%** | 1-2 semanas |
| **Total tiempo** | - | - | **4-8 semanas** |

### Estrategias Clave (por Impacto)

| Estrategia | Ahorro Estimado | Dificultad | Prioridad |
|------------|----------------|------------|-----------|
| **1. Procesamiento Local (Ollama)** | $150-170/mes | Alta | 🔴 CRÍTICA |
| **2. Caché Ultra-Agresivo** | $18/mes | Baja | 🟡 Alta |
| **3. Modelos Más Pequeños** | $20/mes | Media | 🟡 Alta |
| **4. Límites de Uso** | $42/mes | Baja | 🟡 Alta |
| **5. Procesamiento Selectivo** | $15/mes | Media | 🟢 Media |
| **6. Compresión de Audio** | $9/mes | Baja | 🟢 Media |
| **7. Infraestructura Compartida** | $7/mes | Media | 🟢 Media |
| **8. Tier Gratuito HuggingFace** | $50-70/mes | Baja | 🟡 Alta |
| **9. Procesamiento Híbrido** | $100-120/mes | Alta | 🔴 CRÍTICA |
| **10. Optimización de Prompts** | $10-15/mes | Baja | 🟢 Baja |

## Análisis del Costo Actual

### Desglose Actual por Médico
- **Consultas médicas con audio**: $2.70/día = $81/mes
- **Consultas médicas escritas**: $0.20/día = $6/mes
- **Consultas en acciones**: $0.09/día = $2.70/mes
- **Chatbot de pacientes**: $0.68/día = $20.40/mes
- **Chat médico**: $0.30/día = $9/mes
- **Infraestructura**: $9.57/mes
- **Total**: ~$186.10 USD/mes

### Componentes Más Costosos
1. **Speech-to-Text** (audio): $0.72/día = $21.60/mes (26% del costo)
2. **Análisis de consultas**: $0.80/día = $24/mes (29% del costo)
3. **Corrección de texto**: $0.60/día = $18/mes (22% del costo)
4. **Chatbot pacientes**: $0.68/día = $20.40/mes (24% del costo)

---

## Estrategias de Reducción de Costos

### Estrategia 1: Procesamiento Local con Ollama (CRÍTICA)

**Impacto**: Reducción del 60-80% en costos de HuggingFace

#### Implementación
- **Speech-to-Text**: Usar `whisper.cpp` local o modelo Whisper en Ollama
- **Corrección de texto**: Usar modelo local (Llama 3.1 8B o más pequeño)
- **Análisis de consultas**: Usar modelo local para estructuración
- **Embeddings SNOMED**: Usar modelo local de embeddings

#### Ventajas
- **Costo**: $0 USD en API calls (solo infraestructura)
- **Latencia**: Similar o mejor (sin latencia de red)
- **Privacidad**: Datos nunca salen del servidor

#### Desventajas
- **Infraestructura**: Requiere GPU o CPU potente
- **Mantenimiento**: Actualización de modelos manual

#### Costo Estimado
- **Servidor con GPU** (NVIDIA T4 o similar): $50-100 USD/mes
- **Dividido entre 10 médicos**: $5-10 USD/médico/mes
- **Ahorro**: $150-170 USD/médico/mes

#### Modelos Recomendados
- **Speech-to-Text**: `whisper.cpp` (C++ optimizado) o `whisper-medium` en Ollama
- **Corrección**: `llama3.1:8b` o `mistral:7b`
- **Análisis**: `llama3.1:8b` o `phi-3:medium`
- **Embeddings**: `nomic-embed-text` (gratis, local)

---

### Estrategia 2: Caché Ultra-Agresivo

**Impacto**: Reducción del 40-60% en requests duplicados

#### Mejoras de Caché
1. **TTL Extendido**:
   - Respuestas de IA: 24 horas → **7 días**
   - Embeddings: 1 hora → **30 días**
   - Correcciones: 12 horas → **7 días**
   - Transcripciones de audio: 24 horas → **30 días** (aunque raro, algunos dictados se repiten)

2. **Caché Inteligente por Similitud**:
   - Reducir umbral de similitud de 0.95 → **0.85**
   - Agrupar textos médicos similares (ej: "dolor de cabeza" = "cefalea")

3. **Caché de Fragmentos**:
   - Cachear frases comunes médicas
   - Reutilizar correcciones de términos médicos frecuentes

#### Implementación
```php
// En IAManager.php
private const CACHE_TTL = 604800; // 7 días

// En EmbeddingsManager.php
private const CACHE_TTL = 2592000; // 30 días

// En RequestDeduplicator.php
private const SIMILITUD_MINIMA = 0.85; // Más permisivo
```

#### Ahorro Estimado
- **Consultas médicas**: 50% → 70% hit rate = **-$12/mes**
- **Correcciones**: 50% → 75% hit rate = **-$4.50/mes**
- **Embeddings**: 70% → 90% hit rate = **-$1.80/mes**
- **Total ahorro**: ~$18.30/mes

---

### Estrategia 3: Modelos Más Pequeños y Económicos

**Impacto**: Reducción del 30-50% en costos por request

#### Cambios de Modelos
1. **Speech-to-Text**:
   - Actual: `wav2vec2-large-xlsr-53-spanish` ($0.04/request)
   - **Nuevo**: `wav2vec2-xlsr-53-spanish` (más pequeño) = **$0.02/request**

2. **Análisis de Consultas**:
   - Actual: `zephyr-7b-beta` (7B parámetros)
   - **Nuevo**: `mistral-7b-instruct` o `phi-3-mini` = **50% más barato**

3. **Corrección de Texto**:
   - Actual: `roberta-base-biomedical-clinical-es`
   - **Nuevo**: Modelo más pequeño o procesamiento local

#### Ahorro Estimado
- **Speech-to-Text**: 50% reducción = **-$10.80/mes**
- **Análisis**: 40% reducción = **-$9.60/mes**
- **Total ahorro**: ~$20.40/mes

---

### Estrategia 4: Procesamiento Diferido y Batch

**Impacto**: Reducción del 20-30% en costos por optimización de requests

#### Implementación
1. **Cola de Procesamiento**:
   - Agrupar requests similares en batches
   - Procesar durante horarios de menor costo (si aplica)

2. **Procesamiento No-Crítico Diferido**:
   - Correcciones de texto: procesar en batch cada hora
   - Embeddings SNOMED: procesar en batch cada 30 minutos
   - Análisis de consultas: procesar inmediatamente (crítico)

3. **Priorización**:
   - Consultas médicas: Alta prioridad (inmediato)
   - Chatbot pacientes: Media prioridad (puede esperar 5-10 segundos)
   - Consultas en acciones: Baja prioridad (puede esperar 30 segundos)

#### Ahorro Estimado
- **Batch processing**: 25% reducción = **-$15/mes**

---

### Estrategia 5: Compresión y Optimización de Audio

**Impacto**: Reducción del 20-40% en costos de Speech-to-Text

#### Optimizaciones
1. **Compresión de Audio**:
   - Reducir calidad a 16kHz (suficiente para STT)
   - Comprimir a formato más eficiente (OPUS, AAC)
   - Reducir tamaño de archivo en 50-70%

2. **Detección de Silencios**:
   - Eliminar silencios al inicio/final
   - Chunking inteligente (solo procesar partes con voz)

3. **Pre-procesamiento Local**:
   - Normalización de audio local
   - Reducción de ruido local (sin costo de API)

#### Ahorro Estimado
- **Tamaño reducido**: 40% menos tokens = **-$8.64/mes**

---

### Estrategia 6: Límites de Uso y Procesamiento Selectivo

**Impacto**: Reducción del 30-50% en volumen total

#### Límites Sugeridos
1. **Consultas Médicas**:
   - **Plan Básico**: 15 consultas/día (vs 20 actuales)
   - **Plan Estándar**: 25 consultas/día
   - **Plan Premium**: Ilimitadas

2. **Chatbot de Pacientes**:
   - **Límite**: 30 interacciones/día (vs 45 actuales)
   - Respuestas pre-definidas para consultas comunes
   - IA solo para consultas complejas

3. **Procesamiento Selectivo**:
   - **Consultas simples**: No usar IA (reglas predefinidas)
   - **Consultas complejas**: Usar IA completa
   - **Detección automática**: Clasificar antes de procesar

#### Ahorro Estimado
- **Reducción de volumen**: 35% = **-$42/mes**

---

### Estrategia 7: Modelos Gratuitos y Open Source

**Impacto**: Reducción del 50-70% en costos de API

#### Opciones Gratuitas
1. **HuggingFace Inference Endpoints** (gratis hasta cierto límite):
   - 30,000 requests/mes gratis
   - Suficiente para ~1,000 consultas médicas/mes

2. **Modelos Completamente Gratuitos**:
   - **Speech-to-Text**: `whisper-tiny` (local) = $0
   - **Embeddings**: `sentence-transformers/all-MiniLM-L6-v2` (gratis)
   - **Análisis**: Modelos locales con Ollama

3. **Tier Gratuito de HuggingFace**:
   - Usar tier gratuito para desarrollo/testing
   - Migrar a pago solo para producción crítica

#### Ahorro Estimado
- **Tier gratuito**: 100% ahorro en primeros 30K requests = **-$50-70/mes** (dependiendo del uso)

---

### Estrategia 8: Infraestructura Compartida y Escalado

**Impacto**: Reducción del 50-70% en costos fijos

#### Optimización de Infraestructura
1. **Servidor Compartido**:
   - 10-20 médicos por servidor
   - Costo fijo: $50-100/mes
   - **Por médico**: $2.50-10 USD/mes

2. **Base de Datos Optimizada**:
   - Compartir instancia de DB
   - Usar índices eficientes
   - Caché de consultas frecuentes

3. **CDN y Caché Distribuido**:
   - Cloudflare (gratis hasta cierto límite)
   - Caché de respuestas estáticas

#### Ahorro Estimado
- **Infraestructura compartida**: 70% reducción = **-$6.70/mes**

---

### Estrategia 9: Procesamiento Híbrido (Local + Cloud)

**Impacto**: Reducción del 40-60% en costos totales

#### Arquitectura Híbrida
1. **Local (Ollama)**:
   - Speech-to-Text (whisper local)
   - Corrección de texto
   - Análisis básico de consultas
   - Embeddings SNOMED

2. **Cloud (HuggingFace)**:
   - Solo para consultas complejas
   - Fallback si modelo local falla
   - Análisis avanzado (si necesario)

3. **Lógica de Decisión**:
   - Consultas simples → Local
   - Consultas complejas → Cloud (con límite)
   - Fallback automático si local falla

#### Ahorro Estimado
- **80% local, 20% cloud**: **-$100-120/mes**

---

### Estrategia 10: Optimización de Prompts y Respuestas

**Impacto**: Reducción del 10-20% en tokens procesados

#### Optimizaciones
1. **Prompts Más Cortos**:
   - Reducir prompts en 30-40%
   - Usar templates más eficientes
   - Eliminar contexto innecesario

2. **Respuestas Estructuradas**:
   - Forzar formato JSON estricto
   - Limitar longitud de respuestas
   - Usar few-shot examples más cortos

3. **Streaming de Respuestas**:
   - Procesar solo lo necesario
   - Detener cuando se obtiene respuesta suficiente

#### Ahorro Estimado
- **15% reducción en tokens**: **-$10-15/mes**

---

## Plan de Implementación Recomendado

### Fase 1: Quick Wins (Reducción a ~$50/mes)
1. ✅ **Caché ultra-agresivo** (TTL extendido, similitud 0.85)
2. ✅ **Modelos más pequeños** (wav2vec2 pequeño, modelos 7B)
3. ✅ **Compresión de audio** (16kHz, formato eficiente)
4. ✅ **Límites de uso** (15 consultas/día, 30 interacciones chatbot)
5. ✅ **Procesamiento selectivo** (solo consultas complejas)

**Ahorro esperado**: ~$70-80/mes → **Costo: ~$100-110/mes**

### Fase 2: Procesamiento Local (Reducción a ~$20/mes)
1. ✅ **Ollama local** para corrección y análisis
2. ✅ **whisper.cpp local** para Speech-to-Text
3. ✅ **Embeddings locales** (nomic-embed)
4. ✅ **Infraestructura compartida** (10 médicos/servidor)

**Ahorro esperado**: ~$80-90/mes adicionales → **Costo: ~$20-30/mes**

### Fase 3: Optimización Extrema (Reducción a ~$10/mes)
1. ✅ **Procesamiento híbrido** (90% local, 10% cloud)
2. ✅ **Tier gratuito de HuggingFace** (30K requests gratis)
3. ✅ **Batch processing agresivo**
4. ✅ **Optimización de prompts** (30% más cortos)
5. ✅ **Respuestas pre-definidas** para casos comunes

**Ahorro esperado**: ~$10-20/mes adicionales → **Costo: ~$10/mes**

---

## Cálculo Final: $10 USD/mes por Médico

### Desglose del Costo Objetivo

| Componente | Costo Mensual (USD) |
|------------|---------------------|
| **Infraestructura compartida** (10 médicos) | $2.00 |
| **HuggingFace API** (solo fallback/complejo) | $3.00 |
| **Procesamiento local** (GPU compartida) | $3.00 |
| **Storage y backups** | $1.00 |
| **Monitoreo y logs** | $1.00 |
| **TOTAL** | **~$10.00 USD/mes** |

### Asunciones para $10/mes
1. **10 médicos por servidor** (infraestructura compartida)
2. **90% procesamiento local** (Ollama + whisper.cpp)
3. **10% procesamiento cloud** (solo consultas complejas)
4. **Caché ultra-agresivo** (70-90% hit rate)
5. **Límites de uso** (15 consultas/día, 30 interacciones chatbot)
6. **Tier gratuito de HuggingFace** (30K requests gratis/mes)

---

## Implementación Técnica

### 1. Configuración de Ollama Local

```php
// En IAManager.php - Agregar soporte para Ollama local
public static function getConfiguracionOllama()
{
    return [
        'tipo' => 'ollama',
        'base_url' => 'http://localhost:11434',
        'modelo_correccion' => 'llama3.1:8b',
        'modelo_analisis' => 'llama3.1:8b',
        'modelo_embeddings' => 'nomic-embed-text',
        'timeout' => 60,
        'costo_por_request' => 0, // Gratis (solo infraestructura)
    ];
}
```

### 2. Speech-to-Text Local

```php
// En SpeechToTextManager.php - Agregar opción local
private static function transcribirLocal($audioPath)
{
    // Usar whisper.cpp o whisper en Ollama
    $command = "whisper-cpp -m models/ggml-base.bin -f {$audioPath} -l es";
    exec($command, $output);
    return implode("\n", $output);
}
```

### 3. Caché Ultra-Agresivo

```php
// En params.php
'hf_cache_ttl' => 604800, // 7 días
'embedding_cache_ttl' => 2592000, // 30 días
'correccion_cache_ttl' => 604800, // 7 días
'stt_cache_ttl' => 2592000, // 30 días
'request_similitud_minima' => 0.85, // Más permisivo
```

### 4. Límites de Uso

```php
// Nuevo componente: UsageLimiter.php
class UsageLimiter
{
    private const LIMITES = [
        'consultas_medicas' => 15, // Por día
        'chatbot_pacientes' => 30, // Por día
        'consultas_acciones' => 5, // Por día
    ];
    
    public static function puedeProcesar($tipo, $medicoId)
    {
        $usado = self::getUsoDiario($tipo, $medicoId);
        return $usado < self::LIMITES[$tipo];
    }
}
```

### 5. Procesamiento Selectivo

```php
// En ConsultaController.php
private function necesitaIA($texto)
{
    // Reglas simples: no usar IA
    $reglasSimples = ['dolor', 'fiebre', 'tos']; // Casos comunes
    
    // Si es simple, usar reglas predefinidas
    foreach ($reglasSimples as $regla) {
        if (stripos($texto, $regla) !== false) {
            return false; // No necesita IA
        }
    }
    
    return true; // Necesita IA
}
```

---

## Riesgos y Consideraciones

### Riesgos
1. **Calidad de modelos locales**: Puede ser inferior a cloud
2. **Latencia**: Procesamiento local puede ser más lento
3. **Mantenimiento**: Requiere actualización manual de modelos
4. **Infraestructura**: Requiere servidor con GPU (costo inicial)

### Mitigaciones
1. **Fallback automático**: Si local falla, usar cloud
2. **Monitoreo de calidad**: Comparar resultados local vs cloud
3. **Actualización automática**: Scripts para actualizar modelos
4. **Escalado gradual**: Empezar con algunos médicos, escalar después

---

## Conclusión

Para lograr **$10 USD/mes por médico**, se requiere:

1. ✅ **Procesamiento local** (Ollama + whisper.cpp) - **CRÍTICO**
2. ✅ **Caché ultra-agresivo** (TTL extendido, similitud 0.85)
3. ✅ **Infraestructura compartida** (10 médicos/servidor)
4. ✅ **Límites de uso** (15 consultas/día, 30 interacciones chatbot)
5. ✅ **Procesamiento selectivo** (solo consultas complejas)
6. ✅ **Tier gratuito de HuggingFace** (30K requests gratis)

**Prioridad de implementación**:
1. **Fase 1** (Quick Wins): 1-2 semanas → $100-110/mes
2. **Fase 2** (Local): 2-4 semanas → $20-30/mes
3. **Fase 3** (Optimización): 1-2 semanas → $10/mes

**Total tiempo estimado**: 4-8 semanas para alcanzar $10/mes

---

## Implementación Rápida: Pasos Inmediatos

### Paso 1: Configurar Caché Ultra-Agresivo (30 minutos)
```php
// frontend/config/params.php
'hf_cache_ttl' => 604800, // 7 días (era 3600)
'embedding_cache_ttl' => 2592000, // 30 días (era 3600)
'correccion_cache_ttl' => 604800, // 7 días (era 43200)
'stt_cache_ttl' => 2592000, // 30 días (era 86400)
'request_similitud_minima' => 0.85, // Más permisivo (era 0.95)
```

**Ahorro inmediato**: ~$18/mes

### Paso 2: Cambiar a Modelos Más Pequeños (1 hora)
```php
// frontend/config/params.php
'hf_stt_model' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish', // Más pequeño
'hf_model_analysis' => 'mistralai/Mistral-7B-Instruct-v0.2', // Más económico
```

**Ahorro inmediato**: ~$20/mes

### Paso 3: Implementar Límites de Uso (2 horas)
Crear componente `UsageLimiter.php` y aplicar en controladores.

**Ahorro inmediato**: ~$42/mes

### Paso 4: Compresión de Audio (3 horas)
Modificar `SpeechToTextManager.php` para comprimir audio antes de enviar.

**Ahorro inmediato**: ~$9/mes

**Total ahorro inmediato (1 día de trabajo)**: ~$89/mes → **Costo: ~$97/mes**

---

## Checklist de Implementación

### Fase 1: Quick Wins (Semana 1-2)
- [ ] Extender TTL de caché (7 días para IA, 30 días para embeddings)
- [ ] Reducir umbral de similitud a 0.85
- [ ] Cambiar a modelos más pequeños (wav2vec2 pequeño, Mistral 7B)
- [ ] Implementar compresión de audio (16kHz, OPUS)
- [ ] Agregar límites de uso (15 consultas/día, 30 interacciones chatbot)
- [ ] Implementar procesamiento selectivo (solo consultas complejas)

**Meta**: Reducir a $100-110/mes

### Fase 2: Procesamiento Local (Semana 3-6)
- [ ] Instalar Ollama en servidor
- [ ] Configurar modelos locales (llama3.1:8b, nomic-embed-text)
- [ ] Modificar `IAManager.php` para usar Ollama como primario
- [ ] Instalar whisper.cpp o configurar Whisper en Ollama
- [ ] Modificar `SpeechToTextManager.php` para usar local
- [ ] Configurar fallback a HuggingFace si local falla
- [ ] Configurar infraestructura compartida (10 médicos/servidor)

**Meta**: Reducir a $20-30/mes

### Fase 3: Optimización Extrema (Semana 7-8)
- [ ] Implementar procesamiento híbrido (90% local, 10% cloud)
- [ ] Configurar tier gratuito de HuggingFace (30K requests gratis)
- [ ] Implementar batch processing agresivo
- [ ] Optimizar prompts (30% más cortos)
- [ ] Crear respuestas pre-definidas para casos comunes
- [ ] Monitoreo y ajuste fino

**Meta**: Reducir a $10/mes

---

## Preguntas Frecuentes

### ¿Es realista $10/mes?
**Sí**, pero requiere:
- Procesamiento local (Ollama + whisper.cpp)
- Infraestructura compartida (10+ médicos)
- Límites de uso razonables
- Caché agresivo

### ¿Afecta la calidad?
**Mínimamente**:
- Modelos locales (llama3.1:8b) son muy buenos
- Fallback automático a cloud si local falla
- Monitoreo de calidad continuo

### ¿Qué pasa si un médico excede los límites?
- **Opción 1**: Bloquear requests adicionales
- **Opción 2**: Cobrar extra por uso excedente
- **Opción 3**: Procesar en modo "básico" (sin IA avanzada)

### ¿Cuánto cuesta la infraestructura inicial?
- **Servidor con GPU** (NVIDIA T4): $50-100/mes
- **Dividido entre 10 médicos**: $5-10/médico/mes
- **ROI**: Se recupera en 1-2 meses vs costos actuales

### ¿Puedo empezar gradualmente?
**Sí**, implementar por fases:
1. Quick Wins (1 semana) → $100/mes
2. Procesamiento Local (2-4 semanas) → $20/mes
3. Optimización (1-2 semanas) → $10/mes

---

## Recursos Adicionales

- [Documentación de Ollama](https://ollama.ai/docs)
- [whisper.cpp GitHub](https://github.com/ggerganov/whisper.cpp)
- [HuggingFace Free Tier](https://huggingface.co/pricing)
- [Modelos Recomendados para Español](https://huggingface.co/models?language=es)
