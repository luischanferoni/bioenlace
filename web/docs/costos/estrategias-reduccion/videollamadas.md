# Videollamadas

**COGS de planificación (matriz / calculador):** **USD 9,19 / prof / mes** — ver [costos-api.md §6](../costos-api.md#6-videollamadas-pacientemédico).

Desglose planificado:

| Componente | USD / prof / mes |
|------------|------------------|
| Sala + TURN + grabación + ops (buffer Daily→self-host) | **3,00** |
| Transcripción **post-call** Deepgram (vía Daily; 1.440 recorded-min) | **~6,19** |
| **Total COGS videollamada** | **9,19** |

Supuesto de uso: 30 % de las consultas × 12 min × 2 participantes ≈ **1.440 participant-minutes / prof / mes**.  
Grabación para STT: **120 teleconsultas × 12 min** = **1.440 recorded-minutes / prof / mes**.

## Roadmap de proveedor

| Fase | Proveedor | Rol |
|------|-----------|-----|
| **Corto / mediano** | [Daily.co](https://www.daily.co/pricing/video-sdk/) pay-as-you-go + post-call Deepgram | Integración rápida; 10.000 pax-min gratis/mes por cuenta; luego ~$0,004/pax-min; transcript ~$0,0043/recorded-min |
| **Mediano / largo** | Self-host (LiveKit u equivalente) + TURN + grabación; STT Groq o worker propio | Bajar el tramo sala (~3,00 → ~0,6–1,5) y/o STT (~6,19 → ~1,01 con Groq); el COGS lista **9,19** se mantiene como techo planificado |

No bajar el número de metadata solo porque el gasto real sea menor a escala: eso aumenta margen.

## Transcripción post-call (incluida en COGS 9,19)

Producto: al terminar la videollamada, transcript del audio grabado → `analizarTextoProcesado` / revisión del profesional → `clinical/encounter/guardar`. **No** se usa real-time en la planificación.

| Opción | Motor | Tarifa (ref.) | USD / prof / mes | Rol en COGS |
|--------|-------|---------------|------------------|-------------|
| **A — incluida en COGS** | Daily Batch Processor → **Deepgram** | ~**$0,0043**/recorded-min | **~$6,19** | **Sí** (fila STT del 9,19) |
| **B — palanca de reducción** | **Groq** `whisper-large-v3-turbo` | ~**$0,0007**/min | **~$1,01** | Si se migra el STT post-call a Groq, el COGS video tendería a **~4,01** (3,00 + 1,01); no bajar metadata hasta telemetría |
| Real-time | Daily → Deepgram en vivo | ~$0,0059/unmuted pax-min | No modelado | Fuera de alcance |

Si el transcript **reemplaza** el dictado del §4, no sumar dos veces el STT de captura (~0,28); el análisis IA sigue en §4.

Detalle de tarifas: [costos-api §6](../costos-api.md#6-videollamadas-pacientemédico), [stt.md](./stt.md).

## Palancas operativas

- Límite de duración (aviso 15–20 min): **10–25 %** sobre minutos de video **y** recorded-minutes de STT.
- Calidad adaptable / fallback audio.
- Post-call solo con consentimiento y grabación habilitada.
- Migrar STT post-call a Groq cuando el gasto Deepgram lo justifique (telemetría).
- Alertar cuando `(gasto_video + gasto_transcripcion_postcall + ops) / N_video > 9,19` varios meses → revisar retención, duración o proveedor STT.

El context caching de Gemini **no** afecta videollamadas ni el STT post-call (sí el análisis IA posterior).
