# Motivos de consulta (lote)

Una **sola IA por consulta** al cerrar la ventana (`AppointmentReasonBatchService`). Ver [costos-api.md §1](../costos-api.md).

## Palancas

- **Idempotencia**: `motivos_ia_processed_at`; no reprocesar salvo `--force` en consola.
- **STT solo en el lote**, no por mensaje.
- **Menos pacientes usando el chat** → 0 llamadas de este ítem.

No sumar al COGS base salvo validación.
