# Fase 05 — Notificaciones y cierre del plan

Estado: pendiente. Depende de la fase 02 publicada.

## Alcance

Ajustar el recordatorio pre-turno para que el botón abra el cuestionario y no el chat, y cerrar el
plan volcando lo permanente a la documentación estable.

## Entregables

| # | Entregable |
|---|------------|
| 1 | Copy de `JOURNEY_MOTIVOS_RECORDATORIO` y `JOURNEY_MOTIVOS_ULTIMO_AVISO` en `encounter-journey-notifications.yaml`, con el verbo del cuestionario |
| 2 | Payload del push con la acción del cuestionario (tipo, `id_turno`, `phase`) y alerta in-app con `action_label` |
| 3 | Tipo de push propio si el journey necesita distinguir el cuestionario del chat |
| 4 | Actualizar `producto/recorrido-pre-post-consulta.md` y `negocio/costos/estrategias-reduccion/motivos-consulta.md` (sin STT, motivo deducido) |
| 5 | Borrar `plans/motivos-pre-turno/` y su fila en `plans/README.md` |

## Verificación

- `php tools/sync-agent-rules.php --check`.
- Enviar un recordatorio de prueba y confirmar que el botón abre el cuestionario del turno.
- `docs/` sin enlaces a `plans/`.
