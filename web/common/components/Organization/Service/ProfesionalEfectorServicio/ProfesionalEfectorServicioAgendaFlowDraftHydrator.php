<?php

namespace common\components\Organization\Service\ProfesionalEfectorServicio;

use common\models\ProfesionalEfectorServicio;
use common\models\Servicio;
use Yii;
use yii\web\ForbiddenHttpException;

/**
 * Completa el draft cuando el intent declara `draft_hydrator.handler: organization.pes_from_servicio`:
 * - `servicio_acepta_turnos` desde catálogo si hay `id_servicio`
 * - `id_profesional_efector_servicio` desde persona + efector + servicio cuando falta
 * - opcionalmente valida que el PES sea del usuario (`require_own_pes`)
 */
final class ProfesionalEfectorServicioAgendaFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        self::hydrate($body, (bool) ($options['require_own_pes'] ?? false));
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function hydrate(array &$body, bool $requireOwnPes = false): void
    {
        if (!isset($body['draft']) || !is_array($body['draft'])) {
            $body['draft'] = [];
        }
        $draft = &$body['draft'];

        $idServicio = isset($draft['id_servicio']) ? (int) $draft['id_servicio'] : 0;
        if ($idServicio > 0 && (!isset($draft['servicio_acepta_turnos']) || $draft['servicio_acepta_turnos'] === '')) {
            $s = Servicio::findOne($idServicio);
            if ($s !== null) {
                $draft['servicio_acepta_turnos'] = strtoupper(trim((string) $s->acepta_turnos));
            }
        }

        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idPersona = (int) Yii::$app->user->getIdPersona();
        $idPes = isset($draft['id_profesional_efector_servicio']) ? (int) $draft['id_profesional_efector_servicio'] : 0;

        if ($idServicio > 0 && $idEfector > 0 && $idPes <= 0 && $idPersona > 0) {
            $pes = ProfesionalEfectorServicio::findOne([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'id_servicio' => $idServicio,
                'deleted_at' => null,
            ]);
            if ($pes !== null) {
                $draft['id_profesional_efector_servicio'] = (string) $pes->id;
                $idPes = (int) $pes->id;
            }
        }

        if (!$requireOwnPes || $idPes <= 0) {
            return;
        }

        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);
        if ($pes === null || (int) $pes->id_persona !== $idPersona) {
            throw new ForbiddenHttpException('Solo podés operar sobre tus propias asignaciones.');
        }
        if ($idEfector > 0 && (int) $pes->id_efector !== $idEfector) {
            throw new ForbiddenHttpException('La asignación no corresponde al efector de sesión.');
        }
    }
}
