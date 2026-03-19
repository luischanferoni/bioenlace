# Documentación — Módulo Turnos

Esta carpeta describe flujos de negocio del **sistema de turnos** (agenda, estados, cancelaciones y extensiones previstas).

## Índice

| Documento | Descripción |
|-----------|-------------|
| [Cancelación por el paciente](cancelacion-paciente.md) | Flujo cuando la baja corresponde al paciente (`CANCELADO_X_PACIENTE`). |
| [Cancelación por el médico / efector](cancelacion-medico.md) | Flujo cuando la baja la realiza el profesional o el establecimiento (`CANCELADO_X_MEDICO`). |

## Referencias técnicas en el código

- Modelo: `common\models\Turno` — constantes `ESTADO_CANCELADO`, `ESTADO_MOTIVO_CANCELADO_PACIENTE`, `ESTADO_MOTIVO_CANCELADO_MEDICO`.
- Cancelación HTTP (frontend): `frontend\controllers\TurnosController::actionDelete`.
- UI calendario: `frontend\views\turnos\_calendario.php`, `frontend\web\js\turnos_calendario.js`.
- Búsqueda de slots: `common\components\Services\Turnos\TurnoSlotFinder`.
- API v1 turnos: `frontend\modules\api\v1\controllers\TurnosController` (listados y creación; la cancelación vía API puede incorporarse según roadmap).

## Roadmap (notificaciones push)

Se prevé centralizar el ciclo de vida del turno (alta, cancelación, reprogramación) en un servicio de aplicación que:

- cancele recordatorios programados al anular el turno;
- envíe **push** al paciente y/o profesional según el caso;
- dispare la lógica de “hueco liberado” (p. ej. derivaciones en espera).

Hasta que exista esa capa, el comportamiento descrito en cada documento es el **actual en persistencia** más las **recomendaciones** de producto.
