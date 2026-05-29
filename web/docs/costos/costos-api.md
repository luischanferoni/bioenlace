# Costos – Uso de APIs

Costos de referencia cuando usamos **APIs externas** (IA, STT, Vision, videollamadas), alineados con **Bioenlace en producción**:

- **IA:** Google **Vertex / Gemini** con modelo **`gemini-2.5-flash-lite`** (`vertex_ai_model` en `params.php`).
- **Columnas Google:** **sin context caching** = **COGS base seguro**; **con context caching** = **escenario favorable**, no costo esperado (tokens repetidos a tarifa reducida de Vertex; ver abajo). No incluyen caché de aplicación ni otras tácticas de producto.
- **Escenario intensivo:** sin optimizaciones de producto (p. ej. transcribir todo el audio automáticamente).

Otras reducciones (caché Yii, STT bajo demanda, etc.) están en [estrategias-reduccion/](./estrategias-reduccion/README.md) y **no** se suman a las tablas de [impuestos-argentina.md](./impuestos-argentina.md) hasta validarlas. Precios unitarios de proveedores: [Precios de referencia](#precios-de-referencia-mayo-2026).

## Supuestos base

Por médico por mes, en orden del recorrido del paciente (detalle y costes en §1–6). Escala común **400 encounters por mes** (20 por día x 20 días) donde aplica §1, §2, §4, §5 y §6 — [infra-costos.md](./infra-costos.md).

- **§1 Conversación con el paciente:** 5 mensajes por encounter (~2.660 llamadas Vertex por mes)
- **§2 Motivos de consulta:** 1 llamada a la IA por encounter; con audio: 1 transcripción por encounter + 1 llamada a la IA por encounter
- **§3 Onboarding y día a día:** 400 llamadas a la IA por mes
- **§4 Captura clínica (encounter):** **siempre** audio dictado por consulta — 400 min STT (1 min por encounter) + 400 llamadas a la IA (transcripción → análisis). Sin variante solo texto en el modelo de costos.
- **§5 Medios (fotos, etc.):** 2 fotos por encounter (Vision)
- **§6 Videollamada:** 30 % de encounters; 12 min; 2 participantes

---

## Precios de referencia (mayo 2026)

| Servicio | Precio (USD) | Uso en este doc | Fuente |
|----------|--------------|-----------------|--------|
| **Groq** — Whisper large-v3-turbo | **~$0.0007 por min** ($0.04 por h) | STT (§2 caso B, §4) | [groq.com/pricing](https://groq.com/pricing) |
| **Vision API** (Label, Text, Face, etc.) | 1.000 unidades por mes gratis; luego $1.50 por 1.000 | Fotos §5 | [cloud.google.com/vision/pricing](https://cloud.google.com/vision/pricing) |
| **Together AI** — Llama 3.1 8B | **$0.18 por 1M tokens** (input y output) | Comparativa IA | [Together AI](https://docs.together.ai/docs/serverless-models) |

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

**Nota:** **Gemini 2.5 Flash** (no Lite) sube mucho el coste si la respuesta es larga (output a $2.50 por 1M). Para chat clínico y asistente con respuestas cortas, priorizar **2.5 Flash Lite** o **2.0 Flash**.

### Coste por llamada (referencia)

Supuesto de referencia en este documento: **1.500 tokens totales** por llamada (~**1.000 input + 500 output**), salvo que se indique otro reparto.

| Escenario | Cálculo orientativo | USD por llamada |
|-----------|---------------------|-----------------|
| 2.5 Flash Lite, sin caché | 1.000 x $0.10 por 1M + 500 x $0.40 por 1M | **~$0.00030** |
| 2.0 Flash, sin caché | 1.000 x $0.15 por 1M + 500 x $0.60 por 1M | **~$0.00045** |
| 2.0 Flash, reparto 50 y 50 input-output | 750 input y 750 output | **~$0.00056** |
| 2.5 Flash Lite + caché parcial | 750 input nuevos + 250 input cacheados + 500 output | **~$0.00028** |

**Cifras usadas en tablas §2–4** (1.500 tok., una invocación; redondeo conservador):

| Columna | USD por llamada | Uso en pricing |
|---------|-----------------|----------------|
| **Sin context caching** | **~$0.00035** | **COGS base** |
| **Con context caching** | **~$0.00031** | Escenario favorable (~**25 %** input cacheado) |

En tablas §2–4, **tarifa IA** remite a esta tabla; **tarifa STT** a [Groq](#precios-de-referencia-mayo-2026).

### Context caching (Vertex AI) — base de las columnas «con caché»

Bioenlace usa **`gemini-2.5-flash-lite`**. Las tablas comparan el mismo volumen de llamadas con tarifa **estándar de input** vs **input con tokens cacheados** según [pricing de Vertex](https://cloud.google.com/vertex-ai/generative-ai/pricing).

| Tipo | En producción hoy | En las tablas de este doc |
|------|-------------------|---------------------------|
| **Implícito** | Sí (Google aplica hits si el prefijo del prompt se repite; medible con `usageMetadata.cachedContentTokenCount`) | Columna **«con caché»** |
| **Explícito** (`cachedContents` en API) | **Simulado en local** (`vertex_context_cache_simulado`) | Mismo supuesto aritmético favorable hasta integrar API real; ver [estrategias-reduccion/context-caching-explicita.md](./estrategias-reduccion/context-caching-explicita.md) |

Detalle de cada tipo: [implícita](./estrategias-reduccion/context-caching-implicita.md) · [explícita](./estrategias-reduccion/context-caching-explicita.md).

- Tarifa input cacheado 2.5 Flash Lite: **$0.01 por M** vs **$0.10 por M** estándar (**90 %** desc. en esa porción).
- Supuesto **conservador** en tablas §2–4: **~25 %** del input cacheado (no usar ratios altos sin telemetría).
- **Conversación con el paciente** (§1): preprocess ~**40 %** cacheado; conversacional ~**50 %**; operativo **sin 2.ª IA** — ver [§1](#1-conversación-con-el-paciente).
- Calibrar con `ia_usage_tracking_habilitado`, `vertex_context_cache_simulado` y `AICostTracker` por `contexto` ([monitoreo](./estrategias-reduccion/monitoreo.md)).
- El **output** no se cachea.

**Together AI** no usa context caching de Vertex → columna «con caché» = **—**. Comparativa: [Precios de referencia](#precios-de-referencia-mayo-2026) (~**$0.00027** por llamada de 1.500 tokens).

Para bajar el costo con otras palancas (STT bajo demanda, caché de aplicación, etc.), ver [estrategias-reduccion/](./estrategias-reduccion/README.md).

---

## STT

Tarifa unitaria: [Precios de referencia](#precios-de-referencia-mayo-2026) (Groq). **Implementación en código:** `SpeechToTextManager` usa **Hugging Face** (wav2vec2 español por defecto en `params.php`); el coste HF depende del plan o créditos. Groq aplica cuando se externaliza STT.

Detalle y alternativas: [estrategias-reduccion/stt.md](./estrategias-reduccion/stt.md).

| Proveedor | ~USD por min | 400 min por mes | Notas |
|-----------|----------|-------------|--------|
| Together · Fireworks Whisper | ~0,001 | ~0,40 | Similar a Groq |
| AssemblyAI Slim | ~0,002 | ~0,80 | Solo transcripción |
| Deepgram Nova-3 batch | ~0,004 | ~1,60 | Streaming aparte; créditos de prueba |

---

## Capacidades que consumen API

### 1. Conversación con el paciente (chat asistente)

Un solo chat para el paciente: turnos, síntomas, menú de ayuda o mensajes poco claros.

**Paso 1 (todos los mensajes):** 1 llamada a la IA de **preprocess** — clasifica `user_goal`, devuelve `normalized_text` (ortografía corregida, abreviaturas abiertas).

**Paso 2 (según intención):**

| Intención | Qué hace el producto | ¿2.ª llamada IA? |
|-----------|----------------------|------------------|
| **Operacional** | Match con reglas sobre `normalized_text` → **listado**, **formulario** o **flujo guiado** (pasos en chat vía SubIntentEngine; sin IA en cada paso del flujo) | **No** |
| **Conversacional** | Charla clínica / empatía — **cada mensaje** del paciente | **Sí** (1 por mensaje conversacional) |
| **Informativo** | Menú de acciones; si no es pregunta de menú → trata como conversacional | **Casi no** / **sí** si deriva |
| **Ambiguo** | Mensaje guía fijo | **No** |

Los **pasos de un flujo operativo** (elegir profesional, completar formulario, etc.) continúan en el chat con `intent_id`; no consumen una 2.ª IA de clasificación. Si el paciente escribe texto en un paso, puede volver a correr preprocess.

Supuesto de actividad: **2.000 mensajes por mes** (5 por encounter x 400 encounters). Varios ida y vuelta conversacionales cuentan como **varios mensajes** dentro de esos 5.

#### Tras el preprocess: reparto por intención (escenario central — ajustar con telemetría)

La 1.ª IA ya corrió; la tabla indica **si hace falta una 2.ª** y cuántas llamadas IA suma ese mensaje en total. Los % suman 100 % de los 2.000 mensajes.

| Intención (`user_goal`) | Qué escribe el paciente (ejemplos) | Mensajes por mes | ¿2.ª IA? | Total llamadas IA por mensaje |
|-------------------------|-----------------------------------|------------------|----------|-------------------------------|
| `conversational` | Síntomas, malestar, charla clínica | **600** (30 %) | **Sí**, siempre | **2** |
| `operational` | «Quiero un turno», «cancelar mi cita» | **900** (45 %) | **No** (match PHP con `normalized_text`) | **1** |
| `informational` | «¿Qué puedo hacer acá?» | **300** (15 %) | **Sí** en ~60 (~20 % de estos, deriva conversacional); **no** en ~240 (menú) | **1** o **2** |
| `unclear` | Mensaje ambiguo o muy corto | **200** (10 %) | **No** (solo pide aclaración) | **1** |

**Cómo se traduce en volumen mensual:**

| Tipo de llamada | Cálculo (sobre 2.000 mensajes) | Llamadas por mes |
|-----------------|--------------------------------|------------------|
| 1.ª IA — preprocess (todos) | 2.000 | **2.000** |
| 2.ª IA — conversacional | 600 + 60 (deriva desde informational) | **660** |
| **Total Vertex** | 2.000 + 660 | **~2.660** |

#### Tokens y context caching por tipo de llamada

| Tipo de llamada | Input y output (ref.) | % input cacheado (implícito, conservador) |
|-----------------|----------------------|-------------------------------------------|
| Preprocess | ~700 y ~250 | **~40 %** |
| 2.ª conversacional | ~450 y ~180 | **~50 %** |

Tarifas Gemini: ver [§ Gemini Flash](#gemini-flash-tarifas-actuales-y-context-caching).

#### Coste mensual — conversación con el paciente (Google, escenario central)

| Concepto | Sin context caching (COGS) | Con context caching (favorable) |
|----------|----------------------------|----------------------------------|
| 2.000 x preprocess | ~$0,34 | ~$0,29 |
| 660 x conversacional | ~$0,08 | ~$0,06 |
| **Total §1** | **~$0,42** | **~$0,35** |
| Together AI (orden de magnitud) | **~$0,37** | **~$0,31** |

**Escenario alternativo (más conversacional, 45 % `conversational`):** sube a **~$0,46** sin caché · **~$0,38** con caché (~3.000 llamadas Vertex). **Escenario operativo fuerte (55 % operational):** baja a **~$0,38** · **~$0,32** (menos 2.ª IA conversacional).

Detalle de flujos y caché: [estrategias-reduccion/matriz-casos-uso.md](./estrategias-reduccion/matriz-casos-uso.md).

---

### 2. Motivos de consulta (chat dedicado, antes de la atención)

Tras el chat del asistente (§1), el paciente puede cargar en el chat de **motivos** texto, audio e imágenes hasta **1 minuto antes del turno** (sin IA en cada mensaje). Al cerrar la ventana, **1 llamada a la IA** resume el hilo en `encounter.reason_text` para el médico. Implementación: `AppointmentReasonBatchService`, cron `MOTIVOS_IA_BATCH` vía `turno-notificacion/run`.

#### Caso A — solo texto (sin audio en el hilo)

Prompt más chico (~**1.000 input + 400 output** por lote; sin transcripciones).

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| IA (resumen lote) | 400 x tarifa IA (prompt corto) | **~$0.10** | **~$0.08** | **~$0.09** |
| STT | — | — | — | — |
| **Total caso A** | | **~$0.10** | **~$0.08** | **~$0.09** |

#### Caso B — con audio en el hilo

STT antes del lote; volumen IA estándar del doc (~1.500 tokens por llamada).

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| IA (resumen lote) | 400 x tarifa IA | **~$0.14** | **~$0.12** | **~$0.11** |
| STT (Groq, 1 min por encounter) | 400 min x tarifa STT | **~$0.28** | **~$0.28** | **~$0.28** |
| **Total caso B** | | **~$0.42** | **~$0.40** | **~$0.39** |

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| IA generativa | 400 x tarifa IA | **~$0.14** | **~$0.12** | **~$0.11** |

---

### 4. Captura clínica (encounter)

Cada consulta incluye **dictado en audio** del médico: no hay variante de costo «solo texto» ni «solo IA» — STT e inferencia van **siempre** juntos.

Flujo: audio dictado → STT → transcripción → **1 llamada a la IA** (`ConsultaProcesamientoService::analizar`).

| Concepto | Supuesto | Google sin context caching | Google con context caching | Together AI |
|----------|----------|------------------|------------------|-------------|
| STT (Groq, 1 min por encounter) | 400 min x tarifa STT | **~$0.28** | **~$0.28** | **~$0.28** |
| IA (análisis) | 400 x tarifa IA | **~$0.14** | **~$0.12** | **~$0.11** |
| **Total §4 (IA + STT)** | | **~$0.42** | **~$0.40** | **~$0.39** |

---

### 5. Intercambio de fotos y videos (Vision)

**Modelo de uso**: Los medios no se almacenan en cloud; solo hay costo cuando se envía a la nube para analizar (Vision). El audio de captura clínica va en §4. Ver [producto/flows/capacidades-paciente-medico.md](../../producto/apps-paciente-medico.md).

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| **Vision** (fotos compartidas) | supuesto §5 | **$0** |
| **Total §5** | — | **$0 por médico por mes** |

---

### 6. Videollamadas paciente–médico

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Minutos totales por mes | 120 x 12 | **1.440 min** |
| **Twilio Video** (2 participantes, $0.004 por min por participante) | 1.440 x 2 x $0.004 | **~$11.52 por médico por mes** |
| **Plan por asiento** (ej. Daily.co repartido entre 10 médicos) | — | **~$10 por médico por mes** (orden de magnitud) |

---

## Resumen: costo real por API (por médico por mes)

**Apartado 1:** IA generativa (Gemini y Together), incluido §4 con STT. **Apartado 2:** Vision (§5). **Apartado 3:** videollamadas (§6).

### Apartado 1 – IA generativa + STT captura clínica

| Concepto | Google sin context caching | Google con context caching | Together AI |
|----------|------------------|------------------|-------------|
| Conversación con el paciente (§1, `user_goal`) | ~$0.42 | ~$0.35 | ~$0.37 |
| Motivos — solo texto (§2 caso A) | ~$0.10 | ~$0.10 | ~$0.09 |
| Motivos — siempre audio (§2 caso B, IA+STT) | ~$0.42 | ~$0.40 | ~$0.39 |
| Agente onboarding (§3) | ~$0.14 | ~$0.12 | ~$0.11 |
| Captura clínica (§4, **IA + STT**) | ~$0.42 | ~$0.40 | ~$0.39 |
| **Total Apartado 1 — motivos solo texto** | **~$1.08** | **~$0.97** | **~$1.06** |
| **Total Apartado 1 — motivos con audio** | **~$1.40** | **~$1.27** | **~$1.36** |

### Apartado 2 – Medios (Vision §5)

| Concepto | Costo real (USD por médico por mes) |
|----------|-----------------------------|
| Vision (§5) | $0 |
| **Total (Apartado 2)** | **$0** |

### Apartado 3 – Videollamadas (§6)

Ver totales en [§6](#6-videollamadas-pacientemédico).

| Concepto | Costo real (USD por médico por mes) |
|----------|-----------------------------|
| **Twilio Video** ($0.004 por min por participante) | **~$11.52** |
| **Plan por asiento** (ej. Daily.co entre 10 médicos) | **~$10** (orden de magnitud) |

### Total general (Apartados 1 + 2 + 3)

§4 lleva STT **dentro** del total de apartado 1 (no fila aparte). En §2, no sumar dos veces el STT del caso B de motivos con el de §4: son audios distintos (paciente vs. médico).

| Escenario | Google sin context caching | Google con context caching | Together AI |
|-----------|------------------|------------------|-------------|
| Apartados 1 + 2 (motivos **solo texto**) | **~$1.08** | **~$0.97** | **~$1.06** |
| Apartados 1 + 2 (motivos **con audio**) | **~$1.40** | **~$1.27** | **~$1.36** |
| + Apartado 3 (**Twilio Video**) | **+$11.52** | **+$11.52** | **+$11.52** |
| **Total con videollamada (Twilio)** — motivos texto | **~$12.60** | **~$12.49** | **~$12.58** |
| **Total con videollamada (Twilio)** — motivos audio | **~$12.92** | **~$12.79** | **~$12.88** |
| **Total con videollamada (Daily ~$10)** — motivos audio | **~$11.40** | **~$11.27** | **~$11.36** |

**Orden de magnitud uso intensivo (todo incluido, Twilio):** **~USD 12–13 por prof por mes**. Solo IA + STT + Vision (sin §6): **~USD 1,3–1,4 por prof por mes** con motivos en audio (COGS base sin caché; §1 + §2 + §3 + §4).

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
