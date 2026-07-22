<?php

namespace common\components\Domain\Clinical\Service;

use common\models\Clinical\Condition;
use yii\db\ActiveQuery;

/**
 * Condiciones clínicas activas del paciente (universo compartido hub / home).
 */
final class PatientActiveConditionQuery
{
    /** Máximo tras dedupe (hub e inicio). */
    public const DEDUPE_LIMIT = 8;

    public function forPersona(int $subjectPersonaId): ActiveQuery
    {
        return Condition::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'deleted_at' => null,
            ])
            ->andWhere(['clinical_status' => ['ACTIVE', 'RECURRENCE', 'RELAPSE']])
            ->andWhere(['not in', 'verification_status', ['REFUTED', 'ENTERED_IN_ERROR']])
            ->orderBy(['recorded_date' => SORT_DESC, 'id' => SORT_DESC]);
    }

    /**
     * @return Condition[]
     */
    public function listActive(int $subjectPersonaId, int $limit = 100): array
    {
        if ($subjectPersonaId <= 0) {
            return [];
        }

        return $this->forPersona($subjectPersonaId)->limit($limit)->all();
    }
}
