# Documentación — Módulo Turnos

Esta carpeta describe flujos de negocio del **sistema de turnos** (agenda, estados, cancelaciones y extensiones previstas).

## Índice

| Documento | Descripción |
|-----------|-------------|
| [Cancelación por el paciente](cancelacion-paciente.md) | Flujo cuando la baja corresponde al paciente (`CANCELADO_X_PACIENTE`). |
| [Cancelación por el médico / efector](cancelacion-medico.md) | Flujo cuando la baja la realiza el profesional o el establecimiento (`CANCELADO_X_MEDICO`). |
| [Política autogestión / cancelaciones](politica-cancelacion-autogestion.md) | Umbrales suave y moderada, liberación presencial/teléfono. |
| [Confirmación y recordatorios](confirmacion-y-recordatorios.md) | Push, cron, tokens, ubicación stub. |
| [Sobreturno](sobreturno.md) | Turno urgente y notificaciones de demora. |
| [Cancelación masiva](cancelacion-masiva.md) | Por día, AdminEfector. |
| [Solicitudes entre médicos](solicitudes-medicos.md) | Módulo `solicitud_rrhh` y modos. |
| [Reprogramación UI](reprogramacion-ui.md) | Pantalla `turnos/reprogramar` y API. |

## Referencias técnicas en el código

- Modelo: `common\models\Turno` — constantes `ESTADO_CANCELADO`, `ESTADO_MOTIVO_CANCELADO_PACIENTE`, `ESTADO_MOTIVO_CANCELADO_MEDICO`.
- Cancelación HTTP (frontend): `frontend\controllers\TurnosController::actionDelete`.
- UI calendario: `frontend\views\turnos\_calendario.php`, `frontend\web\js\turnos_calendario.js`.
- Búsqueda de slots: `common\components\Services\Turnos\TurnoSlotFinder`.
- API v1 turnos: `frontend\modules\api\v1\controllers\TurnosController` — cancelar, reprogramar, slots-alternativos, confirmar, bulk, política.
- Servicios: `common\components\Services\Turnos\*`, cron `php yii turno-notificacion/run`.
- Config por efector (backend): `backend\controllers\EfectoresController::actionTurnosIntegralConfig`.

## Implementación actual

- Ciclo de vida: `TurnoLifecycleService`, notificaciones en `turno_notificacion_programada`, push vía `PushNotificationSender` (log + `turnosPush.httpEndpoint` opcional).
- Política autogestión: `TurnoCancellationPolicyService` + tabla `persona_efector_autogestion_liberacion`.
- Pendiente de producto: oferta automática de huecos a derivaciones `EN_ESPERA` (no implementado en esta entrega).
