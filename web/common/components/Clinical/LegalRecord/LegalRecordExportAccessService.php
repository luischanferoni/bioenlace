<?php

namespace common\components\Clinical\LegalRecord;

use common\components\Clinical\Enum\EncounterStatus;
use common\components\Core\Permission\Domain\EncounterDomainAccessService;
use common\models\Clinical\Encounter;
use common\models\Clinical\LegalRecordExportRequest;
use common\models\Person\Persona;
use Yii;

/**
 * Staff: puede solicitar expediente si tiene efector y vínculo clínico con el paciente.
 */
final class LegalRecordExportAccessService
{
    public function assertStaffCanRequest(int $subjectPersonaId, ?int $idEfector = null): void
    {
        $userId = (int) (Yii::$app->user->id ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Sesión de staff requerida.');
        }

        $requesterPersonaId = (int) Yii::$app->user->getIdPersona();
        if ($requesterPersonaId > 0 && $requesterPersonaId === $subjectPersonaId) {
            throw new \InvalidArgumentException(
                'El expediente legal no está disponible para autogestión del paciente. Usá el resumen de atención en la app paciente.'
            );
        }

        $persona = Persona::findOne(['id_persona' => $subjectPersonaId]);
        if ($persona === null) {
            throw new \InvalidArgumentException('Paciente no encontrado.');
        }

        $efectorId = $idEfector > 0 ? $idEfector : (int) Yii::$app->user->getIdEfector();
        if ($efectorId <= 0) {
            throw new \InvalidArgumentException(
                'Se requiere efector en sesión operativa o id_efector en la solicitud.'
            );
        }

        if (!$this->staffHasClinicalLinkAtEfector($subjectPersonaId, $efectorId)) {
            throw new \InvalidArgumentException(
                'No hay atenciones registradas de este paciente en el efector indicado.'
            );
        }
    }

    public function assertUserCanDownload(LegalRecordExportRequest $request): void
    {
        $userId = (int) (Yii::$app->user->id ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Sesión requerida.');
        }
        if ((int) $request->requested_by_user_id !== $userId) {
            throw new \InvalidArgumentException('Solo quien solicitó el expediente puede descargarlo.');
        }
    }

    private function staffHasClinicalLinkAtEfector(int $subjectPersonaId, int $efectorId): bool
    {
        $encounters = Encounter::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'efector_id' => $efectorId,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_AMB,
                'status' => EncounterStatus::FINISHED,
                'deleted_at' => null,
            ])
            ->limit(20)
            ->all();

        if ($encounters === []) {
            return false;
        }

        foreach ($encounters as $encounter) {
            if (EncounterDomainAccessService::canAccess($encounter)) {
                return true;
            }
        }

        return false;
    }
}
