# AppointmentReason

Entry point para **motivo de consulta del paciente** (pre-turno; recurso `encounter_id`).

- **No** usa `Chat/Preprocess`.
- Persistencia: `enviarTexto` → `interaccion_motivos_consulta.encounter_id`.
- HTTP: `MotivosConsultaController` (`/api/v1/motivos-consulta/...`). Body: `encounter_id` (alias `consulta_id` en clientes en transición).
