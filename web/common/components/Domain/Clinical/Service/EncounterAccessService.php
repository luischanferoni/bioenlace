<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Person\Representation\Service\PersonRepresentationAccessService;
use common\components\Domain\Scheduling\Service\ConsultaAsyncAccessService;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\Scheduling\Turno;
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

        $idPesSesionRaw = Yii::$app->user->getIdProfesionalEfectorServicio();
        $idPesSesion = $idPesSesionRaw !== null && $idPesSesionRaw !== '' ? (int) $idPesSesionRaw : 0;
        if ($idPesSesion > 0) {
            foreach (self::staffPesCandidatesForEncounter($encounter) as $idPesResource) {
                if (self::staffPesMatchesSession($idPesSesion, $idPesResource)) {
                    return true;
                }
            }
        }

        if (ConsultaAsyncAccessService::staffCanAccessAsyncEncounter($encounter)) {
            return true;
        }

        return false;
    }

    /**
     * PES del encounter y, si hay turno vinculado, el del appointment (motivos suelen crearse
     * con encounter sin PES o con PES desfasado respecto del turno de agenda).
     *
     * @return list<int>
     */
    private static function staffPesCandidatesForEncounter(Encounter $encounter): array
    {
        $out = [];
        $idPesEncounter = (int) ($encounter->id_profesional_efector_servicio ?? 0);
        if ($idPesEncounter > 0) {
            $out[] = $idPesEncounter;
        }

        $appointmentId = (int) ($encounter->appointment_id ?? 0);
        if ($appointmentId <= 0 && strtoupper(trim((string) ($encounter->parent_type ?? ''))) === Encounter::PARENT_TURNO) {
            $appointmentId = (int) ($encounter->parent_id ?? 0);
        }
        if ($appointmentId > 0) {
            $turno = Turno::find()
                ->andWhere(['id_turnos' => $appointmentId])
                ->one();
            $idPesTurno = $turno !== null ? (int) ($turno->id_profesional_efector_servicio ?? 0) : 0;
            if ($idPesTurno > 0 && !in_array($idPesTurno, $out, true)) {
                $out[] = $idPesTurno;
            }
        }

        return $out;
    }

    private static function staffPesMatchesSession(int $idPesSesion, int $idPesResource): bool
    {
        if ($idPesSesion <= 0 || $idPesResource <= 0) {
            return false;
        }
        if ($idPesSesion === $idPesResource) {
            return true;
        }
        $pesS = ProfesionalEfectorServicio::findOne(['id' => $idPesSesion, 'deleted_at' => null]);
        $pesR = ProfesionalEfectorServicio::findOne(['id' => $idPesResource, 'deleted_at' => null]);

        return $pesS !== null
            && $pesR !== null
            && (int) $pesS->id_persona === (int) $pesR->id_persona;
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
