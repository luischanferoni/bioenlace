<?php

namespace common\components\Domain\Scheduling\Service;

use Yii;
use common\models\ProfesionalEfectorServicio;
use common\models\Scheduling\Turno;
use common\models\EfectorTurnosConfig;
use common\models\TurnoEventoAudit;

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
        $lifecycle = new TurnoLifecycleService();
        $n = 0;
        foreach ($models as $turno) {
            try {
                $ok = $lifecycle->cancelar(
                    $turno,
                    Turno::ESTADO_MOTIVO_CANCELADO_EFECTOR,
                    'admin',
                    $idUser ?: (Yii::$app->user->id ?? null),
                    [
                        'fecha' => $fecha,
                        'bulk_day_cancel' => true,
                        'razon_cancelacion' => TurnoEventoAudit::TIPO_BULK_DAY_CANCEL,
                    ],
                    true,
                    TurnoEventoAudit::ACTOR_EFECTOR
                );
                if ($ok) {
                    $n++;
                }
            } catch (\Throwable $e) {
                Yii::warning(
                    'BulkCancelDay: turno=' . (int) $turno->id_turnos . ' ' . $e->getMessage(),
                    'turno-bulk-cancel'
                );
            }
        }
        return $n;
    }
}
