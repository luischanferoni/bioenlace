<?php

namespace common\components\Scheduling\Service;

use common\components\Core\Service\Push\PushNotificationSender;
use Yii;
use yii\db\Expression;
use common\models\ProfesionalEfectorServicio;
use common\models\Scheduling\Turno;
use common\models\EfectorTurnosConfig;
use common\models\TurnoEventoAudit;
use common\models\TurnoNotificacionProgramada;

class BulkCancelDayService
{
    /**
     * Cancela todos los turnos PENDIENTE del día (opcionalmente filtrados por PES o por contexto de profesional).
     *
     * @param int $idEfector
     * @param string $fecha Y-m-d
     * @param int|null $staffContextId id PES o id de asignación usado para resolver persona (todas las PES de ese profesional en el efector)
     * @param int|null $idUser
     * @param int|null $idPes filtro por fila PES (más acotado que staffContextId)
     * @return int cantidad cancelados
     */
    public function cancelarDia($idEfector, $fecha, $staffContextId = null, $idUser = null, $idPes = null)
    {
        $cfg = EfectorTurnosConfig::getOrCreateForEfector((int) $idEfector);
        if (!$cfg->cancelacion_masiva) {
            throw new \RuntimeException('Cancelación masiva deshabilitada para este efector');
        }

        $q = Turno::findActive()
            ->andWhere(['id_efector' => (int) $idEfector, 'fecha' => $fecha, 'estado' => Turno::ESTADO_PENDIENTE]);

        if ($idPes) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => (int) $idPes, 'deleted_at' => null]);
            if ($pes === null || (int) $pes->id_efector !== (int) $idEfector) {
                throw new \InvalidArgumentException('id_profesional_efector_servicio inválido para este efector.');
            }
            $q->andWhere(['id_profesional_efector_servicio' => (int) $idPes]);
        } elseif ($staffContextId) {
            $idPersona = ProfesionalEfectorServicio::resolveIdPersonaFromStaffContextId((int) $staffContextId);
            if ($idPersona === null || $idPersona <= 0) {
                throw new \InvalidArgumentException('Identificador de profesional inválido para cancelación masiva.');
            }
            $pesIds = ProfesionalEfectorServicio::find()
                ->select(['id'])
                ->where([
                    'id_persona' => $idPersona,
                    'id_efector' => (int) $idEfector,
                    'deleted_at' => null,
                ])
                ->column();
            if ($pesIds === []) {
                $q->andWhere('0=1');
            } else {
                $q->andWhere(['id_profesional_efector_servicio' => $pesIds]);
            }
        }

        $models = $q->all();
        $n = 0;
        foreach ($models as $turno) {
            $turno->estado = Turno::ESTADO_CANCELADO;
            $turno->estado_motivo = Turno::ESTADO_MOTIVO_CANCELADO_MEDICO;
            $turno->deleted_by = $idUser ?: (Yii::$app->user->id ?? null);
            $turno->deleted_at = new Expression('NOW()');
            if ($turno->save(false)) {
                TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);
                TurnoEventoAudit::registrar($turno->id_turnos, TurnoEventoAudit::TIPO_BULK_DAY_CANCEL, $idUser, [
                    'fecha' => $fecha,
                ]);
                $push = new PushNotificationSender();
                if ($turno->paciente) {
                    $push->sendToPersona(
                        (int) $turno->id_persona,
                        ['type' => 'TURNO_CANCELADO_EFECTOR', 'id_turno' => (string) $turno->id_turnos],
                        'Turno cancelado',
                        'El efector canceló los turnos del día ' . $fecha . '. Contactá para reprogramar.'
                    );
                }
                $n++;
            }
        }
        return $n;
    }
}
