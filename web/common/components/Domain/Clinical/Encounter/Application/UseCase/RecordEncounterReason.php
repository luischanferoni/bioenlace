<?php

namespace common\components\Domain\Clinical\Encounter\Application\UseCase;

use common\components\Domain\Clinical\Encounter\Domain\ConditionClinicalStatus;
use common\components\Domain\Clinical\Encounter\Domain\ConditionDiagnosisRole;
use common\components\Domain\Clinical\Encounter\Domain\ConditionVerificationStatus;
use common\components\Domain\Clinical\Encounter\Domain\Model\ChiefComplaintReason;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Motivos de consulta del Encounter → Condition con rol {@see ConditionDiagnosisRole::CHIEF_COMPLAINT}.
 */
final class RecordEncounterReason
{
    /** @deprecated Usar {@see ChiefComplaintReason::UNCODED_SYSTEM} */
    public const UNCODED_SYSTEM = ChiefComplaintReason::UNCODED_SYSTEM;

    /**
     * @return Condition[]
     */
    public function listForEncounter(Encounter $encounter): array
    {
        return Condition::find()
            ->where([
                'encounter_id' => (int) $encounter->id,
                'diagnosis_role' => ConditionDiagnosisRole::CHIEF_COMPLAINT,
                'deleted_at' => null,
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all();
    }

    public function hasReasons(Encounter $encounter): bool
    {
        return Condition::find()
            ->where([
                'encounter_id' => (int) $encounter->id,
                'diagnosis_role' => ConditionDiagnosisRole::CHIEF_COMPLAINT,
                'deleted_at' => null,
            ])
            ->exists();
    }

    /**
     * Texto consolidado para prompts / banners (no es columna de BD).
     */
    public function displayText(Encounter $encounter): string
    {
        $parts = [];
        foreach ($this->listForEncounter($encounter) as $c) {
            $label = trim((string) ($c->display ?: $c->note ?: $c->code));
            if ($label !== '') {
                $parts[] = $label;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Reemplaza todos los motivos CC del encounter.
     *
     * @param list<string|array<string, mixed>> $rows texto o filas {texto|display|termino, codigo?, code_system?}
     */
    public function replaceReasons(Encounter $encounter, array $rows): void
    {
        $this->softDeleteExisting((int) $encounter->id);

        foreach ($rows as $row) {
            $reason = ChiefComplaintReason::fromInput($row);
            if ($reason === null) {
                continue;
            }
            $normalized = $reason->toPersistenceArray();
            $condition = new Condition();
            $condition->encounter_id = (int) $encounter->id;
            $condition->subject_persona_id = (int) $encounter->subject_persona_id;
            $condition->code = $normalized['code'];
            $condition->code_system = $normalized['code_system'];
            $condition->display = $normalized['display'];
            $condition->note = $normalized['note'];
            $condition->clinical_status = ConditionClinicalStatus::ACTIVE;
            $condition->verification_status = ConditionVerificationStatus::UNCONFIRMED;
            $condition->diagnosis_role = ConditionDiagnosisRole::CHIEF_COMPLAINT;
            $condition->recorded_date = date('Y-m-d H:i:s');
            if (!$condition->save(false)) {
                Yii::error(
                    'RecordEncounterReason: no se pudo guardar motivo encounter=' . $encounter->id,
                    'encounter-reason'
                );
            }
        }
    }

    private function softDeleteExisting(int $encounterId): void
    {
        $now = date('Y-m-d H:i:s');
        $userId = Yii::$app->user->id ?? null;
        Condition::updateAll(
            [
                'deleted_at' => $now,
                'deleted_by' => $userId,
                'updated_at' => $now,
            ],
            [
                'encounter_id' => $encounterId,
                'diagnosis_role' => ConditionDiagnosisRole::CHIEF_COMPLAINT,
                'deleted_at' => null,
            ]
        );
    }
}
