<?php

namespace common\components\Organization\Service\ProfesionalEfectorServicio;

use common\models\Servicio;
use Yii;

/**
 * Completa el draft del intent `agenda.crear-profesional-flow` antes de {@see \common\components\Assistant\SubIntentEngine\SubIntentEngine::process}:
 * - `servicio_acepta_turnos` desde catálogo si hay `id_servicio`
 * - Alta idempotente del vínculo persona–efector–servicio vía {@see ProfesionalEfectorServicioAltaService} al tener
 *   persona + servicio + efector en sesión y aún sin `id_profesional_efector_servicio` (mismo criterio con o sin agenda de turnos).
 */
final class ProfesionalEfectorServicioCrearFlowDraftHydrator
{
    public static function hydrate(array &$body): void
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

        $idPersona = isset($draft['id_persona']) ? (int) $draft['id_persona'] : 0;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        $idPes = isset($draft['id_profesional_efector_servicio']) ? (int) $draft['id_profesional_efector_servicio'] : 0;

        if ($idPersona > 0 && $idServicio > 0 && $idEfector > 0 && $idPes <= 0) {
            $out = ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector($idPersona, $idEfector, $idServicio);
            $draft['id_profesional_efector_servicio'] = (string) $out['id_profesional_efector_servicio'];
            $draft['servicio_acepta_turnos'] = $out['servicio_acepta_turnos'];
        }
    }
}
