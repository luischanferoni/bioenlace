<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Service;

use common\components\Domain\Scheduling\BehaviorProfile\Application\Service\TurnoBehaviorProfileReadService;
use common\components\Domain\Scheduling\BehaviorProfile\Domain\Catalog\TurnoBehaviorProfileContract;
use common\models\Scheduling\Turno;
use common\models\Scheduling\PersonaTurnosPerfilMetrica;
use common\models\Scheduling\EfectorTurnosConfig;
use common\models\Scheduling\PersonaEfectorAutogestionLiberacion;

/**
 * Política suave → moderada sobre autogestión (app), no sobre derecho a cancelar en persona/llamada.
 */
class TurnoCancellationPolicyService
{
    const NIVEL_OK = 'OK';
    const NIVEL_SUAVE = 'SUAVE';
    const NIVEL_MODERADA = 'MODERADA';

    /**
     * @param int $idPersona
     * @param int $idEfector
     * La decisión legacy sigue gobernando mientras la política candidata está en shadow.
     *
     * @return array<string, mixed>
     */
    public function evaluarAutogestion($idPersona, $idEfector)
    {
        $cfg = EfectorTurnosConfig::getOrCreateForEfector($idEfector);
        $ventana = max(1, (int) $cfg->cancel_ventana_dias);
        $since = date('Y-m-d H:i:s', strtotime('-' . $ventana . ' days'));

        $n = Turno::findInactive()
            ->where([
                'id_persona' => (int) $idPersona,
                'id_efector' => (int) $idEfector,
                'estado' => Turno::ESTADO_CANCELADO,
                'estado_motivo' => Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE,
            ])
            ->andWhere(['>=', 'deleted_at', $since])
            ->count();

        $liberacionVigente = PersonaEfectorAutogestionLiberacion::tieneLiberacionVigente(
            $idPersona,
            $idEfector,
            (int) $cfg->autogestion_liberacion_vigencia_dias
        );

        $suave = (int) $cfg->cancel_suave_umbral;
        $mod = (int) $cfg->cancel_moderada_umbral;

        $nivel = self::NIVEL_OK;
        $mensaje = null;
        if (!$liberacionVigente && $n >= $mod) {
            $nivel = self::NIVEL_MODERADA;
            $mensaje = 'Por política del efector, gestioná turnos presencialmente o por teléfono hasta regularizar la situación.';
        } elseif (!$liberacionVigente && $n >= $suave) {
            $nivel = self::NIVEL_SUAVE;
            $mensaje = 'Tenés varias cancelaciones recientes. Te pedimos que confirmes asistencia con anticipación cuando reserves.';
        }

        $result = [
            'nivel' => $nivel,
            'mensaje' => $mensaje,
            'cancelaciones_en_ventana' => (int) $n,
        ];

        $reader = new TurnoBehaviorProfileReadService();
        $contract = new TurnoBehaviorProfileContract();
        $windowProfile = $contract->nearestWindowDays($ventana);
        $metric = $reader->metric(
            (int) $idPersona,
            'CANCEL_PATIENT',
            PersonaTurnosPerfilMetrica::SCOPE_EFECTOR,
            (string) $idEfector,
            $windowProfile
        );
        $profile = $reader->currentProfile((int) $idPersona);
        $candidateCount = $metric !== null ? (int) $metric->numerator : null;
        $candidateNivel = null;
        if ($candidateCount !== null && !$liberacionVigente) {
            if ($candidateCount >= $mod) {
                $candidateNivel = self::NIVEL_MODERADA;
            } elseif ($candidateCount >= $suave) {
                $candidateNivel = self::NIVEL_SUAVE;
            } else {
                $candidateNivel = self::NIVEL_OK;
            }
        }
        $diffReason = 'candidate_unavailable';
        if ($candidateNivel !== null) {
            $diffReason = $candidateNivel === $nivel ? 'match' : 'nivel_mismatch';
        } elseif ($profile === null) {
            $diffReason = 'profile_missing';
        }
        $result['profile_candidate'] = [
            'mode' => 'shadow',
            'status' => $metric === null ? 'profile_missing_or_metric_absent' : 'available',
            'profile_id' => $profile !== null ? (int) $profile->id : null,
            'profile_contract_version' => $profile !== null ? (int) $profile->profile_contract_version : null,
            'cancelaciones_en_ventana' => $candidateCount,
            'window_days' => $windowProfile,
            'legacy_window_days' => $ventana,
            'scope_type' => PersonaTurnosPerfilMetrica::SCOPE_EFECTOR,
            'scope_id' => (string) $idEfector,
            'nivel' => $candidateNivel,
            'diff_reason' => $diffReason,
        ];

        return $result;
    }

    public function autogestionBloqueada($idPersona, $idEfector)
    {
        return $this->evaluarAutogestion($idPersona, $idEfector)['nivel'] === self::NIVEL_MODERADA;
    }
}
