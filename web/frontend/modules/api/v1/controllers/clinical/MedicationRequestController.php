<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Dto\MedicationRequestDto;
use common\components\Clinical\Service\CarePlanService;
use common\components\Clinical\Service\MedicationRequestService;
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

    private MedicationRequestService $service;
    private CarePlanService $carePlans;

    public function init()
    {
        parent::init();
        $this->carePlans = new CarePlanService();
        $this->service = new MedicationRequestService($this->carePlans);
    }

    public function actionIndex($encounterId)
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        $data = [];
        foreach ($this->service->listForEncounter($encounter->id) as $mr) {
            $data[] = MedicationRequestDto::fromModel($mr)->toArray();
        }

        return [
            'success' => true,
            'message' => 'Medication requests del encounter',
            'data' => $data,
        ];
    }

    public function actionCreate($encounterId)
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $body = Yii::$app->request->post();
        }
        if (!is_array($body)) {
            return $this->clinicalError('Cuerpo JSON inválido', null, 400);
        }

        $carePlan = null;
        $carePlanId = isset($body['care_plan_id']) ? (int) $body['care_plan_id'] : 0;
        if ($carePlanId > 0) {
            $carePlan = CarePlan::findOne($carePlanId);
            if ($carePlan === null || (int) $carePlan->subject_persona_id !== (int) $encounter->subject_persona_id) {
                return $this->clinicalError('Care plan no encontrado o no corresponde al paciente del encounter', null, 400);
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
            return $this->clinicalError($e->getMessage(), null, 400);
        }

        return [
            'success' => true,
            'message' => 'Medication request creado',
            'data' => MedicationRequestDto::fromModel($mr)->toArray(),
        ];
    }
}
