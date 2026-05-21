<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\models\Clinical\Condition;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * Condition (diagnósticos) de un encounter.
 *
 * GET /api/v1/clinical/encounter/<encounterId>/conditions
 */
class ConditionController extends BaseController
{
    use ClinicalAccessTrait;

    public function actionIndex($encounterId)
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        $rows = Condition::find()
            ->where(['encounter_id' => $encounter->id])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'resourceType' => 'Condition',
                'id' => (int) $row->id,
                'encounterId' => (int) $row->encounter_id,
                'code' => $row->code,
                'display' => $row->display,
                'clinicalStatus' => $row->clinical_status,
                'verificationStatus' => $row->verification_status,
            ];
        }

        return [
            'success' => true,
            'message' => 'Condiciones del encounter',
            'data' => $data,
        ];
    }
}
