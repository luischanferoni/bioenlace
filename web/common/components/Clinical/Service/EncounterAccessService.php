<?php

namespace common\components\Clinical\Service;

use common\components\Person\Representation\Service\PersonRepresentationAccessService;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use Yii;

/**
 * Autorización de acceso a un Encounter (reemplazo de ConsultaAccessService).
 */
final class EncounterAccessService
{
    public static function userCanAccessEncounterApi(Encounter $encounter, ?string $representationPermission = null): bool
    {
        $actorId = (int) Yii::$app->user->getIdPersona();
        $subjectId = (int) $encounter->subject_persona_id;

        if ($actorId > 0 && $subjectId === $actorId) {
            return true;
        }

        if ($representationPermission !== null && $actorId > 0 && $subjectId > 0) {
            if ((new PersonRepresentationAccessService())->canAct($actorId, $subjectId, $representationPermission)) {
                return true;
            }
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
