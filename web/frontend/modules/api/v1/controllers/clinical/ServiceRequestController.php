<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Dto\ServiceRequestDto;
use common\components\Domain\Clinical\Service\CarePlanService;
use common\components\Domain\Clinical\Service\ServiceRequestService;
use common\models\Clinical\CarePlan;
use Yii;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * ServiceRequest de un encounter.
 *
 * GET  /api/v1/clinical/encounter/<encounterId>/service-requests
 * POST /api/v1/clinical/encounter/<encounterId>/service-requests
 */
class ServiceRequestController extends BaseController
{
    use ClinicalAccessTrait;

    private ServiceRequestService $service;
    private CarePlanService $carePlans;

    public function init()
    {
        parent::init();
        $this->carePlans = new CarePlanService();
        $this->service = new ServiceRequestService($this->carePlans);
    }

    public function actionIndex($encounterId)
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        $data = [];
        foreach ($this->service->listForEncounter($encounter->id) as $sr) {
            $data[] = ServiceRequestDto::fromModel($sr)->toArray();
        }

        return [
            'success' => true,
            'message' => 'Service requests del encounter',
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
        }

        try {
            $sr = $this->service->createFromApi($encounter, $carePlan, $body);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        }

        return [
            'success' => true,
            'message' => 'Service request creado',
            'data' => ServiceRequestDto::fromModel($sr)->toArray(),
        ];
    }
}
