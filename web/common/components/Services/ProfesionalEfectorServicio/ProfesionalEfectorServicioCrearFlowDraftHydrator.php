<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\Servicio;
use Yii;

/**
 * Completa el draft del intent `agenda.crear-rrhh-flow` antes de {@see \common\components\Assistant\SubIntentEngine\SubIntentEngine::process}:
 * - `servicio_acepta_turnos` desde catálogo si hay `id_servicio`
 * - `id_rr_hh` (y coherencia de `servicio_acepta_turnos`) vía {@see ProfesionalEfectorServicioAltaService} si hay persona+servicio+efector en sesión.
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
        $idRrHh = isset($draft['id_rr_hh']) ? (int) $draft['id_rr_hh'] : 0;

        if ($idPersona > 0 && $idServicio > 0 && $idEfector > 0 && $idRrHh <= 0) {
            $out = ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector($idPersona, $idEfector, $idServicio);
            $draft['id_rr_hh'] = (string) $out['id_rr_hh'];
            $draft['servicio_acepta_turnos'] = $out['servicio_acepta_turnos'];
        }
    }
}
