<?php

namespace common\components\Services\Turnos;

use common\models\Turno;
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
     * @return array{nivel: string, mensaje: ?string, cancelaciones_en_ventana: int}
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

        if (PersonaEfectorAutogestionLiberacion::tieneLiberacionVigente(
            $idPersona,
            $idEfector,
            (int) $cfg->autogestion_liberacion_vigencia_dias
        )) {
            return ['nivel' => self::NIVEL_OK, 'mensaje' => null, 'cancelaciones_en_ventana' => (int) $n];
        }

        $suave = (int) $cfg->cancel_suave_umbral;
        $mod = (int) $cfg->cancel_moderada_umbral;

        if ($n < $suave) {
            return ['nivel' => self::NIVEL_OK, 'mensaje' => null, 'cancelaciones_en_ventana' => (int) $n];
        }
        if ($n < $mod) {
            return [
                'nivel' => self::NIVEL_SUAVE,
                'mensaje' => 'Tenés varias cancelaciones recientes. Te pedimos que confirmes asistencia con anticipación cuando reserves.',
                'cancelaciones_en_ventana' => (int) $n,
            ];
        }

        return [
            'nivel' => self::NIVEL_MODERADA,
            'mensaje' => 'Por política del efector, gestioná turnos presencialmente o por teléfono hasta regularizar la situación.',
            'cancelaciones_en_ventana' => (int) $n,
        ];
    }

    public function autogestionBloqueada($idPersona, $idEfector)
    {
        return $this->evaluarAutogestion($idPersona, $idEfector)['nivel'] === self::NIVEL_MODERADA;
    }
}
