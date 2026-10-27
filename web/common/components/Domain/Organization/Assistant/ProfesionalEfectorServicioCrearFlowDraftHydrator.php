<?php

namespace common\components\Domain\Organization\Assistant;

use common\components\Domain\Organization\Pes\Application\UseCase\EnsurePesAssignment;
use common\components\Domain\Person\Identity\Application\Service\PersonCuilService;
use common\models\Person\Persona;
use common\models\Organization\ProfesionalEfectorServicio;
use common\models\Organization\Servicio;
use Yii;

/**
 * Completa el draft del intent `profesional-efector-servicio.crear-flow` antes de {@see \common\components\Platform\Assistant\SubIntentEngine\SubIntentEngine::process}:
 * - `servicio_acepta_turnos` desde catálogo si hay `id_servicio`
 * - Resuelve el vínculo persona–efector–servicio canónico vía {@see EnsurePesAssignment} cuando el ID
 *   del draft no corresponde a la combinación seleccionada.
 */
final class ProfesionalEfectorServicioCrearFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $options
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        self::hydrate($body);
    }

    public static function hydrate(array &$body): void
    {
        if (!isset($body['draft']) || !is_array($body['draft'])) {
            $body['draft'] = [];
        }
        $draft = &$body['draft'];

        $idServicio = isset($draft['id_servicio']) ? (int) $draft['id_servicio'] : 0;
        if ($idServicio > 0) {
            $s = Servicio::findOne($idServicio);
            if ($s !== null) {
                $draft['servicio_acepta_turnos'] = strtoupper(trim((string) $s->acepta_turnos));
            }
        }

        $idPersona = isset($draft['id_persona']) ? (int) $draft['id_persona'] : 0;
        if ($idPersona > 0) {
            $persona = Persona::findOne(['id_persona' => $idPersona]);
            $draft['persona_requiere_cuil'] = PersonCuilService::personaTieneCuil($persona) ? 'NO' : 'SI';
        }

        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idPes = isset($draft['id_profesional_efector_servicio']) ? (int) $draft['id_profesional_efector_servicio'] : 0;
        $cuil = isset($draft['cuil']) ? trim((string) $draft['cuil']) : '';

        if ($idPersona > 0 && $idServicio > 0 && $idEfector > 0) {
            $pes = $idPes > 0
                ? ProfesionalEfectorServicio::findOne([
                    'id' => $idPes,
                    'id_persona' => $idPersona,
                    'id_efector' => $idEfector,
                    'id_servicio' => $idServicio,
                    'deleted_at' => null,
                ])
                : null;
            if ($pes !== null) {
                return;
            }

            $out = EnsurePesAssignment::ensurePersonaServicioEnEfector(
                $idPersona,
                $idEfector,
                $idServicio,
                Yii::$app->has('user', true) && !Yii::$app->user->isGuest ? (int) Yii::$app->user->id : null,
                $cuil !== '' ? $cuil : null
            );
            $draft['id_profesional_efector_servicio'] = (string) $out['id_profesional_efector_servicio'];
            $draft['servicio_acepta_turnos'] = $out['servicio_acepta_turnos'];
        }
    }
}
