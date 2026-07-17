# Costos – Uso de APIs

Costos de referencia cuando usamos **APIs externas** (IA, STT, Vision, videollamadas), alineados con **Bioenlace en producción**:

## COGS (abreviatura)

**COGS** = *Cost of Goods Sold* (costo de bienes vendidos). En esta documentación: **costo variable directo** de prestar el servicio — lo que Bioenlace paga a terceros **por uso** (tokens Gemini, minutos Groq, minutos de video, etc.) atribuible a cada profesional/mes.

| Incluye | No incluye |
|---------|------------|
| APIs de IA, STT, Vision, videollamada según supuestos §1–§6 | Precio de licencia al cliente, soporte, ventas, marketing |
| Escenario **intensivo** modelado (p. ej. 400 consultas/médico/mes) | Infra fija (servidores, salarios) — ver [infra-costos.md](./infra-costos.md) |
| Columna **«sin context caching»** = **COGS base** (conservador, costo esperado) | Palancas de [estrategias-reduccion/](./estrategias-reduccion/README.md) hasta validarlas en producción |
| Columna **«con context caching»** = escenario **favorable**, no presupuesto garantizado | Impuestos — ver [impuestos-argentina.md](./impuestos-argentina.md) |

Cuando un doc dice «fuera del COGS» o «no en COGS base», significa que **aún no sumamos esa cifra** a las tablas fiscales hasta tener telemetría o piloto.

---
- **IA:** Google **Vertex / Gemini** con modelo **`gemini-2.5-flash-lite`** (`vertex_ai_model` en `params.php`). Columna **DeepSeek** = comparativa con **`deepseek-v4-flash`** (API directa); no es producción hoy.
- **Columnas Google:** **sin context caching** = **COGS base seguro**; **con context caching** = **escenario favorable**, no costo esperado (tokens repetidos a tarifa reducida de Vertex; ver abajo). No incluyen caché de aplicación ni otras tácticas de producto.
- **Contexto clínico del paciente:** bloque acotado (`PatientAiContextBuilder`) en §1 conversacional, §2 motivos y §4 captura — ver [§ Contexto clínico en prompts](#contexto-clínico-en-prompts-ia).
- **Escenario intensivo (COGS):** volumen de STT según supuestos §2 y §4 (paciente ~**4 min**, médico ~**5 min** de voz por encounter).

Otras reducciones (caché Yii, STT en dispositivo, context caching explícito, etc.) están en [estrategias-reduccion/](./estrategias-reduccion/README.md) y **no** se suman a las tablas de [impuestos-argentina.md](./impuestos-argentina.md) hasta validarlas. Precios unitarios de proveedores: [Precios de referencia](#precios-de-referencia-mayo-2026).

## Supuestos base

Por médico por mes, en orden del recorrido del paciente (detalle y costes en §1–6). Escala común **400 encounters por mes** (20 por día x 20 días) donde aplica §1, §2, §4, §5 y §6 — [infra-costos.md](./infra-costos.md).

- **§1 Conversación con el paciente:** 5 mensajes por encounter (~2.660 llamadas Vertex por mes). Misma cifra si el canal es app móvil o WhatsApp (paridad de uso; no duplicar).
- **§2 Motivos de consulta:** 1 llamada `motivos-consulta-batch` + **1** `motivos-consulta-insights` por encounter (si hay resumen); caso B (audio): COGS modela **~4 min de STT Groq por encounter** (voz paciente; 1.600 min/mes) — ver [§ STT](#stt).
- **§3 Onboarding y día a día:** 400 llamadas a la IA por mes (`asistente-onboarding` en metadata; en código reutiliza preprocess/conversacional)
- **§4 Captura clínica (encounter):** **siempre** audio del médico por consulta — **~5 min** STT (2.000 min/mes) + **400** llamadas `analisis-consulta` + **400** llamadas `encounter-codificacion-automatica` al guardar. Sin variante solo texto en el modelo de costos. Alineado a voz típica en consulta (~12 min de reloj → ~5 min de habla del profesional).
- **§5 Medios (fotos, etc.):** 2 fotos por encounter (Vision)
- **§6 Videollamada:** self-host; COGS planificado **5,00** = sala/TURN/ops + storage (**sin** STT: ya en §2/§4 con ~5+~4 min voz). Uso agresivo **80 %** tele — ver [§6](#6-videollamadas-pacientemédico) y [analisis-videollamada-self-host.md](./analisis-videollamada-self-host.md).
- **§7 WhatsApp (alcance actual):** solo respuestas a mensajes **iniciados por el paciente** (service window Meta ≈ $0; IA = §1). **Utility / plantillas proactivas: no habilitadas** — ver [§7](#7-whatsapp-cloud-api-paciente).

---

## Precios de referencia (mayo 2026)

| Servicio | Precio (USD) | Uso en este doc | Fuente |
|----------|--------------|-----------------|--------|
| **Didit** — Full KYC bundle | **~$0.33** por sesión exitosa; **500 gratis/mes** | Registro / alta paciente (identidad) | [didit.me/pricing](https://didit.me/pricing); detalle [costos-didit.md](./costos-didit.md) |
| **Didit** — Biometric Authentication | **~$0.10** por sesión exitosa | Reingreso tras logout (previsto) | Ídem |
| **Groq** — Whisper Large v3 Turbo | **~$0.0007 por min** ($0.04 por h); **mín. 10 s por request** | STT (§2 caso B, §4); opción B post-call video (§6) | [groq.com/pricing](https://groq.com/pricing), [GroqDocs STT](https://console.groq.com/docs/speech-to-text) |
| **Daily** — post-call transcription (Deepgram) | **~$0.0043 por recorded-min** | Histórico; **no** en COGS §6 vigente | [Daily pricing](https://www.daily.co/pricing/video-sdk/), [Transcription](https://docs.daily.co/docs/guides/features/transcription) |
| **Daily** — real-time transcription (Deepgram) | **~$0.0059 por unmuted pax-min** | Fuera de alcance (no planificado) | Ídem |
| **Vision API** (Label, Text, Face, etc.) | 1.000 unidades por mes gratis; luego $1.50 por 1.000 | Fotos §5 | [cloud.google.com/vision/pricing](https://cloud.google.com/vision/pricing) |
| **DeepSeek** — V4 Flash (API) | **$0.14 / $0.0028 / $0.28** por 1M (input miss / cache hit / output) | Comparativa IA | [DeepSeek API pricing](https://api-docs.deepseek.com/quick_start/pricing) |
| **WhatsApp Cloud API** — service (ventana 24 h) | **$0** por mensaje no-plantilla / utility dentro de CSW | Asistente reactivo §7 (alcance actual) | [Meta WhatsApp pricing](https://developers.facebook.com/docs/whatsapp/pricing/) |
| **WhatsApp Cloud API** — utility (Argentina) | **~$0,026** por plantilla entregada (list rate USD; Oct 2025) | **Fuera de alcance** (no habilitado) | Idem + rate card USD |

Para **Vertex AI / Gemini** conviene revisar el [Calculador de precios de Google Cloud](https://cloud.google.com/products/calculator) y la [tabla de precios de Gemini en Vertex AI](https://cloud.google.com/vertex-ai/generative-ai/pricing) (revisar cada 6–12 meses). Videollamadas y post-call STT: [Daily pricing](https://www.daily.co/pricing/video-sdk/) y [estrategias-reduccion/videollamadas.md](./estrategias-reduccion/videollamadas.md). Las tarifas WhatsApp por país/categoría cambian con los rate cards de Meta (revisar cada actualización trimestral).

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
| DeepSeek V4 Flash, sin caché | 1.000 x $0.14 por 1M + 500 x $0.28 por 1M | **~$0.00028** |
| DeepSeek V4 Flash + caché parcial | 750 input nuevos + 250 input cacheados + 500 output | **~$0.00025** |

**Cifras usadas en tablas §1–4** (1.500 tok., una invocación; redondeo conservador):

| Columna | USD por llamada (Gemini) | USD por llamada (DeepSeek) | Uso en pricing |
|---------|---------------------------|----------------------------|----------------|
| **Sin context caching** | **~$0.00035** | **~$0.00030** | **COGS base** |
| **Con context caching** | **~$0.00031** | **~$0.00027** | Escenario favorable (~**25 %** input cacheado) |

En tablas §1–4, **tarifa IA** remite a esta tabla; **tarifa STT** a [Groq](#precios-de-referencia-mayo-2026).

### DeepSeek V4 Flash (comparativa)

Referencia API oficial ([Models & Pricing](https://api-docs.deepseek.com/quick_start/pricing)). **No es el proveedor de producción**; sirve para contrastar coste si se migrara el mismo volumen de tokens.

| Concepto | Input (miss) | Input (cache hit) | Output |
|----------|--------------|-------------------|--------|
| **deepseek-v4-flash** | $0.14 | $0.0028 | $0.28 |

- Input **miss** más caro que Gemini Lite; **output** y **cache hit** más baratos → el ahorro neto depende del mix input/output y del % cacheado (ver [§ Contextos IAManager](#contextos-iamanager-y-tokens-de-referencia)).
- DeepSeek aplica **context caching automático** en prefijos repetidos (análogo al implícito de Vertex); las columnas «con caché» de DeepSeek usan los mismos `cached_ratio` por contexto que Gemini.
- En `params.php`, DeepSeek-R1 vía **Hugging Face** (`hf_model_*`) es otro canal de facturación; no usar estas tarifas para ese camino.

### Context caching (Vertex AI) — base de las columnas «con caché»

Bioenlace usa **`gemini-2.5-flash-lite`**. Las tablas comparan el mismo volumen de llamadas con tarifa **estándar de input** vs **input con tokens cacheados** según [pricing de Vertex](https://cloud.google.com/vertex-ai/generative-ai/pricing).

| Tipo | En producción hoy | En las tablas de este doc |
|------|-------------------|---------------------------|
| **Implícito** | Sí (Google aplica hits si el prefijo del prompt se repite; medible con `usageMetadata.cachedContentTokenCount`) | Columna **«con caché»** |
| **Explícito** (`cachedContents` en API) | **Simulado en local** (`vertex_context_cache_simulado`) | Mismo supuesto aritmético favorable hasta integrar API real; ver [estrategias-reduccion/context-caching-explicita.md](./estrategias-reduccion/context-caching-explicita.md) |

Detalle de cada tipo: [implícita](./estrategias-reduccion/context-caching-implicita.md) · [explícita](./estrategias-reduccion/context-caching-explicita.md).

- Tarifa input cacheado 2.5 Flash Lite: **$0.01 por M** vs **$0.10 por M** estándar (**90 %** desc. en esa porción).
- Supuesto **conservador** en tablas §2–4: **~25 %** del input cacheado (no usar ratios altos sin telemetría).
- **Conversación con el paciente (§1):** preprocess ~**40 %** cacheado; conversacional ~**40 %** (prefijo fijo; historial acotado en parte variable) — [§1](#1-conversación-con-el-paciente)
- Calibrar con `ia_usage_tracking_habilitado`, `vertex_context_cache_simulado` y `AICostTracker` por `contexto` ([monitoreo](./estrategias-reduccion/monitoreo.md)).
- El **output** no se cachea.

### Contextos IAManager y tokens de referencia

Catálogo completo: [producto/catalogo-usos-ia.md](../producto/catalogo-usos-ia.md). Tokens y `cached_ratio` en `web/common/metadata/bioenlace/ai/ai-cost-reference.yaml` (usado por `AICostEstimationService`).

| Contexto | Bloque COGS | Vol./prof/mes (ref.) | Input / output (tok.) | % input cacheado |
|----------|-------------|----------------------|------------------------|------------------|
| `asistente-preprocess` | §1 | **2.000** | 700 / 250 | **40 %** |
| `asistente-conversational` | §1 | **660** | 930 / 180 | **40 %** |
| `asistente-onboarding` | §3 | **400** | 1.000 / 500 | **25 %** |
| `intent-engine-classification` | Anexo A | **25** (bajo) | 800 / 200 | **25 %** |
| `motivos-consulta-batch` | §2 | **400** | 1.350 / 400 | **25 %** |
| `motivos-consulta-insights` | §2 | **400** | 1.200 / 350 | **25 %** |
| `analisis-consulta` | §4 | **400** | 1.200 / 600 | **25 %** |
| `encounter-codificacion-automatica` | §4 | **400** | 1.000 / 400 | **25 %** |
| `care-pack-assistance-batch` | Anexo B | por `cohort_key` | 2.200 / 900 | **50 %** |
| `care-pack-followup-batch` | Anexo B | por `cohort_key` | 2.400 / 1.000 | **50 %** |
| `care-pack-education-batch` | Anexo B | por `cohort_key` | 2.000 / 800 | **50 %** |
| `care-pack-vertex-batch` | Anexo B | por job batch | 2.200 / 900 | **50 %** |
| `terminos-contextuales` | — | 0 (reservado) | — | — |

**Apartado 1 (totales §1–§4):** suma los contextos con volumen **por profesional** (escenario 400 encounters/mes). **`intent-engine-classification`** y **care-packs** van en anexos — volumen bajo o por cohorte, no inflan el total por médico hasta tener telemetría.

**Coste unitario con caché (orientativo, por llamada):**

| Contexto | Gemini | DeepSeek |
|----------|--------|----------|
| `asistente-preprocess` | ~$0.000145 | ~$0.000130 |
| `asistente-conversational` | ~$0.000132 | ~$0.000130 |
| `motivos-consulta-batch` | ~$0.000265 | ~$0.000255 |
| `motivos-consulta-insights` | ~$0.000240 | ~$0.000230 |
| `analisis-consulta` | ~$0.000333 | ~$0.000295 |
| `encounter-codificacion-automatica` | ~$0.000238 | ~$0.000218 |

A **5.000 profesionales**, solo los seis contextos del Apartado 1 (sin §3 ni insights en la fila anterior): Gemini **~$3.550/mes** · DeepSeek **~$3.270/mes** (~**8 %** menos en IA pura con caché favorable).

### Contexto clínico en prompts IA

Implementación: `common/components/Domain/Clinical/AiContext/PatientAiContextBuilder.php` (`patient_ai_context` en `params.php`).

| Flujo | Perfil | Qué incluye | Tokens input extra (ref.) |
|-------|--------|-------------|---------------------------|
| §1 conversacional | `conversational` | Edad, sexo, alergias, condiciones y medicación (límites menores) | **~+280** |
| §2 motivos batch | `motivos` | Igual, perfil medio | **~+350** |
| §4 captura clínica | `encounter` | Igual, perfil completo | **~+350** |

- Techo configurado: **2.400 caracteres** (~600 tokens); en la práctica **~200–450 tokens** según datos del paciente.
- **No** va en preprocess operacional ni en clasificación por reglas.
- Parte **variable por paciente** (no cacheable entre distintos pacientes); en §1 el bloque se repite entre turnos del **mismo** paciente → posible upside de caché implícita no modelado (se mantiene **~40 %** conversacional).
- Calibrar con `AICostTracker` por `contexto` tras desplegar.

Para otras palancas (STT en dispositivo, caché de aplicación, context caching, etc.), ver [estrategias-reduccion/](./estrategias-reduccion/README.md).

---

## STT

Tarifa unitaria: [Precios de referencia](#precios-de-referencia-mayo-2026) (**Groq** `whisper-large-v3-turbo`). **Implementación en código:** el dispositivo es el camino inicial cuando está disponible y `SpeechToTextManager` usa **Groq por defecto** para el fallback servidor. Hugging Face queda como proveedor alternativo opt-in.

### Reglas Groq ASR (referencia COGS)

| Concepto | Valor |
|----------|--------|
| Precio | **USD 0,04 / hora** transcrita |
| Por minuto (orientativo) | **~USD 0,0007** (0,04 ÷ 60) |
| **Mínimo facturado** | **10 segundos por request**, aunque el audio sea más corto |
| Cobro por | Duración del audio en **cada** llamada a la API |

Ejemplo del mínimo por request: tres notas de 4 s en **tres** llamadas → **30 s** facturados. Con **`transcribirLote`** (motivos) se concatenan → **una** llamada de ~12 s (mínimo 10 s una sola vez).

### Supuesto del COGS por flujo

Las tablas §2 (caso B) y §4 asumen **400 consultas/médico/mes** y, cuando el audio va a Groq, voz típica de una consulta de ~12 min de reloj (tras recorte de silencios / VAD):

| Flujo | Minutos STT / encounter | Minutos / mes | USD / médico / mes (~$0,0007/min) |
|-------|-------------------------|---------------|-----------------------------------|
| **§4 Captura clínica** (médico) | **~5** | **2.000** | **~$1,40** |
| **§2 Motivos** caso B (paciente) | **~4** | **1.600** | **~$1,12** |
| **Total voz (médico + paciente)** | **~9** | **3.600** | **~$2,52** |

Origen del supuesto: en ~12 min de consulta, ~65–75 % es habla; el médico habla ~55–60 % de esa voz (~5 min) y el paciente ~40–45 % (~4 min). En teleconsulta, esas pistas pueden salir de la videollamada (tracks + VAD) y **reemplazan** dictado corto / notas de voz sueltas — no duplicar. Detalle: [analisis-videollamada-self-host.md](./analisis-videollamada-self-host.md).

### COGS de planificación (lista comercial) — −30 % on-device

Decisión de producto: el dispositivo intenta STT local primero; Groq es fallback. Para **matriz / calculador** se aplica **−30 %** sobre el STT bruto (orden de magnitud conservador; no el 50–80 % aspiracional). Ver [stt.md](./estrategias-reduccion/stt.md).

| Flujo | Bruto (todo servidor) | Planificación (−30 %) |
|-------|----------------------|------------------------|
| §4 médico (add-on **audio**) | **1,40** | **0,98** |
| §2 paciente (caso B, en intensivo) | **1,12** | **0,78** |
| Total voz | **2,52** | **1,76** |

Escala **5.000+** profesionales: todo servidor ~**USD 12.600/mes** Groq; con planificación −30 % ~**USD 8.800/mes**.

| Flujo | Qué pasa en producto | Nota de facturación |
|-------|----------------------|---------------------|
| **§4 Captura clínica** | Audio del médico (dictado o pista de videollamada) → dispositivo primero; Groq si falla calidad. | Un archivo concatenado por encounter evita el mínimo de **10 s** por fragmento. |
| **§2 Motivos de consulta** (caso B) | El paciente puede mandar **varias** notas de voz; el lote **concatena** los audios y hace **una** llamada Groq (`transcribirLote`). | Notas cortas ya no pagan el mínimo de **10 s** por mensaje. **STT en dispositivo** al grabar evita la llamada por completo. |

**Palancas adicionales:** telemetría `stt_fallback_rate`; modelo fit on-device ([stt.md](./estrategias-reduccion/stt.md#modelo-fit-on-device-base-clínica-nacional--lora-provincia--lora-speaker)). Calibrar el −30 % cuando haya datos reales.

Detalle de estrategia, calidad y fallback: [estrategias-reduccion/stt.md](./estrategias-reduccion/stt.md).

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

**Historial en charla clínica:** la 2.ª IA conversacional incluye una **ventana acotada** del hilo (implementación: `ConversationalHistoryWindow` — máx. **5 turnos**, **3.200 caracteres** de historial; corte si hubo un trámite operativo). El primer mensaje de un tema va casi solo con instrucciones; los siguientes arrastran contexto. Techo de input por llamada conversacional acotado en la tabla de tokens.

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
| 2.ª conversacional | ~930 y ~180 (instrucciones + **contexto clínico** + historial acotado + mensaje actual) | **~40 %** (prefijo fijo; bloque clínico semi-estable por paciente; historial variable) |

Tarifas Gemini: ver [§ Gemini Flash](#gemini-flash-tarifas-actuales-y-context-caching).

#### Coste mensual — conversación con el paciente (Google, escenario central)

| Concepto | Sin context caching (COGS) | Con context caching (favorable) |
|----------|----------------------------|----------------------------------|
| 2.000 x preprocess | ~$0,34 | ~$0,29 |
| 660 x conversacional | ~$0,11 | ~$0,09 |
| **Total §1** | **~$0,45** | **~$0,38** |
| DeepSeek V4 Flash (orden de magnitud) | **~$0,36** | **~$0,32** |

**Escenario alternativo (más conversacional, 45 % `conversational`):** sube a **~$0,49** sin caché · **~$0,41** con caché (~3.000 llamadas Vertex). **Escenario operativo fuerte (55 % operational):** baja a **~$0,41** · **~$0,35** (menos 2.ª IA conversacional).

Detalle de flujos y caché: [estrategias-reduccion/matriz-casos-uso.md](./estrategias-reduccion/matriz-casos-uso.md).

---

### 2. Motivos de consulta (chat dedicado, antes de la atención)

Tras el chat del asistente (§1), el paciente puede cargar en el chat de **motivos** texto, audio e imágenes hasta **10 minutos antes del turno** (sin IA en cada mensaje). Al cerrar la ventana:

1. **`motivos-consulta-batch`** — resume el hilo en `encounter.reason_text` (contexto clínico acotado).
2. **`motivos-consulta-insights`** — sugerencias orientativas para el médico (hipótesis / prácticas preliminares), si hay resumen.

Implementación: `AppointmentReasonBatchService`, `AppointmentReasonClinicalInsightsService`, cron `MOTIVOS_IA_BATCH` vía `turno-notificacion/run`.

#### Caso A — solo texto (sin audio en el hilo)

| Concepto | Supuesto | Google sin context caching | Google con context caching | DeepSeek V4 Flash |
|----------|----------|----------------------------|----------------------------|-------------------|
| IA (`motivos-consulta-batch`) | 400 x tarifa IA | **~$0.12** | **~$0.11** | **~$0.10** |
| IA (`motivos-consulta-insights`) | 400 x tarifa IA | **~$0.10** | **~$0.09** | **~$0.08** |
| STT | — | — | — | — |
| **Total caso A** | | **~$0.22** | **~$0.20** | **~$0.18** |

#### Caso B — con audio en el hilo

STT antes del lote; volumen IA con contexto clínico (~**1.850 tokens** por llamada: ~1.350 in + 500 out ref.). El COGS modela **~4 min de STT Groq por encounter** (voz paciente; fila siguiente). El lote **concatena** los audios del hilo en una sola llamada (`transcribirLote`) — ver [§ STT](#stt).

| Concepto | Supuesto | Google sin context caching | Google con context caching | DeepSeek V4 Flash |
|----------|----------|----------------------------|----------------------------|-------------------|
| IA (`motivos-consulta-batch`) | 400 x tarifa IA | **~$0.15** | **~$0.13** | **~$0.12** |
| IA (`motivos-consulta-insights`) | 400 x tarifa IA | **~$0.10** | **~$0.09** | **~$0.08** |
| STT (Groq, ~4 min por encounter) | 1.600 min x tarifa STT | **~$1.12** | **~$1.12** | **~$1.12** |
| **Total caso B** | | **~$1.37** | **~$1.34** | **~$1.32** |

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Google sin context caching | Google con context caching | DeepSeek V4 Flash |
|----------|----------|----------------------------|----------------------------|-------------------|
| IA generativa (`asistente-onboarding`) | 400 x tarifa IA | **~$0.14** | **~$0.12** | **~$0.11** |

---

### 4. Captura clínica (encounter)

Cada consulta incluye **audio del médico**: no hay variante de costo «solo texto» ni «solo IA» — STT e inferencia van **siempre** juntos.

Flujo: audio (dictado o pista de videollamada) → STT → transcripción → **1 llamada** `analisis-consulta` (extracción a campos) → al guardar, **1 llamada** `encounter-codificacion-automatica` (CIE-10/SNOMED en `clinical_condition`). Supuesto STT: **~5 min de voz del profesional por consulta** si va a Groq — alineado con [§ STT](#stt).

| Concepto | Supuesto | Google sin context caching | Google con context caching | DeepSeek V4 Flash |
|----------|----------|----------------------------|----------------------------|-------------------|
| STT (Groq, ~5 min por encounter) | 2.000 min x tarifa STT | **~$1.40** | **~$1.40** | **~$1.40** |
| IA (análisis `analisis-consulta`) | 400 x tarifa IA (~1.200 in + 600 out ref.) | **~$0.15** | **~$0.13** | **~$0.12** |
| IA (codificación `encounter-codificacion-automatica`) | 400 x tarifa IA (~1.000 in + 400 out ref.) | **~$0.14** | **~$0.12** | **~$0.11** |
| **Total §4 (IA + STT)** | | **~$1.69** | **~$1.65** | **~$1.63** |

---

### 5. Intercambio de fotos y videos (Vision)

**Modelo de uso**: Los medios no se almacenan en cloud; solo hay costo cuando se envía a la nube para analizar (Vision). El audio de captura clínica va en §4. Ver [producto/flows/capacidades-paciente-medico.md](../../producto/apps-paciente-personalsalud.md).

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| **Vision** (fotos compartidas) | supuesto §5 | **$0** |
| **Total §5** | — | **$0 por médico por mes** |

---

### 6. Videollamadas paciente–médico

Arquitectura: **self-host** (LiveKit + TURN + tracks + workers batch + storage frío). Autoescalado agresivo. Detalle: [analisis-videollamada-self-host.md](./analisis-videollamada-self-host.md).

Supuesto de uso agresivo: **320 teleconsultas × 12 min × 2 participantes** = **7.680 participant-minutes / médico / mes** (80 % de 400 encounters).  
STT post-call: **~9 min de voz** (médico ~5 + paciente ~4, con VAD) → ya modelado en **§2/§4**; **no** se suma otra vez aquí.

| Concepto | Notas | USD / médico / mes |
|----------|-------|--------------------|
| Sala + TURN + grabación + ops (self-host + autoescalado) | Buffer a 5.000+ prof | **~3,00** |
| Storage + backup (frío / retención) | Buffer hasta cerrar A vs B | **~2,00** |
| Transcripción post-call | Cubierta por §2/§4 | **0** |
| **COGS de planificación (matriz / calculador)** | Suma de las filas anteriores | **5,00** |
| Histórico Daily + Deepgram | 30 % tele; ya no es lista | Era **9,19** |

Real-time Deepgram (~$0,0059/unmuted pax-min): **fuera de alcance**.

Detalle: [estrategias-reduccion/videollamadas.md](./estrategias-reduccion/videollamadas.md).

---

### 7. WhatsApp (Cloud API, paciente)

**Decisión de producto:** WhatsApp queda en funcionamiento solo para el **asistente reactivo** — respuestas a mensajes **iniciados por el paciente**. **No** se habilitan plantillas **utility** (ni marketing) para avisos proactivos; esos siguen en **push** (y escalada email/SMS de agentes). Ver [asistente-y-chat.md](../producto/asistente-y-chat.md), [turnos.md](../producto/turnos.md).

Misma superficie de asistente que la **app móvil paciente**: mismo `ChatOrchestrator`, mismo mix del §1. **No** hay delta de IA por canal (paridad de uso; no duplicar mensajes).

| Capa | Alcance actual | Qué cobra Meta | Delta vs solo app |
|------|----------------|----------------|-------------------|
| **Service** (texto / botones / listas en ventana **24 h** tras mensaje del usuario) | **Sí** | **$0** | **~$0** |
| **IA §1** (preprocess ± conversacional) | **Sí** | Vertex (igual que app) | **~$0** |
| **Utility** (recordatorios, resolución, waitlist, anti no-show, etc.) | **No habilitado** | ~$0,026 AR / msg (list USD) si se usara | **N/A — fuera de alcance** |
| Marketing / authentication | **No habilitado** | Rate card Meta | **N/A** |

**COGS Meta del alcance actual:** **~$0**/prof/mes (solo service window). El costo variable sigue siendo el §1 de IA, atribuido al volumen de chat (app o WhatsApp), no un add-on Meta.

Referencia de tarifas (por si se reabre utility en el futuro): [Meta WhatsApp pricing](https://developers.facebook.com/docs/whatsapp/pricing/) + rate card USD. Utility dentro de CSW también es $0; el costo aparece al enviar plantillas **fuera** de ventana.

---

## Anexo A — `intent-engine-classification` (fuera del total Apartado 1)

Fallback del clasificador global cuando reglas + confianza no alcanzan. Volumen **bajo** (supuesto **25 llamadas/prof/mes**; calibrar con `AICostTracker`).

| Escenario | Google (con caché favorable) | DeepSeek V4 Flash |
|-----------|-------------------------------|-------------------|
| Por profesional / mes | **~$0,004** | **~$0,004** |
| **5.000 profesionales / mes** | **~$20** | **~$18** |

---

## Anexo B — Care packs (fuera del total Apartado 1)

Generación por **`cohort_key`**, no por encounter. Contextos: `care-pack-assistance-batch`, `care-pack-followup-batch`, `care-pack-education-batch`, `care-pack-vertex-batch` (este último vía Vertex batch jobs; tarifa batch puede diferir).

Supuesto ilustrativo: **10 cohortes nuevas/mes** en todo el tenant (no por médico), **1 llamada** por tipo de pack al crear cohorte:

| Contexto | Tokens ref. (in / out) | % cache | Costo / llamada (Gemini, caché) | 10 llamadas / mes |
|----------|------------------------|---------|--------------------------------|-------------------|
| `care-pack-assistance-batch` | 2.200 / 900 | 50 % | ~$0.00055 | ~$0.006 |
| `care-pack-followup-batch` | 2.400 / 1.000 | 50 % | ~$0.00062 | ~$0.006 |
| `care-pack-education-batch` | 2.000 / 800 | 50 % | ~$0.00050 | ~$0.005 |

Orden de magnitud tenant: **< USD 50/mes** en generación sync hasta escalar cohortes; **no** prorratear por los 5.000 profesionales sin telemetría real.

---

## Anexo C — Didit (identidad y biometría remota)

**Fuera del COGS por médico** de las tablas §1–§6: Didit se factura **por verificación exitosa** (KYC en registro, reingreso biométrico), no por consulta ni por profesional. §7 WhatsApp (alcance actual) no suma Meta.

| Concepto | Tarifa pública (ref.) | Cupo |
|----------|----------------------|------|
| Full KYC (registro paciente / alta staff) | **~USD 0,33** / sesión | **500 gratis / mes** por workspace |
| Biometric Authentication (reingreso) | **~USD 0,10** / sesión | Comparte el mismo cupo mensual |
| Huella local del dispositivo | **USD 0** | No es Didit |

Proyección por escala (altas, pacientes activos, reingresos tras logout, escenario «Didit en cada apertura» a evitar): **[costos-didit.md](./costos-didit.md)**.

---

## Resumen: costo real por API (por médico por mes)

**Apartado 1:** IA generativa (Gemini y comparativa DeepSeek), incluido §4 con STT e **insights** de §2. **Apartado 2:** Vision (§5). **Apartado 3:** videollamadas (§6). **§7 WhatsApp** (reactivo): Meta **~$0**; no es fila de COGS aparte. Anexos A y B: [fuera del total](#anexo-a--intent-engine-classification-fuera-del-total-apartado-1).

### Apartado 1 – IA generativa + STT captura clínica

| Concepto | Google sin context caching | Google con context caching | DeepSeek V4 Flash |
|----------|----------------------------|----------------------------|-------------------|
| Conversación con el paciente (§1) | ~$0.45 | ~$0.38 | ~$0.41 / ~$0.35 |
| Motivos — solo texto (§2 caso A, batch + insights) | ~$0.22 | ~$0.20 | ~$0.20 / ~$0.18 |
| Motivos — con audio (§2 caso B, IA+STT ~4 min) | ~$1.37 | ~$1.34 | ~$1.34 / ~$1.32 |
| Agente onboarding (§3) | ~$0.14 | ~$0.12 | ~$0.13 / ~$0.11 |
| Captura clínica (§4, **IA + STT ~5 min**) | ~$1.69 | ~$1.65 | ~$1.65 / ~$1.63 |
| **Total Apartado 1 — motivos solo texto** | **~$2.50** | **~$2.35** | **~$2.39 / ~$2.27** |
| **Total Apartado 1 — motivos con audio** | **~$3.65** | **~$3.49** | **~$3.53 / ~$3.41** |

### Apartado 2 – Medios (Vision §5)

| Concepto | Costo real (USD por médico por mes) |
|----------|-----------------------------|
| Vision (§5) | $0 |
| **Total (Apartado 2)** | **$0** |

### Apartado 3 – Videollamadas (§6)

Ver totales en [§6](#6-videollamadas-pacientemédico).

| Concepto | Costo (USD por médico por mes) |
|----------|-------------------------------|
| Sala / TURN / ops + storage | **5,00** |
| Post-call STT | **0** (en §2/§4) |
| **COGS planificación (lista comercial)** | **5,00** |

### Apartado 4 – WhatsApp (§7)

**Alcance actual:** solo asistente iniciado por el paciente → Meta **~$0**/prof/mes. Utility **no habilitada**. Detalle: [§7](#7-whatsapp-cloud-api-paciente).

### Total general (Apartados 1 + 2 + 3)

§4 lleva STT **dentro** del total de apartado 1 (no fila aparte). En §2, no sumar dos veces el STT del caso B de motivos con el de §4: son audios distintos (paciente ~4 min vs. médico ~5 min). Si la videollamada alimenta ambos, ver [analisis-videollamada-self-host.md](./analisis-videollamada-self-host.md).

| Escenario | Google sin context caching | Google con context caching | DeepSeek V4 Flash |
|-----------|----------------------------|----------------------------|-------------------|
| Apartados 1 + 2 (motivos **solo texto**) | **~$2.50** | **~$2.35** | **~$2.39 / ~$2.27** |
| Apartados 1 + 2 (motivos **con audio**) | **~$3.65** | **~$3.49** | **~$3.53 / ~$3.41** |
| + Apartado 3 (**videollamada, COGS planificado**) | **+$5,00** | **+$5,00** | **+$5,00** |
| **Total con videollamada** — motivos texto | **~$7,50** | **~$7,35** | **~$7,39 / ~$7,27** |
| **Total con videollamada** — motivos audio | **~$8,65** | **~$8,49** | **~$8,53 / ~$8,41** |

**Orden de magnitud uso intensivo (todo incluido, video con COGS 5,00):** bruto todo-servidor **~USD 8–9**/prof/mes; con **−30 % STT on-device** en planificación **~USD 7,5–8**/prof/mes. Solo IA + STT + Vision (sin §6): bruto **~USD 3,5–3,7**; planificación **~USD 2,7–2,9** (motivos en audio).

**WhatsApp (§7, alcance actual):** Meta **~$0** (sin utility). La IA del chat sigue en §1.

**De COGS a precio de lista:** la licencia comercial usa la columna **con context caching** y el STT de planificación (**audio 0,98**) — `precio = COGS × (1 + margin_on_cost_percent/100)` (hoy margen **233 %** ≈ 70 % bruto). Detalle y add-ons audio/videollamada: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md). Metadata: `pricing-pes-by-encounter-class.yaml` (+ `institucional/js/pricing-config.json`).

**Nota:** Si la IA corre en **nuestra infra**, los ítems del apartado 1 figuran en [infra-costos.md](./infra-costos.md) y no se duplican aquí. El apartado 3 usa COGS planificado **5,00** (self-host; STT en §2/§4) — ver [videollamadas.md](./estrategias-reduccion/videollamadas.md).

---

## Referencias

- [impuestos-argentina.md](./impuestos-argentina.md) – IVA, IIBB y ganancias (AR) sobre costo y facturación.
- [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) – precio lista = COGS × (1 + margen); calculador institucional.
- [estrategias-reduccion/](./estrategias-reduccion/README.md) – Palancas adicionales (no incluidas en COGS base).
- [Groq pricing](https://groq.com/pricing) – STT de referencia en tablas.
- [Vertex AI – Context caching overview](https://cloud.google.com/vertex-ai/generative-ai/docs/context-cache/context-cache-overview)
- [infra/costos.md](../infra/costos.md) – Costes cuando la IA corre en nuestra GPU.
- [DeepSeek API – Models & Pricing](https://api-docs.deepseek.com/quick_start/pricing) – comparativa IA en tablas.
- [costos-didit.md](./costos-didit.md) – Didit KYC y biometría remota (proyección por altas y reingresos).
- [producto/asistente-y-chat.md](../producto/asistente-y-chat.md) – WhatsApp como superficie del asistente paciente.
- [Meta WhatsApp pricing](https://developers.facebook.com/docs/whatsapp/pricing/) – service / utility / marketing.
- [producto/flows/capacidades-paciente-medico.md](../../producto/apps-paciente-personalsalud.md) – Descripción de las capacidades.
