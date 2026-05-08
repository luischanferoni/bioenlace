<?php

namespace common\components\Services\Consulta;

use common\models\Consulta;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\RrhhEfector;
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
            if ($pes !== null) {
                $re = RrhhEfector::find()
                    ->where([
                        'id_persona' => $pes->id_persona,
                        'id_efector' => $pes->id_efector,
                        'deleted_at' => null,
                    ])
                    ->one();
                if ($re !== null && $idRrhhConsulta > 0 && (int) $re->id_rr_hh === $idRrhhConsulta) {
                    return true;
                }
            }
        }

        if ($idRrhhSesion > 0 && $idPesConsulta > 0) {
            $pesC = ProfesionalEfectorServicio::findOne(['id' => $idPesConsulta, 'deleted_at' => null]);
            if ($pesC !== null) {
                $re = RrhhEfector::find()
                    ->where([
                        'id_rr_hh' => $idRrhhSesion,
                        'id_efector' => $pesC->id_efector,
                        'id_persona' => $pesC->id_persona,
                        'deleted_at' => null,
                    ])
                    ->one();
                if ($re !== null) {
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
        $rrhhEfector = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhhC, 'deleted_at' => null])
            ->one();

        return $rrhhEfector !== null && (int) $rrhhEfector->id_persona === (int) $persona->id_persona;
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
        $rrhhEfector = RrhhEfector::find()
            ->where(['id_rr_hh' => $idRrhhC, 'deleted_at' => null])
            ->one();

        return $rrhhEfector !== null && (int) $rrhhEfector->id_persona === (int) $persona->id_persona;
    }
}
