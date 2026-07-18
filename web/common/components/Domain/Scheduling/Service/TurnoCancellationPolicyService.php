<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoBehaviorProfileReader;
use common\models\Scheduling\Turno;
use common\models\Scheduling\PersonaTurnosPerfilMetrica;
use common\models\EfectorTurnosConfig;
use common\models\PersonaEfectorAutogestionLiberacion;

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

        $reader = new TurnoBehaviorProfileReader();
        $metric = $reader->metric(
            (int) $idPersona,
            'CANCEL_PATIENT',
            PersonaTurnosPerfilMetrica::SCOPE_EFECTOR,
            (string) $idEfector,
            $ventana
        );
        $profile = $reader->currentProfile((int) $idPersona);
        $result['profile_candidate'] = [
            'mode' => 'shadow',
            'status' => $metric === null ? 'unavailable_or_unsupported_window' : 'available',
            'profile_id' => $profile !== null ? (int) $profile->id : null,
            'profile_contract_version' => $profile !== null ? (int) $profile->profile_contract_version : null,
            'cancelaciones_en_ventana' => $metric !== null ? (int) $metric->numerator : null,
            'window_days' => $ventana,
            'scope_type' => PersonaTurnosPerfilMetrica::SCOPE_EFECTOR,
            'scope_id' => (string) $idEfector,
        ];

        return $result;
    }

    public function autogestionBloqueada($idPersona, $idEfector)
    {
        return $this->evaluarAutogestion($idPersona, $idEfector)['nivel'] === self::NIVEL_MODERADA;
    }
}
