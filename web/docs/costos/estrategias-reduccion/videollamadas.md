# Videollamadas

**COGS de planificación (matriz / calculador):** **USD 5,00 / prof / mes** — ver [costos-api.md §6](../costos-api.md#6-videollamadas-pacientemédico).

Desglose planificado:

| Componente | USD / prof / mes |
|------------|------------------|
| Sala + TURN + grabación + ops (self-host + autoescalado) | **~3,00** |
| Storage + backup (buffer frío / retención) | **~2,00** |
| Transcripción post-call | **0** (ya en §2/§4: ~5 min médico + ~4 min paciente) |
| **Total COGS videollamada** | **5,00** |

Supuesto de uso de planificación agresiva: **80 %** de las consultas × 12 min × 2 participantes ≈ **7.680 participant-minutes / prof / mes** (ver [analisis-videollamada-self-host.md](../analisis-videollamada-self-host.md)).  
El COGS publicado histórico a **30 %** + Deepgram era **9,19**; se reemplazó al pasar STT a la base y self-host.

## Roadmap de proveedor

| Fase | Proveedor | Rol |
|------|-----------|-----|
| **Objetivo** | Self-host (LiveKit u equivalente) + TURN + grabación tracks; STT Groq vía §2/§4 | Camino principal |
| **Histórico** | Daily.co pay-as-you-go + Deepgram post-call | Integración rápida; ya no es COGS lista |

No bajar el número de metadata solo porque el gasto real de cómputo sea menor a escala: eso aumenta margen. Sí bajar cuando el modelo de costo cambia (p. ej. STT sale del add-on).

## Transcripción post-call (fuera del add-on video)

Producto: al terminar la videollamada, transcript del audio (tracks + VAD) → motivos (§2) y note del encounter → `analizarTextoProcesado` / revisión → `clinical/encounter/guardar`. **No** se usa real-time en la planificación.

| Opción | Motor | Minutos / teleconsulta | Rol en COGS |
|--------|-------|------------------------|-------------|
| **Acordado** | Groq Whisper + VAD (pista médico ~5 + paciente ~4) | **~9** | En **§2/§4**, no en add-on video |
| Histórico | Daily → Deepgram | 12 recorded-min | Ya no en lista |

Si el transcript **reemplaza** el dictado del §4 y los motivos del §2, no sumar dos veces el STT (~5 min médico + ~4 min paciente en COGS base); el análisis IA sigue en §4. Con VAD sobre tracks de videollamada la voz facturable es ~9 min/teleconsulta — ver [analisis-videollamada-self-host.md](../analisis-videollamada-self-host.md).

Detalle de tarifas: [costos-api §6](../costos-api.md#6-videollamadas-pacientemédico), [stt.md](./stt.md).

## Palancas operativas

- Límite de duración (aviso 15–20 min): **10–25 %** sobre minutos de video **y** recorded-minutes de STT.
- Calidad adaptable / fallback audio.
- Post-call solo con consentimiento y grabación habilitada.
- Autoescalado agresivo del SFU (cloud por hora + banda incluida).
- Retención video A vs B (años vs 30–90 d) — ver análisis self-host.
- Alertar cuando `(gasto_video_infra + storage) / N_video > 5,00` varios meses → revisar retención, duración o bitrate.

El context caching de Gemini **no** afecta videollamadas ni el STT post-call (sí el análisis IA posterior).
