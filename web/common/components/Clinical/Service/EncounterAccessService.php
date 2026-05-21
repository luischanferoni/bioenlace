<?php

namespace common\components\Clinical\Service;

use common\models\Clinical\Encounter;
use common\models\Persona;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Autorización de acceso a un Encounter (reemplazo de ConsultaAccessService).
 */
final class EncounterAccessService
{
    public static function userCanAccessEncounterApi(Encounter $encounter): bool
    {
        if ((int) $encounter->subject_persona_id === (int) Yii::$app->user->getIdPersona()) {
            return true;
        }

        $idPesEncounter = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        $idPesSesionRaw = Yii::$app->user->getIdProfesionalEfectorServicio();
        $idPesSesion = $idPesSesionRaw !== null && $idPesSesionRaw !== '' ? (int) $idPesSesionRaw : 0;

        if ($idPesEncounter > 0 && $idPesSesion > 0 && $idPesEncounter === $idPesSesion) {
            return true;
        }
        if ($idPesSesion > 0 && $idPesEncounter > 0) {
            $pesS = ProfesionalEfectorServicio::findOne(['id' => $idPesSesion, 'deleted_at' => null]);
            $pesE = ProfesionalEfectorServicio::findOne(['id' => $idPesEncounter, 'deleted_at' => null]);
            if (
                $pesS !== null && $pesE !== null
                && (int) $pesS->id_persona === (int) $pesE->id_persona
            ) {
                return true;
            }
        }

        return false;
    }

    public static function userCanAccessEncounterWeb(Encounter $encounter): bool
    {
        $userId = Yii::$app->user->id;
        if (!$userId) {
            return false;
        }
        $persona = Persona::findOne(['id_user' => $userId]);
        if (!$persona) {
            return false;
        }
        if ((int) $encounter->subject_persona_id === (int) $persona->id_persona) {
            return true;
        }
        $idPes = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        if ($idPes > 0) {
            $pes = ProfesionalEfectorServicio::findOne(['id' => $idPes, 'deleted_at' => null]);

            return $pes !== null && (int) $pes->id_persona === (int) $persona->id_persona;
        }

        return false;
    }
}
