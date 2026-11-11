# Fase 03 — Vista staff

Estado: pendiente.

## Alcance

Que el equipo vea **de dónde salió** el motivo y qué respondió el paciente, sin abrir el chat.

## Entregables

| # | Entregable |
|---|------------|
| 1 | Respuestas del cuestionario en `patient_encounter_reasons` (texto de pregunta + opción + «Otra» + cierre libre) |
| 2 | Origen visible del resumen: `triage`, `asistente`, `cuestionario`, `chat` (marcas, no texto nuevo de IA) |
| 3 | Timeline staff web y móvil: bloque de motivos con el resumen arriba y las respuestas debajo |
| 4 | La HC del médico mantiene la regla de ventana vigente (`AppointmentReasonWindowService`) |

## Reglas

- Sin sección de «preguntas previas» inventada: se muestran sólo respuestas persistidas.
- Sin IA extra: el origen es un dato calculado, no una inferencia.

## Verificación

- `GET /api/v1/personas/{id}/historia-clinica?turno_id=` devuelve las respuestas.
- `codecept run unit clinical` y la suite de timeline.
