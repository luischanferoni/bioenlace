<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Enum\CarePlanStatus;
use common\models\Clinical\CarePlan;
use yii\db\ActiveQuery;

final class PatientActiveCarePlanQuery
{
    public function forPersona(int $subjectPersonaId): ActiveQuery
    {
        return CarePlan::find()
            ->andWhere(['subject_persona_id' => $subjectPersonaId])
            ->andWhere(['status' => [CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD]])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['period_start' => SORT_DESC]);
    }

    /**
     * @return CarePlan[]
     */
    public function listActive(int $subjectPersonaId): array
    {
        return $this->forPersona($subjectPersonaId)->all();
    }
}
