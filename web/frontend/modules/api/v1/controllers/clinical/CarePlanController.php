<?php

namespace frontend\modules\api\v1\controllers\clinical;

use Yii;
use common\components\Clinical\Dto\CarePlanDto;
use common\components\Clinical\Service\CarePlanService;
use common\components\Clinical\Service\PatientActiveCarePlanQuery;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * CarePlan activos y ciclo de vida.
 *
 * GET  /api/v1/clinical/care-plans/active
 * GET  /api/v1/clinical/care-plans/<id>
 * POST /api/v1/clinical/care-plans/<id>/complete
 * POST /api/v1/clinical/care-plans/<id>/revoke
 */
class CarePlanController extends BaseController
{
    use ClinicalAccessTrait;

    private CarePlanService $carePlanService;
    private PatientActiveCarePlanQuery $activeQuery;

    public function init()
    {
        parent::init();
        $this->carePlanService = new CarePlanService();
        $this->activeQuery = new PatientActiveCarePlanQuery();
    }

    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);

        return $actions;
    }

    /**
     * Planes activos del paciente autenticado.
     */
    public function actionActive()
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError(
                'Solo pacientes autenticados pueden listar care plans activos (idPersona en sesión).',
                null,
                400
            );
        }

        $plans = $this->activeQuery->listActive($idPersona);
        $data = [];
        foreach ($plans as $plan) {
            $data[] = CarePlanDto::fromModel($plan)->toArray();
        }

        return [
            'success' => true,
            'message' => 'Care plans activos',
            'data' => $data,
        ];
    }

    public function actionView($id)
    {
        [$plan, $err] = $this->requireCarePlanAccess((int) $id);
        if ($err !== null) {
            return $err;
        }

        return [
            'success' => true,
            'message' => 'Care plan',
            'data' => CarePlanDto::fromModel($plan, true)->toArray(),
        ];
    }

    public function actionComplete($id)
    {
        [$plan, $err] = $this->requireCarePlanAccess((int) $id);
        if ($err !== null) {
            return $err;
        }

        try {
            $this->carePlanService->complete($plan);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        }

        return [
            'success' => true,
            'message' => 'Care plan completado',
            'data' => CarePlanDto::fromModel($plan)->toArray(),
        ];
    }

    public function actionRevoke($id)
    {
        [$plan, $err] = $this->requireCarePlanAccess((int) $id);
        if ($err !== null) {
            return $err;
        }

        try {
            $this->carePlanService->revoke($plan);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        }

        return [
            'success' => true,
            'message' => 'Care plan revocado',
            'data' => CarePlanDto::fromModel($plan)->toArray(),
        ];
    }
}
