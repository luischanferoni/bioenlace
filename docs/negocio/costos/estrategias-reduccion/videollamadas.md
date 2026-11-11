# Videollamadas

**COGS de planificación (matriz / calculador):** **USD 2,19 / prof / mes** @ **40 %** teleconsulta — ver [costos-api.md §6](../costos-api.md#6-videollamadas-pacientemédico).

Desglose planificado (escalado lineal desde el techo @ 80 %):

| Componente | USD / prof / mes | % del add-on |
|------------|------------------|--------------|
| Sala + TURN + Track Egress + ops (self-host + autoescalado) | **~0,94** | **42,9 %** |
| Storage + Deep Archive (14 d caliente → frío; 1 copia) | **~1,25** | **57,1 %** |
| Transcripción post-call | **0** en este add-on (mismo STT que dictado / §2–§4; se cobra **una sola vez** en el calculador) | **0 %** |
| **Total COGS videollamada** | **2,19** | **100 %** |

Supuesto de uso de planificación: **40 %** de las consultas × 12 min × 2 participantes ≈ **4.800 participant-minutes / prof / mes** (200 teleconsultas de 500 encounters; ver [analisis-videollamada-self-host.md](../analisis-videollamada-self-host.md)).  
Histórico: Daily + Deepgram @ 30 % (**9,19**); techo self-host @ 80 % (**3,50**); intermedio **5,00**. Vigente @ 40 %: **2,19** → **0,0044** USD / atención (2,19 / 500).

**Track Egress (muxing):** el cliente publica a 480p/720p @ 15 fps; el servidor **no** re-encodea. Autoescala grabación **min 1 / base 4 / max 12**.

**Calculador institucional:** si se tilda videollamada, el STT profesional (`audio` **1,23**) se incluye **una sola vez** (no se suma dictado + video como dos transcripciones).

## Roadmap de proveedor

| Fase | Proveedor | Rol |
|------|-----------|-----|
| **Objetivo** | Self-host (LiveKit) + TURN + **Track Egress**; STT Groq vía §2/§4 | Camino principal |
| **Histórico** | Daily.co + Deepgram; techo **5,00** | Ya no es lista |

## Transcripción post-call (fuera del add-on video en USD de infra)

Producto: tracks + VAD → motivos (§2) y note → análisis. El costo Groq está en el add-on **Dictado** / §2–§4, no otra vez en videollamada.

| Opción | Motor | Minutos / teleconsulta | Rol en COGS |
|--------|-------|------------------------|-------------|
| **Acordado** | Groq Whisper + VAD (~5 + ~4) | **~9** | En **audio / §2–§4**, una vez |
| Histórico | Daily → Deepgram | 12 recorded-min | Ya no en lista |

### Si el proveedor de video transcribiera (evaluado y descartado)

Las plataformas de video —Daily, o los plugins de transcripción de LiveKit— ofrecen STT propio, cobrado **por minuto de reunión** (~USD 0,0043–0,0059 recorded/unmuted-min). A 200 teleconsultas × 12 min = **2.400 min/prof/mes**, eso da **USD 10–14 / prof / mes**: **~5–6,5×** todo el add-on actual (**2,19**), y además transcribe silencios y los dos participantes completos (12 min crudos contra **~9 min de voz real** con VAD).

Por eso el modelo deja la transcripción **fuera** del add-on: el video solo entrega **pistas separadas** (Track Egress) y nuestro STT las procesa post-call **una sola vez**, reutilizando los minutos del dictado y de motivos. Contratar transcripción al proveedor de video solo tendría sentido si hiciera falta **transcripción en vivo** dentro de la llamada, capacidad que hoy no está en alcance.

Detalle: [costos-api §6](../costos-api.md#6-videollamadas-pacientemédico), [stt.md](stt.md), [analisis-videollamada-self-host.md](../analisis-videollamada-self-host.md).

## Palancas operativas

- Límite de duración; publish a resolución de archivo desde el cliente.
- Autoescalado SFU + Track Egress (min 1 / base 4 / max 12).
- Lifecycle: 14 d caliente → Deep Archive (mín. 180 d); **sin** 2.ª copia.
- Alertar cuando `(gasto_video_infra + storage) / N_video > 2,19` varios meses.
