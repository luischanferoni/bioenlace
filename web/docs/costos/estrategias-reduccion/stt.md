# STT (transcripción de audio)

Baseline en [costos-api.md](../costos-api.md): **Groq Whisper** ~**$0,0007/min** ⇒ **~$0,28/médico/mes** (400 min intensivas). El código usa **Hugging Face** por defecto (`SpeechToTextManager`, `hf_stt_model`).

## Escalera de proveedores

| Orden | Proveedor | Cuándo | Coste orientativo |
|-------|-----------|--------|-------------------|
| 1 | Hugging Face | Ya integrado; volumen bajo–medio | Plan HF |
| 2 | Groq Whisper | Batch, &lt; 25 MB/archivo | ~$0,0007/min |
| 3 | Together / Fireworks | Si Groq limita | ~$0,001/min |
| 4 | Deepgram | Streaming / Nova-3 | ~$0,004/min batch |
| 5 | AssemblyAI | Streaming, extras | ~$0,002/min Slim |
| 6 | Whisper GPU propia | Alto volumen | [infra-costos.md](../infra-costos.md) |

## Reducir minutos facturables

- Transcribir **solo bajo demanda** (**50–100 %** del escenario 400 min automáticos).
- FFmpeg: silencios, compresión (ya en `SpeechToTextManager`).
- Caché por hash de audio.
- Batch async (motivos, lotes nocturnos).

## Referencias

- [Groq pricing](https://groq.com/pricing)
