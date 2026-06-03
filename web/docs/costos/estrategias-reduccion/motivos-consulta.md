# Motivos de consulta (lote)

Una **sola IA por consulta** al cerrar la ventana (`AppointmentReasonBatchService`), tras el chat del asistente (§1). El prompt incluye **contexto clínico acotado** del paciente (`PatientAiContextBuilder`). Ver [costos-api.md §2](../costos-api.md#2-motivos-de-consulta-chat-dedicado-antes-de-la-atención).

## Palancas

- **Idempotencia**: `motivos_ia_processed_at`; no reprocesar salvo `--force` en consola.
- **STT solo en el lote**, no por mensaje (hoy en servidor) — decisión de arquitectura.
- **STT en dispositivo** al grabar (futuro en motivos): mensaje tipo texto → el lote no invoca STT; ver [stt.md](./stt.md).
- **Menos pacientes usando el chat** → 0 llamadas de este ítem.

No sumar al COGS base salvo validación.
