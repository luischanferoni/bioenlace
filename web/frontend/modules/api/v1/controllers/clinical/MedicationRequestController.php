<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\CarePlan\Domain\Model\MedicationRequestDto;
use common\components\Domain\Clinical\CarePlan\Application\Service\CarePlanService;
use common\components\Domain\Clinical\CarePlan\Application\UseCase\ManageMedicationRequest;
use common\models\Clinical\CarePlan;
use Yii;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * MedicationRequest de un encounter.
 *
 * GET  /api/v1/clinical/encounter/<encounterId>/medication-requests
 * POST /api/v1/clinical/encounter/<encounterId>/medication-requests
 */
class MedicationRequestController extends BaseController
{
    use ClinicalAccessTrait;

    private ManageMedicationRequest $service;
    private CarePlanService $carePlans;

    public function init()
    {
        parent::init();
        $this->carePlans = new CarePlanService();
        $this->service = new ManageMedicationRequest($this->carePlans);
    }

    public function actionIndex($encounterId)
    {
        $encounter = $this->assertEncounterAccess((int) $encounterId);

        $data = [];
        foreach ($this->service->listForEncounter($encounter->id) as $mr) {
            $data[] = MedicationRequestDto::fromModel($mr)->toArray();
        }

        return $this->ok($data);
    }

    public function actionCreate($encounterId)
    {
        $encounter = $this->assertEncounterAccess((int) $encounterId);

        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $body = Yii::$app->request->post();
        }
        if (!is_array($body)) {
            return $this->fail('request_invalid');
        }

        $carePlan = null;
        $carePlanId = isset($body['care_plan_id']) ? (int) $body['care_plan_id'] : 0;
        if ($carePlanId > 0) {
            $carePlan = CarePlan::findOne($carePlanId);
            if ($carePlan === null || (int) $carePlan->subject_persona_id !== (int) $encounter->subject_persona_id) {
                return $this->fail('care_plan_subject_mismatch');
            }
        } elseif (!empty($body['attach_to_acute_plan'])) {
            $carePlan = $this->carePlans->createAcutePlanForEncounter(
                (int) $encounter->subject_persona_id,
                (int) $encounter->id
            );
        }

        try {
            $mr = $this->service->createFromApi($encounter, $carePlan, $body);
        } catch (\InvalidArgumentException $e) {
            return $this->fail('request_invalid', ['detail' => $e->getMessage()]);
        }

        return $this->ok(MedicationRequestDto::fromModel($mr)->toArray(), ['message' => 'Receta registrada']);
    }
}
