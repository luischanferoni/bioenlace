# Estrategias de reducción de costos – API

Este documento detalla cómo **reducir el costo real** cuando se usan **APIs** (IA generativa, STT, Vision, videollamadas). El costo de referencia (proveedor más barato por categoría) está en [costos-api.md](./costos-api.md). Muchas tácticas permiten quedar **por debajo** de esas cifras.

Estas mismas tácticas, al reducir **llamadas a IA**, también reducen la carga cuando la IA corre en nuestra infra; ver [infra/estrategias.md](../infra/estrategias.md).

---

## Resumen por área

| Área | Costo ref. ([costos-api.md](./costos-api.md)) | Reducción estimada | Palancas principales |
|------|---------------------------------------------|--------------------|-----------------------|
| IA / modelos (chat, corrección, análisis) | ~$1,2/médico/mes (Gemini Flash Lite) | **40–70%** | Caché app, context caching Vertex, uso condicional, Together AI |
| Motivos de consulta (lote) | ~$0,14/médico/mes | **Variable** | Ya es 1 IA/consulta; bajar con menos consultas con motivos o STT local |
| Pre-consulta | ~$0,35/médico/mes | **30–50%** | Idem |
| Onboarding | ~$0,14/médico/mes | **Hasta 60%** | Flujos guiados, FAQ, caché |
| **STT** | **~$0,28/médico/mes** (Groq) | **50–100%** | Tiers gratis, HF, bajo demanda, menos minutos facturables |
| Vision | $0 (800 img; tier gratis) | **50–100%** | Analizar solo cuando aporte |
| Videollamadas | $10–12/médico/mes | **20–50%** | Plan por asiento, límite de duración, proveedor |
| **Total IA+STT intensivo** | **~$1,5/prof/mes** | **Variable** | Combinar filas anteriores |

---

## 1. IA y modelos (chat, corrección, análisis)

### 1.1 Caché de respuestas y resultados (aplicación)

- **Qué hace**: Evitar llamadas repetidas al modelo para consultas o correcciones muy similares.
- **Dónde aplica**: Chat (pre-turno, pre-consulta, onboarding), corrección de texto, análisis, embeddings, transcripciones (STT).
- **Cómo**: TTL largos (`ia_cache_ttl`, `correccion_cache_ttl`, `embedding_cache_ttl`, `stt_cache_ttl` en `params.php`). Clave de caché por hash del input (y opcionalmente usuario/contexto).
- **Reducción estimada**: **40–60%** del costo de IA (ref. [costos-api.md](./costos-api.md)).

### 1.1b Context caching de Vertex AI (Gemini)

- **Qué hace**: Factura los **tokens de entrada repetidos** (system prompt, reglas del asistente, contexto de efector/consulta) a tarifa reducida cuando hay *cache hit*, sin evitar la llamada al modelo.
- **Tipos**: **Implícito** (activo por defecto; sin coste de almacenamiento) y **Explícito** (declarás el bloque a cachear; coste de almacenamiento por hora + input cacheado ~**90 %** más barato en 2.5+).
- **Dónde aplica**: Flujos con mucho contexto fijo por sesión (asistente, captura clínica, pre-consulta con historial acotado). Ver detalle y tarifas en [costos-api.md – Gemini Flash y context caching](./costos-api.md#gemini-flash-tarifas-actuales-y-context-caching).
- **Cómo**: Usar modelos con soporte (p. ej. **Gemini 2.5 Flash Lite**); agrupar instrucciones y contexto estable al inicio del prompt; evaluar caché explícita si el bloque supera el mínimo (~2.048 tokens en 2.0/2.5).
- **Reducción estimada**: **20–50 %** adicional sobre el coste de **input** de cada llamada (depende del % de tokens cacheados); combinable con §1.1.
- **Referencias**: [Context caching overview](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-overview), [pricing](https://cloud.google.com/vertex-ai/generative-ai/pricing).

### 1.2 Uso condicional de IA (no llamar siempre)

- **Qué hace**: Usar reglas, diccionarios o flujos predefinidos primero; llamar al modelo solo cuando sea necesario.
- **Dónde aplica**: Corrección de texto (SymSpell + diccionario + IA condicional), clasificación de intents (reglas/keywords primero, IA como fallback), FAQ.
- **Cómo**: Mantener flujo híbrido; extender a más intents con reglas claras antes de invocar IA.
- **Reducción estimada**: **30–50%** del costo de IA (ref. [costos-api.md](./costos-api.md)).

### 1.3 Elección de proveedor y modelo

- **Qué hace**: Usar el proveedor y modelo más baratos que cumplan calidad mínima.
- **Opciones**: `ia_proveedor` en params: `google` (Vertex/Gemini), `huggingface`, `groq`, `openai`, `ollama`. Gemini Flash más barato que Pro; HuggingFace tier gratuito (30K requests/mes).
- **Reducción estimada**: **20–50%** del costo de IA según cambio de modelo/proveedor (Pro → Flash, tier gratis).

### 1.4 Limitar tokens y complejidad

- **Qué hace**: Reducir `max_tokens` / `maxOutputTokens` y longitudes de prompt.
- **Dónde**: `vertex_ai_max_tokens`, `google_max_output_tokens`, `hf_max_length` en params; prompts acotados en código.
- **Reducción estimada**: **10–30%** del costo de IA por llamada.

### 1.5 Comprimir datos en tránsito

- **Qué hace**: Enviar menos bytes al proveedor (gzip) donde el API lo acepte.
- **Impacto**: Variable; no suele ser el mayor % del ahorro.

---

## 2. Motivos de consulta (app paciente, lote pre-atención)

Una **sola IA por consulta** al cerrar la ventana (~1 min antes del turno). Coste de referencia en [costos-api.md](./costos-api.md).

### 2.1 No reprocesar

- Idempotencia con `encounter.motivos_ia_processed_at`; no volver a llamar IA si el resumen ya existe (salvo `--force` en consola).

### 2.2 STT / visión solo en el lote

- Audios: `SpeechToTextManager` (HF/Groq) **solo** en `AppointmentReasonBatchService`, no en cada mensaje.
- Imágenes: por ahora referencia en el prompt; ampliar a visión multimodal solo si aporta calidad.

### 2.3 Menos consultas con motivos cargados

- Si el paciente no usa el chat, **0** llamadas IA de este ítem.

---

## 3. Conversación pre-consulta

- **Reducir % que llama a IA**: Respuestas predefinidas y flujos guiados (preparación, documentación, horarios, estado del turno); IA solo para preguntas no catalogadas. **30–40%** del costo real de pre-consulta.
- **Caché por pregunta/intención**: **20–40%** adicional.
- **Límite de mensajes o ventana** (ej. solo 48 h antes del turno): **Variable**.

---

## 4. Agente de onboarding y día a día

- **Flujos guiados y FAQ primero**: Resolver con árboles de decisión, botones y respuestas fijas; IA solo cuando el usuario hace pregunta libre no cubierta. **Hasta 60%** del costo real del agente.
- **Caché por tipo de consulta**: **20–40%**.
- **Reducir interacciones por usuario** (mejor UX, menos pasos): **Variable**.

---

## 5. STT (transcripción de audio)

Baseline en [costos-api.md](./costos-api.md): **Groq Whisper** ~**$0,0007/min** ⇒ **~$0,28/médico/mes** (400 min intensivas). El código actual usa **Hugging Face** (`SpeechToTextManager`, `hf_stt_model` en `params.php`); las estrategias siguientes aplican a **cualquier** backend.

### 5.1 Escalera de proveedores (usar lo que corresponda en cada caso)

Priorizar **coste cero o créditos** hasta agotarlos; luego el más barato que cumpla latencia y calidad en español (idealmente español médico en pruebas reales).

| Orden | Proveedor / modo | Cuándo usarlo | Coste orientativo | Tier gratis / créditos |
|-------|------------------|---------------|-------------------|-------------------------|
| 1 | **Hugging Face** (wav2vec2 / Whisper en router HF) | Ya integrado; español; volumen bajo–medio | Plan/créditos HF; a menudo **muy bajo** | Límites del plan; modelo `economico` en código |
| 2 | **Groq Whisper** | Batch, respuesta rápida, archivos &lt; 25 MB | **~$0,0007/min** | Créditos iniciales de cuenta |
| 3 | **Together / Fireworks** Whisper | Similar a Groq si Groq limita tasa | **~$0,001/min** | Créditos de prueba |
| 4 | **Deepgram** | Streaming en consulta; Nova-3 Medical (validar ES) | Nova-3 batch ~$0,004/min; streaming más caro | **~$200** crédito nuevo |
| 5 | **AssemblyAI** | Streaming o extras (diarización, PII) | Slim ~$0,002/min; Universal-Streaming ~$0,0025/min | **~$50** créditos |
| 6 | **Whisper en GPU propia** | Alto volumen estable | Coste fijo [infra-costos.md](./infra-costos.md) | N/A |

**Regla operativa:** **router STT** en `params.php`: HF → Groq → Together/Fireworks → Deepgram/AssemblyAI según cuota, error o necesidad de streaming.

### 5.2 Tiers gratuitos y créditos (hasta que se acaben)

- **Groq / Deepgram / AssemblyAI / Together:** consumir **créditos de alta** en entornos de prueba y primeros meses de producción.
- **Hugging Face:** mantener modelo **económico** por defecto; reservar Whisper `premium` en `SpeechToTextManager` solo si falla calidad.
- **Vision (relacionado):** 1.000 análisis/mes gratis — no mezclar con STT pero misma lógica de “gratis primero”.

Documentar en operaciones **cuántos minutos/mes** van por proveedor para no sorprenderse cuando expire un crédito.

### 5.3 Reducir minutos facturables (producto + FFmpeg)

Ya en código: eliminar silencios, comprimir audio, chunking con voz (`SpeechToTextManager`). Adicional:

- **Transcribir solo bajo demanda** (botón “Transcribir” / al guardar nota clínica): **50–100%** del coste STT del escenario “400 min automáticos”.
- **No transcribir** audios &lt; 1 s o sin voz detectada (ya parcialmente implementado).
- **Caché 30 días** por hash de audio (`CACHE_TTL` en manager): misma grabación no vuelve a API.
- **Batch / async** cuando el usuario no espera el texto en vivo (cola nocturna con Groq o GPU propia).

### 5.4 Calidad vs coste (español clínico)

- Probar con audios reales del efector antes de fijar proveedor único.
- Si wav2vec2 falla en jerga o ruido: subir a **Groq Whisper** (u otro Whisper hosted) solo en ese flujo (captura clínica), mantener HF en notas cortas.
- **Streaming en vivo** (dictado en consulta): Deepgram o AssemblyAI Universal-Streaming; suele costar más que Groq batch.

### 5.5 Reducción estimada (sobre baseline Groq ~$0,28/mes)

| Táctica | Reducción orientativa |
|---------|------------------------|
| Solo bajo demanda (50% de consultas con audio) | **~50%** |
| HF dentro de tier / créditos | **hasta 100%** del tramo cubierto |
| Caché + menos silencio (menos minutos) | **20–40%** |
| Mezcla HF gratis + créditos Groq/Deepgram | **Variable** por escala |

---

## 6. Vision (análisis de imágenes)

- **No almacenar en cloud** (modelo actual): costo = solo Vision al invocar.
- **Tier gratis 1.000 unidades/mes** cubre 800 imágenes/médico del escenario intensivo → **$0** en referencia.
- **Analizar solo cuando aporte** (botón “Analizar imagen”): **50–100%** si hoy se analizara todo automáticamente.
- Una feature por imagen cuando baste (no Label + Text + Face si solo hace falta OCR).

---

## 7. Videollamadas

- **Proveedor y tipo de plan**: Plan por asiento o por institución (Daily.co, etc.) frente a pago por minuto. **20–50%** del costo real.
- **Límites razonables de duración**: Aviso o corte suave a los 15–20 min. **10–25%** si baja la duración media.
- **Calidad adaptable / solo audio como fallback**: **Variable** según proveedor.

---

## 8. Monitoreo y gobernanza

- **Métricas de uso y costo**: Llamadas a IA, minutos STT, imágenes Vision, minutos de video, por médico o institución; alertas si se superan umbrales.
- **Cuotas y límites** por usuario o institución: Evitar uso desproporcionado; presupuesto predecible.
- **Revisión periódica de precios** de proveedores (cada 6–12 meses).

---

## Referencias

- [costos-api.md](./costos-api.md) – Costos de referencia por capacidad (proveedor más barato).
- [Groq pricing](https://groq.com/pricing) · [Deepgram pricing](https://deepgram.com/pricing) · [AssemblyAI pricing](https://www.assemblyai.com/pricing)
- [infra/estrategias.md](../infra/estrategias.md) – Cómo reducir coste de infra (las mismas tácticas de “menos IA” también bajan carga en nuestra GPU).
- [captura-clinica/flows/correccion-texto-medico.md](../../producto/captura-clinica.md) – Ejemplo de uso condicional de IA.
- Parámetros: `web/frontend/config/params.php` (cachés, proveedor, modelos).
