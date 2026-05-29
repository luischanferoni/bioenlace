# Costos – Uso de APIs

Costos de referencia cuando usamos **APIs externas** (IA, STT, Vision, videollamadas), alineados con **Bioenlace en producción**:

- **IA:** Google **Vertex / Gemini** con modelo **`gemini-2.5-flash-lite`** (`vertex_ai_model` en `params.php`).
- **Columnas Google:** **sin context caching** vs **con context caching** (tokens de entrada repetidos a tarifa reducida de Vertex; ver abajo). No incluyen caché de aplicación ni otras tácticas de producto.
- **Escenario intensivo:** sin optimizaciones de producto (p. ej. transcribir todo el audio automáticamente).

Otras reducciones (caché Yii, STT bajo demanda, etc.) están en [estrategias-reduccion/](./estrategias-reduccion/README.md) y **no** se suman a las tablas de [impuestos-argentina.md](./impuestos-argentina.md) hasta validarlas.

- **IA generativa:** **Google (Gemini 2.5 Flash Lite)** y **Together AI** (Llama 3.1 8B) solo como comparativa de precio.
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
| **Vision API** (Label, Text, Face, etc.) | 1.000 unidades/mes gratis; luego $1.50/1.000 | Referencia Vision | [cloud.google.com/vision/pricing](https://cloud.google.com/vision/pricing) |

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

### Context caching (Vertex AI) — base de las columnas «con caché»

Bioenlace usa **`gemini-2.5-flash-lite`**. Las tablas comparan el mismo volumen de llamadas con tarifa **estándar de input** vs **input con tokens cacheados** según [pricing de Vertex](https://cloud.google.com/vertex-ai/generative-ai/pricing).

| Tipo | En producción hoy | En las tablas de este doc |
|------|-------------------|---------------------------|
| **Implícito** | Sí (Google aplica hits si el prefijo del prompt se repite; medible con `usageMetadata.cachedContentTokenCount`) | Columna **«con caché»** |
| **Explícito** (`cachedContents` en API) | **No implementado** | Mismo supuesto aritmético hasta integrarlo; ver [estrategias-reduccion/context-caching-explicita.md](./estrategias-reduccion/context-caching-explicita.md) |

Detalle de cada tipo: [implícita](./estrategias-reduccion/context-caching-implicita.md) · [explícita](./estrategias-reduccion/context-caching-explicita.md).

- Tarifa input cacheado 2.5 Flash Lite: **$0.01/M** vs **$0.10/M** estándar (**90 %** desc. en esa porción).
- Supuesto de presupuesto: **~80 %** del input de cada llamada entra como cacheado (200 tokens nuevos + 800 cacheados + 500 output) → fila «2.5 Flash Lite + caché parcial».
- Objetivo de diseño: instrucciones del asistente, reglas y contexto de efector **al inicio** del prompt, estable entre llamadas de la misma sesión. Calibrar con `ia_usage_tracking_habilitado` y `AICostTracker` ([monitoreo](./estrategias-reduccion/monitoreo.md)).
- El **output** no se cachea.

**Cifras en tablas siguientes (solo Google / Gemini):**

| Columna | USD/llamada (1.500 tok.) | Notas |
|---------|--------------------------|--------|
| **Sin context caching** | **~$0.00035** | 1.000 input + 500 output a tarifa estándar |
| **Con context caching** | **~$0.00023** | Supuesto 80 % input cacheado; ver fila arriba |

**Together AI** no usa context caching de Vertex → columna «con caché» = **—**.

---

## Coste de IA vía API – Google y Together AI

### Google (Vertex AI / Gemini)

Ver sección [Gemini Flash: tarifas actuales y context caching](#gemini-flash-tarifas-actuales-y-context-caching). Resumen: **~$0.00025–0.00045/llamada** (1.500 tokens) según modelo; **~$0.0003** es razonable con **2.5 Flash Lite**. A 400 consultas/mes: **~$0.12–0.18/médico/mes** solo por IA de consultas (sin contar pre-turno ni onboarding).

### Together AI (Llama 3.1 8B)

Precio **$0.18 por millón de tokens** (input y output; referencia [Together AI](https://docs.together.ai/docs/serverless-models)). Para consulta de 1.500 tokens: 1.500 × $0.18/1.000.000 ≈ **$0.00027 por consulta**. A 400 consultas/mes: **aprox. $0.11/médico/mes** solo por IA de consultas. Más barato que Gemini Flash por consulta; útil como alternativa para reducir coste de IA generativa.

Para bajar el costo con otras palancas (STT bajo demanda, caché de aplicación, etc.), ver [estrategias-reduccion/](./estrategias-reduccion/README.md).

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

### Otras APIs STT (sin Google / OpenAI)

Detalle STT: [estrategias-reduccion/stt.md](./estrategias-reduccion/stt.md).

| Proveedor | ~USD/min | 400 min/mes | Notas |
|-----------|----------|-------------|--------|
| **Groq Whisper** | 0,0007 | **~0,28** | Referencia de este doc; batch; ~25 MB/archivo |
| Together / Fireworks Whisper | ~0,001 | ~0,40 | Similar a Groq |
| AssemblyAI Slim | ~0,002 | ~0,80 | Solo transcripción |
| Deepgram Nova-3 batch | ~0,004 | ~1,60 | Streaming aparte; créditos de prueba |

---

## Capacidades que consumen API

### 1. Motivos de consulta (app paciente, antes de la atención)

El paciente carga texto, audio e imágenes en el chat de **motivos** hasta **1 minuto antes del turno** (sin IA en cada mensaje). Al cerrar la ventana, **una sola llamada IA** resume todo el hilo en `encounter.reason_text` para el médico. Implementación: `AppointmentReasonBatchService`, cron `MOTIVOS_IA_BATCH` vía `turno-notificacion/run`.

**Cuenta:** **400 lotes IA/mes** (1 por consulta). STT solo en el caso con audio (Groq, misma tarifa que §STT).

#### Caso A — solo texto (audio deshabilitado en motivos)

Prompt más chico (~**1.000 input + 400 output** por lote; sin transcripciones).

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| Llamadas IA | 400 | — | — | — |
| IA (resumen lote) | 400 × ~$0.00024 / ~$0.00019 | **~$0.10** | **~$0.08** | **~$0.09** |
| STT | — | — | — | — |
| **Total caso A** | | **~$0.10** | **~$0.08** | **~$0.09** |

#### Caso B — siempre audio (1 min de audio por consulta en el hilo)

Mismo volumen de tokens IA que el resto del doc (**~1.500/llamada**) + STT del audio antes del lote.

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| Llamadas IA | 400 | — | — | — |
| IA (resumen lote) | 400 × ~$0.00035 / ~$0.00023 | **~$0.14** | **~$0.09** | **~$0.11** |
| STT (Groq) | 400 min × $0.0007/min | **~$0.28** | **~$0.28** | **~$0.28** |
| **Total caso B** | | **~$0.42** | **~$0.37** | **~$0.39** |

---

### 2. Conversación pre-consulta (chat para despejar dudas)

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| Llamadas IA | 400 × 5 × 50% = **1.000** | — | — | — |
| IA generativa | 1.000 × ~$0.00035 / ~$0.00023 | **~$0.35** | **~$0.23** | **~$0.27** |

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| Llamadas IA/médico/mes | 400 | — | — | — |
| IA generativa | 400 × ~$0.00035 / ~$0.00023 | **~$0.14** | **~$0.09** | **~$0.11** |

---

### 4. Consulta (IA de las 400 consultas/mes)

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| IA generativa | 400 × ~$0.00035 / ~$0.00023 | **~$0.14** | **~$0.09** | **~$0.11** |

---

### 5. Intercambio de audios, fotos y videos (STT + Vision)

**Modelo de uso**: Los medios no se almacenan en cloud; solo hay costo cuando se envía a la nube para analizar (STT, Vision). Ver [producto/flows/capacidades-paciente-medico.md](../../producto/apps-paciente-medico.md).

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
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

**Apartado 1:** IA generativa (Gemini / Together). **Apartado 2:** STT **Groq** + Vision **Google**. **Apartado 3:** videollamadas (§6). Cifras IA: **~$0.00035/llamada** sin caché ([tarifas Gemini](#gemini-flash-tarifas-actuales-y-context-caching)); STT **~$0.0007/min**.

### Apartado 1 – IA generativa (chat y consulta)

| Concepto | Google sin context caching | Google con context caching | Together AI |
|----------|------------------|------------------|-------------|
| Motivos — solo texto (§1 caso A) | ~$0.10 | ~$0.08 | ~$0.09 |
| Motivos — siempre audio (§1 caso B, IA+STT) | ~$0.42 | ~$0.37 | ~$0.39 |
| Conversación pre-consulta | ~$0.35 | ~$0.23 | ~$0.27 |
| Agente onboarding | ~$0.14 | ~$0.09 | ~$0.11 |
| Consulta (400/mes) | ~$0.14 | ~$0.09 | ~$0.11 |
| **Total Apartado 1 — motivos solo texto** | **~$0.73** | **~$0.49** | **~$0.58** |
| **Total Apartado 1 — motivos con audio** | **~$1.05** | **~$0.78** | **~$0.88** |

### Apartado 2 – Medios (transcripción y análisis de imágenes)

| Concepto | Costo real (USD/médico/mes) |
|----------|-----------------------------|
| Transcripción de audios (STT, **Groq Whisper**) | **~$0.28** |
| Análisis de fotos (Vision API, Google) | $0 (800 imágenes; 1.000 gratis/mes) |
| **Total (Apartado 2)** | **~$0.28** |

### Apartado 3 – Videollamadas (§6)

Supuesto: **30 %** de las 400 consultas/mes por video, **12 min** promedio, **2** participantes facturados (Twilio).

| Concepto | Costo real (USD/médico/mes) |
|----------|-----------------------------|
| **Twilio Video** ($0.004/min por participante) | **~$11.52** |
| **Plan por asiento** (ej. Daily.co / 10 médicos) | **~$10** (orden de magnitud) |

### Total general (Apartados 1 + 2 + 3)

**400 min STT/mes** es cupo **global** del escenario intensivo (§5): no sumar el STT del caso B de motivos **y** los 400 min completos del apartado 2.

| Escenario | Google sin context caching | Google con context caching | Together AI |
|-----------|------------------|------------------|-------------|
| Apartados 1 + 2 (motivos **solo texto**) | **~$1.01** | **~$0.77** | **~$0.86** |
| Apartados 1 + 2 (motivos **con audio**) | **~$1.05** | **~$0.78** | **~$0.88** |
| + Apartado 3 (**Twilio Video**) | **+$11.52** | **+$11.52** | **+$11.52** |
| **Total con videollamada (Twilio)** — motivos texto | **~$12.53** | **~$12.29** | **~$12.38** |
| **Total con videollamada (Twilio)** — motivos audio | **~$12.57** | **~$12.30** | **~$12.40** |
| **Total con videollamada (Daily ~$10)** — motivos audio | **~$11.05** | **~$10.78** | **~$10.88** |

**Orden de magnitud uso intensivo (todo incluido, Twilio):** **~USD 12–13/prof/mes**. Solo IA + STT + Vision (sin §6): **~USD 1,0–1,1/prof/mes**.

**Nota:** Si la IA corre en **nuestra infra**, los ítems del apartado 1 figuran en [infra-costos.md](./infra-costos.md) y no se duplican aquí. El apartado 3 sigue siendo coste de proveedor de video salvo stack propio.

---

## Referencias

- [impuestos-argentina.md](./impuestos-argentina.md) – IVA, IIBB y ganancias (AR) sobre costo y facturación.
- [estrategias-reduccion/](./estrategias-reduccion/README.md) – Palancas adicionales (no incluidas en COGS base).
- [Groq pricing](https://groq.com/pricing) – STT de referencia en tablas.
- [Vertex AI – Context caching overview](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-overview)
- [infra/costos.md](../infra/costos.md) – Costes cuando la IA corre en nuestra GPU.
- [Together AI – Serverless Models / Pricing](https://docs.together.ai/docs/serverless-models) – Llama 3.1 8B y otros modelos.
- [producto/flows/capacidades-paciente-medico.md](../../producto/apps-paciente-medico.md) – Descripción de las capacidades.
