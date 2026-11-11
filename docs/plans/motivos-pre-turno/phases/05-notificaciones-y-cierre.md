# Fase 05 — Notificaciones y cierre del plan

Estado: **implementada** (2026-11-11); queda la prueba de push en dispositivo y la decisión de
archivar la carpeta del plan.

## Alcance

Ajustar el recordatorio pre-turno para que el botón abra el cuestionario y no el chat, y cerrar el
plan volcando lo permanente a la documentación estable.

## Entregables

| # | Entregable | Estado |
|---|------------|--------|
| 1 | Copy de `JOURNEY_MOTIVOS_RECORDATORIO` y `JOURNEY_MOTIVOS_ULTIMO_AVISO` en `encounter-journey-notifications.yaml`, con el verbo del cuestionario | Hecho |
| 2 | Payload del push con la acción del cuestionario (tipo, `id_turno`, `phase`, `action`, `action_label`) y alerta in-app con `action_label` | Hecho |
| 3 | Tipo de push propio si el journey necesita distinguir el cuestionario del chat | No hace falta: la fase `motivos_consulta` ya identifica la superficie |
| 4 | Actualizar `producto/recorrido-pre-post-consulta.md` y `negocio/costos/estrategias-reduccion/motivos-consulta.md` (sin STT, motivo deducido) | Hecho |
| 5 | Borrar `plans/motivos-pre-turno/` y su fila en `plans/README.md` | Pendiente de decisión del equipo |

El catálogo de notificaciones admite `action` y `action_label` por entrada; el programador los
guarda en `turno_notificacion_programada.payload_json` y el cron los incluye en el push.

## Verificación

- `php tools/sync-agent-rules.php --check`.
- Enviar un recordatorio de prueba y confirmar que el botón abre el cuestionario del turno.
- `docs/` sin enlaces a `plans/` desde documentación estable.
