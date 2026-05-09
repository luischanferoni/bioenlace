<?php

namespace common\components\Services\Consulta;

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

        $idRrhhConsulta = (int) $consulta->id_rr_hh;
        $idRrhhSesion = (int) Yii::$app->user->getIdRecursoHumano();
        if ($idRrhhConsulta > 0 && $idRrhhSesion > 0 && $idRrhhConsulta === $idRrhhSesion) {
            return true;
        }

        $pesSesionRaw = Yii::$app->user->getIdProfesionalEfectorServicio();
        $idPesSesion = $pesSesionRaw !== null && $pesSesionRaw !== '' ? (int) $pesSesionRaw : 0;
        $idPesConsulta = (int) ($consulta->id_profesional_efector_servicio ?? 0);
        if ($idPesSesion > 0 && $idPesConsulta > 0 && $idPesSesion === $idPesConsulta) {
            return true;
        }

        if ($idPesSesion > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPesSesion, 'deleted_at' => null]);
            if ($pes !== null && $idRrhhConsulta > 0) {
                $idRrhhPersona = ProfesionalEfectorServicio::resolveIdRrhhForPersona((int) $pes->id_persona);
                if ($idRrhhPersona > 0 && $idRrhhPersona === $idRrhhConsulta) {
                    return true;
                }
            }
        }

        if ($idRrhhSesion > 0 && $idPesConsulta > 0) {
            $pesC = ProfesionalEfectorServicio::findOne(['id' => $idPesConsulta, 'deleted_at' => null]);
            if ($pesC !== null) {
                $idRrhhPersonaC = ProfesionalEfectorServicio::resolveIdRrhhForPersona((int) $pesC->id_persona);
                if ($idRrhhPersonaC > 0 && $idRrhhPersonaC === $idRrhhSesion) {
                    return true;
                }
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
        $idRrhhC = (int) $consulta->id_rr_hh;
        if ($idRrhhC <= 0) {
            return false;
        }
        $idPersonaConsulta = ProfesionalEfectorServicio::resolveIdPersonaFromIdRrhh($idRrhhC);

        return $idPersonaConsulta !== null && (int) $idPersonaConsulta === (int) $persona->id_persona;
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
        if ($idPesC > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPesC, 'deleted_at' => null]);
            if ($pes !== null && (int) $pes->id_persona === (int) $persona->id_persona) {
                return true;
            }
        }
        $idRrhhC = (int) $consulta->id_rr_hh;
        if ($idRrhhC <= 0) {
            return false;
        }
        $idPersonaConsulta = ProfesionalEfectorServicio::resolveIdPersonaFromIdRrhh($idRrhhC);

        return $idPersonaConsulta !== null && (int) $idPersonaConsulta === (int) $persona->id_persona;
    }
}
