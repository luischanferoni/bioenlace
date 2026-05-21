<?php

namespace common\components\Clinical\Legacy;

use common\models\Consulta;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Autorización de acceso a una consulta (chat / motivos): paciente o profesional asignado.
 * Sin HTTP: usa solo identidad en sesión.
 */
final class ConsultaAccessService
{
    /**
     * Identidad API v1: {@see \frontend\modules\api\v1\components\JsonHttpBearerAuth}, {@see \frontend\components\ApiUser}.
     */
    public static function userCanAccessConsultaApi(Consulta $consulta): bool
    {
        if ((int) $consulta->id_persona === (int) Yii::$app->user->getIdPersona()) {
            return true;
        }

        $idPesConsulta = (int) ($consulta->id_profesional_efector_servicio ?? 0);
        $idPesSesionRaw = Yii::$app->user->getIdProfesionalEfectorServicio();
        $idPesSesion = $idPesSesionRaw !== null && $idPesSesionRaw !== '' ? (int) $idPesSesionRaw : 0;
        if ($idPesSesion <= 0) {
            $rh = Yii::$app->user->getIdProfesionalEfectorServicio();
            $idPesSesion = $rh !== null && $rh !== '' ? (int) $rh : 0;
        }
        if ($idPesConsulta > 0 && $idPesSesion > 0 && $idPesConsulta === $idPesSesion) {
            return true;
        }
        if ($idPesSesion > 0 && $idPesConsulta > 0) {
            $pesS = ProfesionalEfectorServicio::findOne(['id' => $idPesSesion, 'deleted_at' => null]);
            $pesC = ProfesionalEfectorServicio::findOne(['id' => $idPesConsulta, 'deleted_at' => null]);
            if (
                $pesS !== null && $pesC !== null
                && (int) $pesS->id_persona === (int) $pesC->id_persona
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Usuario web Yii (`users` + `personas`).
     */
    public static function userCanAccessConsultaWeb(Consulta $consulta): bool
    {
        $userId = Yii::$app->user->id;
        if (!$userId) {
            return false;
        }
        $persona = Persona::findOne(['id_user' => $userId]);
        if (!$persona) {
            return false;
        }
        if ((int) $consulta->id_persona === (int) $persona->id_persona) {
            return true;
        }
        $idPesC = (int) ($consulta->id_profesional_efector_servicio ?? 0);
        if ($idPesC > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPesC, 'deleted_at' => null]);
            if ($pes !== null && (int) $pes->id_persona === (int) $persona->id_persona) {
                return true;
            }
        }

        return false;
    }

    /**
     * Usuario web es el profesional asignado a la consulta (no el paciente).
     * Para acciones de edición/clonado donde solo debe operar el médico.
     */
    public static function userIsProfesionalAsignadoConsultaWeb(Consulta $consulta): bool
    {
        $userId = Yii::$app->user->id;
        if (!$userId) {
            return false;
        }
        $persona = Persona::findOne(['id_user' => $userId]);
        if (!$persona) {
            return false;
        }
        if ((int) $consulta->id_persona === (int) $persona->id_persona) {
            return false;
        }
        $idPesC = (int) ($consulta->id_profesional_efector_servicio ?? 0);
        if ($idPesC <= 0) {
            return false;
        }
        $pes = ProfesionalEfectorServicio::findOne(['id' => $idPesC, 'deleted_at' => null]);

        return $pes !== null && (int) $pes->id_persona === (int) $persona->id_persona;
    }
}
