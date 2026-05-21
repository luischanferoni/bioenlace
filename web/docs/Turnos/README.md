# Documentación — Módulo Turnos

Esta carpeta describe flujos de negocio del **sistema de turnos** (agenda, estados, cancelaciones y extensiones previstas).

## Índice

| Documento | Descripción |
|-----------|-------------|
| [Cancelación por el paciente](cancelacion-paciente.md) | Flujo cuando la baja corresponde al paciente (`CANCELADO_X_PACIENTE`). |
| [Cancelación por el médico / efector](cancelacion-medico.md) | Flujo cuando la baja la realiza el profesional o el establecimiento (`CANCELADO_X_MEDICO`). |
| [Política autogestión / cancelaciones](politica-cancelacion-autogestion.md) | Umbrales suave y moderada, liberación presencial/teléfono. |
| [Notificaciones](confirmacion-y-recordatorios.md) | Push, cron, tokens, ubicación stub. |
| [Sobreturno](sobreturno.md) | Turno urgente y notificaciones de demora. |
| [Cancelación masiva](cancelacion-masiva.md) | Por día, AdminEfector. |
| [Solicitudes entre médicos](solicitudes-medicos.md) | Módulo `solicitud_rrhh` y modos. |
| [Reprogramación UI](reprogramacion-ui.md) | Pantalla `turnos/reprogramar` y API. |
| [Agenda versionada e intervalo](agenda-intervalo-y-reservas.md) | Grilla 15–60 min, versiones `vigente_desde`, `slot_id`, solapamiento, conflictos y preview. |
| [Intents asistente (turnos/agenda)](intents-turnos.md) | Matriz intent ↔ API, flujos paciente y staff. |

## Referencias técnicas en el código

- Modelo: `common\models\Turno` — constantes `ESTADO_CANCELADO`, `ESTADO_MOTIVO_CANCELADO_PACIENTE`, `ESTADO_MOTIVO_CANCELADO_MEDICO`.
- Cancelación HTTP (frontend): `frontend\controllers\TurnosController::actionDelete`.
- UI calendario: `frontend\views\turnos\_calendario.php`, `frontend\web\js\turnos_calendario.js`.
- Búsqueda de slots: `common\components\Scheduling\Service\TurnoSlotFinder` (versión de agenda por fecha).
- Agenda versionada / intervalo: `ProfesionalEfectorServicioAgendaVersionService`, `AgendaSlotEngine`, `TurnoSlotOccupancyService`, `TurnoReservaSlotService` — ver [agenda-intervalo-y-reservas.md](./agenda-intervalo-y-reservas.md).
- API v1 turnos: `frontend\modules\api\v1\controllers\TurnosController` — convención y RBAC: **[API-nomenclatura-y-RBAC.md](./API-nomenclatura-y-RBAC.md)** (tabla canónica en el docblock del controlador).
- Servicios: `common\components\Scheduling\Service\*`, cron `php yii turno-notificacion/run`.
- Config por efector (backend): `backend\controllers\EfectoresController::actionTurnosIntegralConfig`.

## Implementación actual

- Ciclo de vida: `TurnoLifecycleService`, notificaciones en `turno_notificacion_programada`, push vía `Services\Push\PushNotificationSender` (`fcmPush` en params).
- Política autogestión: `TurnoCancellationPolicyService` + tabla `persona_efector_autogestion_liberacion`.
- Pendiente de producto: oferta automática de huecos a derivaciones `EN_ESPERA` (no implementado en esta entrega).
