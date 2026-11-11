# Fase 03 — Vista staff

Estado: **implementada** (2026-11-11). Falta prueba visual en navegador.

## Alcance

Que el equipo vea **de dónde salió** el motivo y qué respondió el paciente, sin abrir el chat.

## Entregables

| # | Entregable | Estado |
|---|------------|--------|
| 1 | Respuestas del cuestionario en `patient_encounter_reasons` (texto de pregunta + opción + «Otra» + cierre libre) | Hecho |
| 2 | Origen visible del resumen: `triage`, `asistente`, `cuestionario`, `chat` (marcas, no texto nuevo de IA) | Hecho |
| 3 | Timeline staff web y móvil: bloque de motivos con el resumen arriba y las respuestas debajo | Hecho |
| 4 | La HC del médico mantiene la regla de ventana vigente (`AppointmentReasonWindowService`) | Hecho |

Campos nuevos del payload: `respuestas_cuestionario`, `cuestionario_completado` y `origen`. El
`resumen_pendiente` ahora también es verdadero cuando sólo hay respuestas del cuestionario.

## Reglas

- Sin sección de «preguntas previas» inventada: se muestran sólo respuestas persistidas.
- Sin IA extra: el origen es un dato calculado, no una inferencia.

## Verificación

- `GET /api/v1/personas/{id}/historia-clinica?turno_id=` devuelve las respuestas.
- `codecept run unit clinical`: sin fallas nuevas.
- `flutter test` en `mobile/personalsalud`: 60 tests OK, incluido el parseo de respuestas.
- `node --check` del timeline y `php -l` de la plantilla: OK.
