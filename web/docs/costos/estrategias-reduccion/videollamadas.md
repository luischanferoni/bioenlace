# Videollamadas

**COGS de planificación (matriz / calculador):** **USD 1,75 / prof / mes** @ **40 %** teleconsulta — ver [costos-api.md §6](../costos-api.md#6-videollamadas-pacientemédico).

Desglose planificado (escalado lineal desde el techo @ 80 %):

| Componente | USD / prof / mes |
|------------|------------------|
| Sala + TURN + Track Egress + ops (self-host + autoescalado) | **~0,75** |
| Storage + Deep Archive (14 d caliente → frío; 1 copia) | **~1,00** |
| Transcripción post-call | **0** en este add-on (mismo STT que dictado / §2–§4; se cobra **una sola vez** en el calculador) |
| **Total COGS videollamada** | **1,75** |

Supuesto de uso de planificación: **40 %** de las consultas × 12 min × 2 participantes ≈ **3.840 participant-minutes / prof / mes** (160 teleconsultas de 400 encounters; ver [analisis-videollamada-self-host.md](../analisis-videollamada-self-host.md)).  
Histórico: Daily + Deepgram @ 30 % (**9,19**); techo self-host @ 80 % (**3,50**); intermedio **5,00**. Vigente @ 40 %: **1,75** → **0,0044** USD / atención (1,75 / 400).

**Track Egress (muxing):** el cliente publica a 480p/720p @ 15 fps; el servidor **no** re-encodea. Autoescala grabación **min 1 / base 4 / max 12**.

**Calculador institucional:** si se tilda videollamada, el STT profesional (`audio` **0,98**) se incluye **una sola vez** (no se suma dictado + video como dos transcripciones).

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

Detalle: [costos-api §6](../costos-api.md#6-videollamadas-pacientemédico), [stt.md](./stt.md), [analisis-videollamada-self-host.md](../analisis-videollamada-self-host.md).

## Palancas operativas

- Límite de duración; publish a resolución de archivo desde el cliente.
- Autoescalado SFU + Track Egress (min 1 / base 4 / max 12).
- Lifecycle: 14 d caliente → Deep Archive (mín. 180 d); **sin** 2.ª copia.
- Alertar cuando `(gasto_video_infra + storage) / N_video > 1,75` varios meses.
