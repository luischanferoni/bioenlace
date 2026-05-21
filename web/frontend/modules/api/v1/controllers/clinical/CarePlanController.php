<?php

namespace frontend\modules\api\v1\controllers\clinical;

use Yii;
use common\components\Clinical\Dto\CarePlanDto;
use common\components\Clinical\Service\CarePlanLifecycleService;
use common\components\Clinical\Service\CarePlanPresentationService;
use common\components\Clinical\Service\PatientActiveCarePlanQuery;
use common\components\Ui\UiScreenService;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * CarePlan activos y ciclo de vida.
 *
 * GET  /api/v1/clinical/care-plans/active
 * GET  /api/v1/clinical/care-plans/<id>
 * GET|POST /api/v1/clinical/care-plan/ver-tratamiento-paciente (UI JSON)
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
    private CarePlanPresentationService $presentation;

    public function init()
    {
        parent::init();
        $this->lifecycle = new CarePlanLifecycleService();
        $this->activeQuery = new PatientActiveCarePlanQuery();
        $this->presentation = new CarePlanPresentationService();
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
    /**
     * UI JSON: listado de care plans activos del paciente (asistente / móvil).
     *
     * @tags clinical, care-plan, paciente, ui_json
     * @keywords ver mi tratamiento, plan de tratamiento, care plan activo
     */
    public function actionVerTratamientoPaciente(): array
    {
        $req = Yii::$app->request;
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError(
                'Solo pacientes autenticados pueden ver tratamientos activos.',
                null,
                400
            );
        }

        $out = UiScreenService::handleScreen(
            'care-plan',
            'ver-tratamiento-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($idPersona): array {
                $planId = (int) ($post['care_plan_id'] ?? 0);
                if ($planId <= 0) {
                    throw new \InvalidArgumentException('Seleccioná un plan de tratamiento.');
                }
                $plan = null;
                foreach ($this->activeQuery->listActive($idPersona) as $p) {
                    if ((int) $p->id === $planId) {
                        $plan = $p;
                        break;
                    }
                }
                if ($plan === null) {
                    throw new \InvalidArgumentException('Plan no encontrado o no activo.');
                }

                return ['data' => $this->presentation->toPatientSummary($plan, true)];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->isGet) {
            $items = [];
            foreach ($this->activeQuery->listActive($idPersona) as $plan) {
                $summary = $this->presentation->toPatientSummary($plan, true);
                $lines = $summary['activitySummaries'] ?? [];
                $subtitle = is_array($lines) && $lines !== [] ? implode(' · ', array_slice($lines, 0, 2)) : '';
                $items[] = [
                    'id' => (string) $plan->id,
                    'name' => (string) ($summary['categoryLabel'] ?? $summary['category'] ?? 'Tratamiento'),
                    'label' => (string) ($summary['categoryLabel'] ?? 'Tratamiento'),
                    'subtitle' => $subtitle,
                    'meta' => [
                        'status' => $plan->status,
                        'category' => $plan->category,
                    ],
                ];
            }

            return UiScreenService::withListBlockItems($out, $items, 'planes');
        }

        return $out;
    }

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

        $includeActivities = Yii::$app->request->get('includeActivities', '1') !== '0';
        $plans = $this->activeQuery->listActive($idPersona);
        $data = [];
        foreach ($plans as $plan) {
            $data[] = $this->presentation->toPatientSummary($plan, $includeActivities);
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
