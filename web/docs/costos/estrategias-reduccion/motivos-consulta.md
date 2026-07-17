# Motivos de consulta (lote)

Una **sola IA por consulta** al cerrar la ventana (`AppointmentReasonBatchService`), tras el chat del asistente (§1). El prompt incluye **contexto clínico acotado** del paciente (`PatientAiContextBuilder`). Ver [costos-api.md §2](../costos-api.md#2-motivos-de-consulta-chat-dedicado-antes-de-la-atención).

Cierre de captura: **`motivos_consulta_cierre_minutos` = 10** (params). En ese momento el cron `MOTIVOS_IA_BATCH` junta el hilo.

Contextos del [catálogo de IA](../../producto/catalogo-usos-ia.md): `motivos-consulta-batch`, `motivos-consulta-insights` (+ STT del hilo en [stt.md](./stt.md)).

## Palancas

- **Idempotencia**: `motivos_ia_processed_at`; no reprocesar salvo `--force` en consola.
- **STT solo en el lote**, no por mensaje — decisión de arquitectura.
- **Audios agrupados**: `SpeechToTextManager::transcribirLote` concatena todos los `TYPE_AUDIO` del encounter (FFmpeg) → **una** llamada Groq (evita el mínimo de 10 s por nota corta).
- **Textos**: escritos o transcripts on-device ya guardados como `TYPE_TEXTO` se concatenan en el transcript del lote **sin** STT.
- **STT en dispositivo** al grabar (app paciente): si la calidad alcanza → mensaje `TYPE_TEXTO`; el lote no invoca STT. Si no → audio al lote agrupado.
- **Menos pacientes usando el chat** → 0 llamadas de este ítem.
- **Modelo:** `motivos-consulta-batch` → mantener Flash Lite. **`motivos-consulta-insights`** → candidato a **subir** a Pro/Flash si se valida calidad; ver [proveedor-modelo-tokens.md](./proveedor-modelo-tokens.md).

No sumar al COGS base salvo validación.
