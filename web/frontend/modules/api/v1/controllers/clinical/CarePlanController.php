<?php

namespace frontend\modules\api\v1\controllers\clinical;

use Yii;
use common\components\Clinical\Dto\CarePlanDto;
use common\components\Clinical\Service\CarePlanLifecycleService;
use common\components\Clinical\Service\PatientActiveCarePlanQuery;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * CarePlan activos y ciclo de vida.
 *
 * GET  /api/v1/clinical/care-plans/active
 * GET  /api/v1/clinical/care-plans/<id>
 * POST /api/v1/clinical/care-plans/<id>/complete
 * POST /api/v1/clinical/care-plans/<id>/revoke
 * POST /api/v1/clinical/care-plans/<id>/hold
 * POST /api/v1/clinical/care-plans/<id>/activate
 */
class CarePlanController extends BaseController
{
    use ClinicalAccessTrait;

    private CarePlanLifecycleService $lifecycle;
    private PatientActiveCarePlanQuery $activeQuery;

    public function init()
    {
        parent::init();
        $this->lifecycle = new CarePlanLifecycleService();
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
        return $this->transition((int) $id, 'complete');
    }

    public function actionRevoke($id)
    {
        return $this->transition((int) $id, 'revoke');
    }

    public function actionHold($id)
    {
        return $this->transition((int) $id, 'hold');
    }

    public function actionActivate($id)
    {
        return $this->transition((int) $id, 'activate');
    }

    private function transition(int $id, string $operation): array
    {
        [$plan, $err] = $this->requireCarePlanAccess($id);
        if ($err !== null) {
            return $err;
        }

        try {
            switch ($operation) {
                case 'complete':
                    $plan = $this->lifecycle->complete($plan);
                    $message = 'Care plan completado';
                    break;
                case 'revoke':
                    $plan = $this->lifecycle->revoke($plan);
                    $message = 'Care plan revocado';
                    break;
                case 'hold':
                    $plan = $this->lifecycle->hold($plan);
                    $message = 'Care plan en pausa';
                    break;
                case 'activate':
                    $plan = $this->lifecycle->activate($plan);
                    $message = 'Care plan activado';
                    break;
                default:
                    return $this->clinicalError('Operación no soportada', null, 400);
            }
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        }

        return [
            'success' => true,
            'message' => $message,
            'data' => CarePlanDto::fromModel($plan)->toArray(),
        ];
    }
}
