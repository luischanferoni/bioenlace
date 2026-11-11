# Motivos de consulta (lote)

Una **sola IA por consulta** al cerrar la ventana (`AppointmentReasonBatchService`), con lo que el paciente ya contó al reservar y en el cuestionario pre-turno. El prompt incluye **contexto clínico acotado** del paciente (`PatientAiContextService`). Ver [costos-api.md §2](../costos-api.md#2-motivos-de-consulta-cuestionario-y-mensajes-antes-de-la-atención).

Cierre de captura: **`motivos_consulta_cierre_minutos` = 10** (params). En ese momento el cron `MOTIVOS_IA_BATCH` junta las fuentes (triage, asistente y cuestionario).

Contextos del [catálogo de IA](../../../producto/catalogo-usos-ia.md): `motivos-consulta-batch`, `motivos-consulta-insights`. Sin STT en este flujo ([stt.md](stt.md) queda para captura clínica).

## Palancas

- **Idempotencia**: `motivos_ia_processed_at`; no reprocesar salvo `--force` en consola.
- **Sin STT**: el audio de motivos no se transcribe; se elimina también el dictado en dispositivo del flujo.
- **Sin chat obligatorio**: el resumen se arma con el triage de reserva, los mensajes al asistente anteriores al turno y las respuestas del cuestionario pre-turno.
- **Una llamada**: el lote sigue siendo una sola IA por consulta; el cuestionario no dispara IA por respuesta.
- **Modelo:** `motivos-consulta-batch` → mantener Flash Lite. **`motivos-consulta-insights`** → candidato a **subir** a Pro/Flash si se valida calidad; ver [proveedor-modelo-tokens.md](proveedor-modelo-tokens.md).

No sumar al COGS base salvo validación.
