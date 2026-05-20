# AppointmentReason

Entry point para **motivo de consulta del paciente** (chat de motivos ligado a `id_consulta`).

- **No** usa `Chat/Preprocess`.
- Hoy: persistir texto (`enviarTexto`). Extracción IA estructurada puede sumarse aquí sin pasar por `asistente/enviar`.
- HTTP: `MotivosConsultaController` (`/api/v1/motivos-consulta/...`).
