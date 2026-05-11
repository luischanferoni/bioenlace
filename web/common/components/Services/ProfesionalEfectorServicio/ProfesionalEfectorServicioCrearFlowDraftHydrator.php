<?php

namespace common\components\Services\ProfesionalEfectorServicio;

use common\models\Servicio;
use Yii;

/**
 * Completa el draft del intent `agenda.crear-profesional-flow` antes de {@see \common\components\Assistant\SubIntentEngine\SubIntentEngine::process}:
 * - `servicio_acepta_turnos` desde catálogo si hay `id_servicio`
 * - Si el servicio acepta turnos (`SI`), alta idempotente de PES vía {@see ProfesionalEfectorServicioAltaService} cuando falta el id.
 *   Si no acepta turnos, la alta queda para el POST del paso de confirmación en API.
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
        $aceptaRaw = isset($draft['servicio_acepta_turnos']) ? strtoupper(trim((string) $draft['servicio_acepta_turnos'])) : '';

        // Solo crear PES aquí si el servicio acepta turnos (paso siguiente: configurar agenda).
        // Si no acepta turnos, la persistencia ocurre al confirmar en {@see ProfesionalEfectorServicioController::actionConfirmarAsignacionSinAgenda}.
        if ($idPersona > 0 && $idServicio > 0 && $idEfector > 0 && $idPes <= 0 && $aceptaRaw === 'SI') {
            $out = ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector($idPersona, $idEfector, $idServicio);
            $draft['id_profesional_efector_servicio'] = (string) $out['id_profesional_efector_servicio'];
            $draft['servicio_acepta_turnos'] = $out['servicio_acepta_turnos'];
        }
    }
}
