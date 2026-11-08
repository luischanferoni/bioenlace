### 1. Conversación con el paciente (chat asistente)

Un solo chat para el paciente: turnos, síntomas, menú de ayuda o mensajes poco claros.

**Arquitectura actual (preprocess + guide):**

1. **Preprocess (1ª IA)** en cada mensaje — clasifica `routing_hint` (`guide` / `fuera_his` / `sin_pedido`), devuelve `normalized_text` (ortografía corregida, abreviaturas abiertas), extracciones y tags.
2. **Discovery** — PHP cruza tags con `meta.tags` de intents (YAML Domain) y keywords de artículos BD (`DiscoveryIndex`).
3. **Routing** — Handlers deciden camino según `routing_result`:
   - `dudosa` / `fuera_de_his` → 0 IAs adicionales (mensaje límite / aclaración)
   - `clara` / `incompletas` → **Guide (2ª IA)** con adjuntos (intents y/o artículo)

Detalle: [arquitectura/asistente-motores.md](../../arquitectura/asistente-motores.md), [decisions/asistente-canal-guide.md](../../decisions/asistente-canal-guide.md).

Los **pasos de un flujo operativo** (elegir profesional, completar formulario, etc.) continúan en el chat con `intent_id`; no consumen una 2.ª IA de clasificación. Si el paciente escribe texto en un paso, puede volver a correr preprocess.

**Historial en charla guide:** la 2.ª IA guide incluye una **ventana acotada** del hilo (implementación: `GuideHistoryWindow` — foco por área, acotado). El primer mensaje de un tema va casi solo con instrucciones; los siguientes arrastran contexto. Techo de input por llamada guide acotado en la tabla de tokens.

Supuesto de actividad (AMB): **5.000 mensajes por mes** (10 por encounter × 500 encounters). Varios ida y vuelta guide cuentan como **varios mensajes** dentro de esos 10.

#### Volumen de llamadas IA por mensaje (escenario central — ajustar con telemetría)

La 1.ª IA ya corrió; la tabla indica **si hace falta una 2.ª** y cuántas llamadas IA suma ese mensaje en total. Los % suman 100 % de los **5.000** mensajes (10 × 500 encounters, solo AMB en el modelo comercial).

|| `routing_result` | Qué escribe el paciente (ejemplos) | Mensajes por mes | ¿2.ª IA? | Total llamadas IA por mensaje |
||------------------|-----------------------------------|------------------|----------|-------------------------------|
|| `clara` / `incompletas` | Pedido claro pero incompleto, síntomas, charla clínica | **2.650** (53 %) | **Sí**, guide con adjuntos | **2** |
|| `dudosa` | Saludo, mensaje muy corto, sin necesidad clara | **2.350** (47 %) | **No** (solo pide aclaración) | **1** |

**Cómo se traduce en volumen mensual:**

|| Tipo de llamada | Cálculo (sobre 5.000 mensajes) | Llamadas por mes |
||-----------------|--------------------------------|------------------|
|| 1.ª IA — preprocess (todos) | 5.000 | **5.000** |
|| 2.ª IA — guide | 2.650 | **2.650** |
|| **Total Vertex** | 5.000 + 2.650 | **~7.650** |

**Por profesional (500 encounters/mes):** ~**7.650** llamadas/mes (5.000 preprocess + 2.650 guide). Ver `ai-cost-reference.yaml`.

#### Tokens y context caching por tipo de llamada

|| Tipo de llamada | Input y output (ref.) | % input cacheado (implícito, conservador) |
||-----------------|----------------------|-------------------------------------------|
|| Preprocess | ~700 y ~250 | **~40 %** |
|| Guide | ~1.000 y ~200 (instrucciones + **adjuntos discovery** + contexto clínico + historial acotado + mensaje actual) | **~40 %** (prefijo fijo; bloque clínico semi-estable por paciente; adjuntos variables; historial acotado) |

Tarifas Gemini: ver [§ Gemini Flash](#gemini-flash-tarifas-actuales-y-context-caching).

#### Coste mensual — conversación con el paciente (Google, escenario central, 10 msgs/encounter)

|| Concepto | Sin context caching (COGS) | Con context caching (favorable) |
||----------|----------------------------|----------------------------------|
|| 5.000 x preprocess | ~$0,85 | ~$0,73 |
|| 2.650 x guide | ~$1,06 | ~$0,91 |
|| **Total §1** | **~$1,91** | **~$1,64** |
|| DeepSeek V4 Flash (orden de magnitud) | **~$1,53** | **~$1,38** |

**Por profesional (500 encounters/mes):** ~**$1,91** sin caché · ~**$1,64** con caché.

**Escenario alternativo (más guide, 60 % clara/incompletas):** sube a **~$2,10** sin caché · **~$1,80** con caché. **Escenario operativo fuerte (70 % dudosa):** baja a **~$1,73** · **~$1,48**.

Detalle de flujos y caché: [estrategias-reduccion/matriz-casos-uso.md](estrategias-reduccion/matriz-casos-uso.md).

> **Nota comercial (pricing):** el COGS §2 que entra al calculador **no** asume audio en el 100 % de las atenciones. Blend por `encounter_class` (caso B × share + caso A × (1−share)), con context caching → AMB **~30 %** ≈ **0,0014**; EMER **~45 %** ≈ **0,0018**; IMP **~50 %** ≈ **0,0019** (`motivos_audio_by_class` en metadata). EMER/IMP asumen más notas de voz de triaje, pases y enfermería, no solo del médico. El cupo de **10 mensajes** en §1 es capacidad / headroom (prompts más largos o varias llamadas IA), no un supuesto de uso pleno en cada mensaje.
