# Costos – Uso de APIs

Este documento refleja el **costo real** cuando se usan **APIs externas** (IA generativa, STT, Vision, videollamadas), **sin aplicar** estrategias de reducción del producto (p. ej. transcribir solo bajo demanda). Las palancas adicionales (tiers gratuitos, mezcla de proveedores, caché) están en [estrategias-api.md](./estrategias-api.md).

- **IA generativa:** **Google (Vertex AI / Gemini 2.5 Flash Lite)** y **Together AI** (Llama 3.1 8B) como comparativa.
- **STT (transcripción):** **Groq Whisper** (tarifa más baja documentada para API managed).
- **Vision:** **Google Vision API** (tier gratuito cubre el volumen de referencia).

## Supuestos base

- **Consultas por médico**: 20/día = 400/mes (20×20, mismo que en [infra_costos.md](./infra_costos.md)).
- **STT:** 1 min de audio transcrito por consulta ⇒ **400 min/médico/mes** (transcripción de todo el audio del escenario intensivo).
- Todos los valores son **costo de referencia** con el proveedor más barato por categoría, **sin** optimizaciones de producto ni agotamiento de créditos gratuitos (ver estrategias).

---

## Precios de referencia (mayo 2026)

| Servicio | Precio (USD) | Uso en este doc | Fuente |
|----------|--------------|-----------------|--------|
| **Groq** — Whisper large-v3-turbo | **~$0.0007/min** ($0.04/h) | **STT de referencia** | [groq.com/pricing](https://groq.com/pricing) |
| **OpenAI** — gpt-4o-mini-transcribe | $0.003/min | Alternativa batch/streaming | [openai.com/api/pricing](https://openai.com/api/pricing) |
| **Google** — Chirp 3 Dynamic Batch | $0.004/min (async) | Si ya usás GCP y no urge tiempo real | [Speech-to-Text pricing](https://cloud.google.com/speech-to-text/pricing) |
| **Google** — Speech-to-Text V2 estándar | $0.016/min | **No** es referencia (muy caro vs mercado) | Misma fuente |
| **Vision API** (Label, Text, Face, etc.) | 1.000 unidades/mes gratis; luego $1.50/1.000 | Referencia Vision | [cloud.google.com/vision/pricing](https://cloud.google.com/vision/pricing) |
| **Cloud Storage Standard** (almacenamiento) | ~$0.020/GB/mes (us-central1) | [cloud.google.com/storage/pricing](https://cloud.google.com/storage/pricing) |
| **Cloud Storage** (egress a internet) | $0.12/GB (primer tramo, destinos mundial) | Misma fuente |

Para **Vertex AI / Gemini** y **videollamadas** (Twilio, Daily.co) conviene revisar el [Calculador de precios de Google Cloud](https://cloud.google.com/products/calculator) y la [tabla de precios de Gemini en Vertex AI](https://cloud.google.com/vertex-ai/generative-ai/pricing) (revisar cada 6–12 meses).

---

## Gemini Flash: tarifas actuales y context caching

Referencia **mayo 2026** ([Vertex AI – Generative AI pricing](https://cloud.google.com/vertex-ai/generative-ai/pricing), [Context caching overview](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-overview)).

### Tarifas por millón de tokens (texto, ≤200K contexto)

| Modelo | Input | Output | Input en caché (hit) |
|--------|-------|--------|----------------------|
| **Gemini 2.5 Flash Lite** | $0.10 | $0.40 | $0.01 (90 % desc.) |
| **Gemini 2.0 Flash** | $0.15 | $0.60 | ~75 % desc. en 2.0 (implícito) |
| **Gemini 2.5 Flash** | $0.30 | $2.50 | $0.03 (90 % desc.) |

**Nota:** **Gemini 2.5 Flash** (no Lite) sube mucho el coste si la respuesta es larga (output a $2.50/1M). Para chat clínico y asistente con respuestas cortas, priorizar **2.5 Flash Lite** o **2.0 Flash**.

### Coste por llamada (consulta típica)

Supuesto de referencia en este documento: **1.500 tokens totales** por llamada (~**1.000 input + 500 output**), salvo que se indique otro reparto.

| Escenario | Cálculo orientativo | USD/llamada |
|-----------|---------------------|-------------|
| 2.5 Flash Lite, sin caché | 1.000×$0.10/1M + 500×$0.40/1M | **~$0.00030** |
| 2.0 Flash, sin caché | 1.000×$0.15/1M + 500×$0.60/1M | **~$0.00045** |
| 2.0 Flash (doc. anterior) | 750/750 input-output | **~$0.00056** |
| 2.5 Flash Lite + caché parcial | 200 input nuevos + 800 input cacheados + 500 output | **~$0.00023** |

Sí: **~$0.0003 por llamada** encaja con **Gemini 2.5 Flash Lite** (o 2.0 Flash Lite) y reparto input-heavy; es **menor** que la cifra histórica **$0.0005–0.001** del doc anterior cuando se usaba 2.0 Flash con otro reparto o modelos más caros en output.

A **400 consultas/mes** solo IA de consulta: **~$0.12–0.18/médico/mes** (Flash Lite / 2.0 Flash), frente a **~$0.20–0.24** si se mantiene **~$0.0006** como redondeo conservador en tablas siguientes.

### Context caching (Vertex AI)

Google ofrece dos mecanismos ([overview](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-overview)):

| Tipo | Comportamiento | Coste extra |
|------|----------------|-------------|
| **Implícito** | Activado por defecto en el proyecto; descuento si hay *cache hit* en tokens de entrada repetidos | Sin coste de almacenamiento |
| **Explícito** | Declarás en la API el bloque a cachear (system prompt, esquema FHIR, políticas del efector, etc.) | Almacenamiento **$/M tok·hora** (p. ej. 2.5 Flash: **$1/M tok/h**); input cacheado a tarifa reducida |

- En **Gemini 2.5+**, los tokens de entrada que referencian caché explícita suelen facturarse a **10 %** del precio de input estándar (**90 % de descuento**). En **2.0**, el descuento documentado es **75 %**.
- Mínimo de tokens para cachear: **2.048** en modelos 2.0/2.5 (ver tabla en la doc de Google).
- **Cuándo aplica en Bioenlace:** instrucciones del asistente, definiciones `ui_json`, contexto de consulta/efector que se repite en muchas llamadas del mismo turno o sesión. El output **no** se cachea; sigue facturándose íntegro.

**Orden de magnitud con caché:** si ~**70–80 %** del input de cada llamada es contexto repetido (system + reglas), el coste de **input** puede bajar ~**60–75 %** respecto a la misma llamada sin hits; el ahorro total por llamada depende de cuánto pese el output en el mix de tokens.

Esto es **complementario** a la caché de aplicación (respuestas por hash) descrita en [estrategias-api.md](./estrategias-api.md): Vertex reduce el coste del **prompt repetido**; la caché interna evita **llamadas** enteras.

**Cifra de trabajo en tablas siguientes:** se mantiene **~$0.00035/llamada** como estimación central (entre Flash Lite y 2.0 Flash, sin asumir caché máximo), para no subestimar el presupuesto. Con Flash Lite + context caching agresivo, el Apartado 1 puede quedar **~30–50 %** por debajo de esas filas.

---

## Coste de IA vía API – Google y Together AI

### Google (Vertex AI / Gemini)

Ver sección [Gemini Flash: tarifas actuales y context caching](#gemini-flash-tarifas-actuales-y-context-caching). Resumen: **~$0.00025–0.00045/llamada** (1.500 tokens) según modelo; **~$0.0003** es razonable con **2.5 Flash Lite**. A 400 consultas/mes: **~$0.12–0.18/médico/mes** solo por IA de consultas (sin contar pre-turno ni onboarding).

### Together AI (Llama 3.1 8B)

Precio **$0.18 por millón de tokens** (input y output; referencia [Together AI](https://docs.together.ai/docs/serverless-models)). Para consulta de 1.500 tokens: 1.500 × $0.18/1.000.000 ≈ **$0.00027 por consulta**. A 400 consultas/mes: **aprox. $0.11/médico/mes** solo por IA de consultas. Más barato que Gemini Flash por consulta; útil como alternativa para reducir coste de IA generativa.

Para cifras exactas y **cómo bajar aún más** (tiers gratis, Hugging Face, solo bajo demanda), usar [estrategias-api.md](./estrategias-api.md).

---

## STT: proveedor de referencia y alternativas

### Referencia para tablas de este documento

| Concepto | Valor |
|----------|--------|
| Proveedor | **Groq** — Whisper large-v3-turbo |
| Tarifa | **~$0.0007/min** |
| Volumen intensivo | 400 min/médico/mes |
| **Coste STT/médico/mes** | 400 × $0.0007 ≈ **$0.28** |

**Implementación actual en código:** `SpeechToTextManager` usa **Hugging Face** (wav2vec2 español por defecto en `params.php`). El coste HF depende del plan/créditos, no de USD/min fijo; Groq es la referencia **API managed** comparable cuando se externaliza STT.

### Otras APIs (orden de precio aproximado)

| Proveedor | ~USD/min | 400 min/mes | Notas |
|-----------|----------|-------------|--------|
| **Groq Whisper** | 0,0007 | **~0,28** | Batch; muy rápido; límite ~25 MB/archivo |
| Together / Fireworks Whisper | ~0,001 | ~0,40 | Similar a Groq |
| AssemblyAI Slim | ~0,002 | ~0,80 | Solo transcripción |
| OpenAI gpt-4o-mini-transcribe | 0,003 | ~1,20 | Buen equilibrio calidad/precio |
| Google Chirp 3 Dynamic Batch | 0,004 | ~1,60 | Async (~24 h SLA) |
| Deepgram Nova-3 batch | ~0,004 | ~1,60 | Streaming aparte |
| OpenAI Whisper-1 | 0,006 | ~2,40 | Referencia clásica |
| **Google STT V2** | 0,016 | **~6,40** | Evitar como default; ver estrategias si hay créditos GCP |

Cómo **reducir por debajo** de ~$0,28/mes: [estrategias-api.md §5](./estrategias-api.md#5-stt-transcripción-de-audio) (HF gratis, créditos Groq/Deepgram, transcribir solo bajo demanda, Whisper en GPU propia).

---

## Capacidades que consumen API

### 1. Comunicación previa al turno (pre-turno)

El chat/bot guía al paciente **antes** de sacar el turno. Conversación que puede terminar en turno o no.

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Contactos pre-turno | 1.000/médico/mes; 4 mensajes × 40% con IA ⇒ **1.600 llamadas IA** | — |
| **Google (Gemini Flash)** 1.600 × ~$0.00035/llamada | — | **aprox. $0.56/médico/mes** |
| **Together AI (Llama 3.1 8B)** 1.600 × $0.00027/llamada | — | **aprox. $0.43/médico/mes** |

---

### 2. Conversación pre-consulta (chat para despejar dudas)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA | 400 × 5 × 50% = **1.000 llamadas IA** | — |
| **Google (Gemini Flash)** 1.000 × ~$0.00035/llamada | — | **aprox. $0.35/médico/mes** |
| **Together AI (Llama 3.1 8B)** 1.000 × $0.00027/llamada | — | **aprox. $0.27/médico/mes** |

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA/médico/mes | 400 | — |
| **Google (Gemini Flash)** 400 × ~$0.00035/llamada | — | **aprox. $0.14/médico/mes** |
| **Together AI (Llama 3.1 8B)** 400 × $0.00027/llamada | — | **aprox. $0.11/médico/mes** |

---

### 4. Consulta (IA de las 400 consultas/mes)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| **Google (Gemini Flash)** 400 × ~$0.00035/consulta | — | **aprox. $0.14/médico/mes** |
| **Together AI (Llama 3.1 8B)** 400 × $0.00027/consulta | — | **aprox. $0.11/médico/mes** |

---

### 5. Intercambio de audios, fotos y videos (STT + Vision)

**Modelo de uso**: Los medios no se almacenan en cloud; solo hay costo cuando se envía a la nube para analizar (STT, Vision). Ver [producto/flows/capacidades-paciente-medico.md](../../producto/apps-paciente-medico.md).

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Almacenamiento / egress | No se usa cloud storage | **$0** |
| **STT** (transcribir todo el audio) | 400 min; **Groq** ~$0.0007/min | **~$0.28** |
| **Vision** (analizar todas las fotos) | 400 × 2 = 800 imágenes; 1.000 gratis | **$0** |
| **Total medios (STT + Vision)** | — | **~$0.28/médico/mes** |

---

### 6. Videollamadas paciente–médico

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Consultas por videollamada/mes | 30% de 400 = 120 | 120 |
| Duración media | 12 min | 12 min |
| Minutos totales/mes | 120 × 12 = 1.440 min | **1.440 min** |
| **Twilio Video** (2 participantes, $0.004/min c/u) | 1.440 × 2 × $0.004 | **~$11.52/médico/mes** |
| **Plan por asiento** (ej. Daily.co repartido 10 médicos) | — | **~$10/médico/mes** (orden de magnitud) |

---

## Resumen: costo real por API (por médico/mes)

**Apartado 1:** Gemini Flash Lite o Together AI. **Apartado 2:** STT **Groq** + Vision **Google**. Cifras: Gemini **~$0.00035/llamada** ([tarifas Gemini](#gemini-flash-tarifas-actuales-y-context-caching)), STT **~$0.0007/min**.

### Apartado 1 – IA generativa (chat y consulta)

| Concepto | Google (Gemini Flash) | Together AI (Llama 3.1 8B) |
|----------|------------------------|-----------------------------|
| Comunicación previa al turno | aprox. $0.56 | aprox. $0.43 |
| Conversación pre-consulta | aprox. $0.35 | aprox. $0.27 |
| Agente onboarding | aprox. $0.14 | aprox. $0.11 |
| Consulta (400 consultas/mes) | aprox. $0.14 | aprox. $0.11 |
| **Total (Apartado 1)** | **aprox. $1.19** | **aprox. $0.92** |

### Apartado 2 – Medios (transcripción y análisis de imágenes)

| Concepto | Costo real (USD/médico/mes) |
|----------|-----------------------------|
| Transcripción de audios (STT, **Groq Whisper**) | **~$0.28** |
| Análisis de fotos (Vision API, Google) | $0 (800 imágenes; 1.000 gratis/mes) |
| **Total (Apartado 2)** | **~$0.28** |

### Total general (IA + STT + Vision, sin videollamadas)

| Proveedor IA (Apartado 1) | Total Apartado 1 | Total Apartado 2 | **Total general** |
|---------------------------|------------------|------------------|-------------------|
| Google (Gemini Flash Lite) | aprox. $1.19 | aprox. $0.28 | **aprox. $1.47** |
| Together AI (Llama 3.1 8B) | aprox. $0.92 | aprox. $0.28 | **aprox. $1.20** |

**Orden de magnitud uso intensivo:** **~USD 1,5–2/prof/mes** (redondeo conservador sobre ~$1,47). Histórico con Google STT a $0,016/min: **~USD 6,5–8/prof** — ya no aplica como baseline.

**Nota**: Si la IA de pre-turno, pre-consulta, onboarding y consulta corre en **nuestra infra**, esos ítems figuran en [infra/costos.md](../infra/costos.md) y no se duplican aquí. Videollamadas (Twilio, Daily.co, etc.) no están incluidas en este resumen; ver sección correspondiente si aplica.

---

## Referencias

- [impuestos-argentina.md](./impuestos-argentina.md) – IVA, IIBB y ganancias (AR) sobre costo y facturación.
- [estrategias-api.md](./estrategias-api.md) – Cómo reducir coste vía API (STT por proveedor/tier gratis, caché, bajo demanda, context caching Vertex).
- [Groq pricing](https://groq.com/pricing) – STT de referencia en tablas.
- [Vertex AI – Context caching overview](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-overview)
- [infra/costos.md](../infra/costos.md) – Costes cuando la IA corre en nuestra GPU.
- [Together AI – Serverless Models / Pricing](https://docs.together.ai/docs/serverless-models) – Llama 3.1 8B y otros modelos.
- [producto/flows/capacidades-paciente-medico.md](../../producto/apps-paciente-medico.md) – Descripción de las capacidades.
