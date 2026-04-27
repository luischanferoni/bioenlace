# Notificaciones (push)

## Casos en los que se envían notificaciones

- **Al crear un turno (API o web)**: `TurnoLifecycleService::afterTurnoCreado` genera `confirmacion_token` y programa filas en `turno_notificacion_programada`:
   - `CONFIRM_REQUEST` (~48 h antes)
   - `REMINDER` (~24 h antes)
   - `TRANSPORT_HINT` (~12 h antes)

- **Ejecución (cron/consola)**: `php yii turno-notificacion/run` procesa filas `PENDIENTE` con `run_at <= now`.

- **Al confirmar asistencia**: `POST api/v1/turnos/{id}/confirmar-asistencia` (opcional `token` en body; permiso `/api/turnos/confirmar-asistencia-como-paciente`). Marca `confirmado_en` y cancela notificaciones pendientes.

- **Al cancelar masivamente un día**: `POST api/v1/turnos/cancelar-dia-efector` (rol AdminEfector). Marca cancelación, cancela notificaciones pendientes y envía push al paciente (ver `cancelacion-masiva.md`).

## Contenido enriquecido

`TurnoReminderContentBuilder` usa `Efector` (`domicilio`, `formas_acceso`, `dias_horario`) y `NullEfectorDirectionsProvider` (coords null hasta integrar localidad / API externa).

## Push

`PushNotificationSender` registra en categoría `turnos-push`. Si `params['turnosPush']['httpEndpoint']` está definido, reenvía JSON al proxy configurado.

## Dispositivos

`POST api/v1/devices/push-token` — body: `device_id`, `push_token`, `push_provider`, `platform`.
