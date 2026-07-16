<?php

namespace frontend\modules\api\v1\controllers\clinical;

use Yii;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\components\Domain\Person\Representation\Service\PersonRepresentationSubjectService;
use common\models\Person\PersonRelatedAuditLog;
use common\components\Domain\Clinical\CarePlan\Reminder\CarePlanReminderPreferenceService;
use common\components\Domain\Clinical\CarePlan\Reminder\CarePlanReminderScheduleBuilder;
use common\components\Domain\Clinical\Dto\CarePlanDto;
use common\components\Domain\Clinical\Service\CarePlanLifecycleService;
use common\components\Domain\Clinical\Service\CarePlanMedicationListService;
use common\components\Domain\Clinical\Service\CarePlanPresentationService;
use common\components\Domain\Clinical\CarePlan\CarePlanAdherenceStaffService;
use common\components\Domain\Clinical\Service\PatientActiveCarePlanQuery;
use common\components\Domain\Scheduling\Service\ConsultaAsyncSolicitudService;
use common\components\Platform\Ui\UiScreenService;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * CarePlan activos y ciclo de vida.
 *
 * GET  /api/v1/clinical/care-plans/active
 * GET  /api/v1/clinical/care-plans/<id>
 * GET|POST /api/v1/clinical/care-plan/ver-tratamiento-paciente (UI JSON)
 * GET|POST /api/v1/clinical/care-plan/medicamentos-como-paciente (UI JSON)
 * GET|POST /api/v1/clinical/care-plan/confirmar-renovacion-como-paciente (UI JSON)
 * POST /api/v1/clinical/care-plans/<id>/complete
 * POST /api/v1/clinical/care-plans/<id>/revoke
 * POST /api/v1/clinical/care-plans/<id>/hold
 * POST /api/v1/clinical/care-plans/<id>/activate
 * GET  /api/v1/clinical/care-plans/recordatorios-como-paciente
 * GET  /api/v1/clinical/care-plans/preferencias-recordatorios-como-paciente
 * PUT  /api/v1/clinical/care-plans/preferencias-recordatorios-como-paciente
 * GET  /api/v1/clinical/care-plans/adherencia-resumen-staff (UI JSON dashboard staff)
 */
class CarePlanController extends BaseController
{
    use ClinicalAccessTrait;

    private CarePlanLifecycleService $lifecycle;
    private PatientActiveCarePlanQuery $activeQuery;
    private CarePlanPresentationService $presentation;
    private CarePlanReminderScheduleBuilder $reminderSchedule;
    private CarePlanReminderPreferenceService $reminderPreferences;

    public function init()
    {
        parent::init();
        $this->lifecycle = new CarePlanLifecycleService();
        $this->activeQuery = new PatientActiveCarePlanQuery();
        $this->presentation = new CarePlanPresentationService();
        $this->reminderSchedule = new CarePlanReminderScheduleBuilder($this->activeQuery);
        $this->reminderPreferences = new CarePlanReminderPreferenceService();
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
        $params = array_merge($req->get(), $req->post());
        try {
            $subjectSvc = new PersonRepresentationSubjectService();
            $idPersona = $subjectSvc->resolveAndAuthorize($params, RepresentationPermission::CLINICAL_CARE_PLAN);
            $subjectSvc->auditDelegatedAction(PersonRelatedAuditLog::ACTION_CARE_PLAN_ACCESSED, $idPersona, []);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        } catch (\yii\web\ForbiddenHttpException $e) {
            return $this->clinicalError($e->getMessage(), null, 403);
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

            $out = UiScreenService::withListBlockItems($out, $items, 'planes');
            if ($items === [] && isset($out['blocks']) && is_array($out['blocks'])) {
                foreach ($out['blocks'] as $i => $block) {
                    if (!is_array($block) || ($block['id'] ?? '') !== 'planes') {
                        continue;
                    }
                    $block['selection'] = ['mode' => 'none'];
                    $out['blocks'][$i] = $block;
                    break;
                }
            }

            return $out;
        }

        return $out;
    }

    /**
     * UI JSON: medicación activa del CarePlan (paciente) para renovar / ajustar.
     *
     * GET|POST /api/v1/clinical/care-plan/medicamentos-como-paciente?care_plan_id=
     *
     * @tags clinical, care-plan, paciente, ui_json, medicacion
     */
    public function actionMedicamentosComoPaciente(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        try {
            $subjectSvc = new PersonRepresentationSubjectService();
            $idPersona = $subjectSvc->resolveAndAuthorize($params, RepresentationPermission::CLINICAL_CARE_PLAN);
            $subjectSvc->auditDelegatedAction(PersonRelatedAuditLog::ACTION_CARE_PLAN_ACCESSED, $idPersona, []);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        } catch (\yii\web\ForbiddenHttpException $e) {
            return $this->clinicalError($e->getMessage(), null, 403);
        }

        $carePlanId = (int) ($params['care_plan_id'] ?? 0);
        if ($carePlanId <= 0) {
            return $this->clinicalError('Seleccioná un plan de tratamiento.', null, 400);
        }

        $medSvc = new CarePlanMedicationListService();
        $plan = $medSvc->findActivePlanForPersona($carePlanId, $idPersona);
        if ($plan === null) {
            return $this->clinicalError('Plan no encontrado o no activo.', null, 404);
        }

        $out = UiScreenService::handleScreen(
            'care-plan',
            'medicamentos-como-paciente',
            $req->get(),
            $req->post(),
            static function (array $post): array {
                $raw = $post['medication_request_ids'] ?? '';
                if (is_array($raw)) {
                    $ids = array_values(array_filter(array_map('intval', $raw)));
                } else {
                    $ids = array_values(array_filter(array_map(
                        'intval',
                        preg_split('/\s*,\s*/', trim((string) $raw)) ?: []
                    )));
                }
                if ($ids === []) {
                    throw new \InvalidArgumentException('Seleccioná al menos un medicamento.');
                }

                return [
                    'data' => [
                        'medication_request_ids' => implode(',', $ids),
                        'care_plan_id' => (string) (int) ($post['care_plan_id'] ?? 0),
                    ],
                ];
            }
        );

        if (($out['kind'] ?? '') === 'ui_definition' && $req->isGet) {
            $items = $medSvc->listItemsForPlan($plan);
            $out = UiScreenService::withListBlockItems($out, $items, 'medicamentos');
            if ($items === [] && isset($out['blocks']) && is_array($out['blocks'])) {
                foreach ($out['blocks'] as $i => $block) {
                    if (!is_array($block) || ($block['id'] ?? '') !== 'medicamentos') {
                        continue;
                    }
                    $block['selection'] = ['mode' => 'none'];
                    $out['blocks'][$i] = $block;
                    break;
                }
            }

            return $out;
        }

        return $out;
    }

    /**
     * UI JSON: confirmar renovación de medicación (sin texto libre).
     *
     * GET|POST /api/v1/clinical/care-plan/confirmar-renovacion-como-paciente
     *
     * @tags clinical, care-plan, paciente, ui_json, medicacion
     */
    public function actionConfirmarRenovacionComoPaciente(): array
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());

        return UiScreenService::handleScreen(
            'care-plan',
            'confirmar-renovacion-como-paciente',
            $req->get(),
            $req->post(),
            function (array $post) use ($params): array {
                try {
                    $subjectSvc = new PersonRepresentationSubjectService();
                    $idPersona = $subjectSvc->resolveAndAuthorize(
                        array_merge($params, $post),
                        RepresentationPermission::CLINICAL_CARE_PLAN
                    );
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException($e->getMessage());
                } catch (\yii\web\ForbiddenHttpException $e) {
                    throw new \InvalidArgumentException($e->getMessage());
                }

                $merged = array_merge($params, $post, [
                    'intake_tipo' => 'seguimiento',
                    'seguimiento_necesidad' => 'renovar_medicacion',
                    'medicacion_operacion' => 'renovacion',
                ]);

                return (new ConsultaAsyncSolicitudService())
                    ->solicitarComoPaciente($idPersona, $merged);
            }
        );
    }

    /**
     * UI JSON: adherencia a planes activos del efector (staff).
     *
     * @tags clinical, care-plan, staff, ui_json
     */
    public function actionAdherenciaResumenStaff(): array
    {
        $req = Yii::$app->request;
        $idEfector = (int) Yii::$app->user->getIdEfector();
        if ($idEfector <= 0) {
            $idEfector = (int) ($req->get('id_efector') ?? $req->post('id_efector') ?? 0);
        }
        if ($idEfector <= 0) {
            return $this->clinicalError(
                'Se requiere contexto de efector (sesión operativa o id_efector).',
                null,
                400
            );
        }

        try {
            $resumen = (new CarePlanAdherenceStaffService())->resumen($idEfector);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        }

        $params = array_merge($req->get(), $req->post(), [
            'resumen_texto' => (string) ($resumen['resumen_texto'] ?? ''),
        ]);
        $out = UiScreenService::renderUiDefinition('care-plan', 'adherencia-resumen-staff', $params, null);

        $items = [];
        foreach ($resumen['planes'] as $row) {
            $pct = $row['adherencia_pct'] ?? null;
            $suffix = $pct !== null ? " · {$pct}%" : '';
            $items[] = [
                'id' => (string) ($row['care_plan_id'] ?? ''),
                'name' => ((string) ($row['paciente_nombre'] ?? 'Paciente')) . $suffix,
                'label' => (string) ($row['paciente_nombre'] ?? 'Paciente'),
                'subtitle' => (string) ($row['category_label'] ?? ''),
                'meta' => [
                    'actividades_total' => $row['actividades_total'] ?? 0,
                    'actividades_completadas' => $row['actividades_completadas'] ?? 0,
                    'adherencia_pct' => $pct,
                ],
            ];
        }

        $out['success'] = true;
        $out['data'] = $resumen;

        return UiScreenService::withListBlockItems($out, $items, 'planes');
    }

    /**
     * Agenda de recordatorios derivada de care plans activos (cliente programa alarmas locales).
     *
     * GET /api/v1/clinical/care-plans/recordatorios-como-paciente
     * Query opcional: care_plan_id
     */
    public function actionRecordatoriosComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError(
                'Solo pacientes autenticados pueden obtener recordatorios de tratamiento.',
                null,
                400
            );
        }

        $carePlanId = (int) Yii::$app->request->get('care_plan_id', 0);
        $filter = $carePlanId > 0 ? $carePlanId : null;
        if ($filter !== null) {
            [$plan, $err] = $this->requireCarePlanAccess($filter);
            if ($err !== null) {
                return $err;
            }
            if ((int) $plan->subject_persona_id !== $idPersona) {
                return $this->clinicalError('No tiene permiso para este plan.', null, 403);
            }
        }

        return [
            'success' => true,
            'message' => 'Agenda de recordatorios',
            'data' => $this->reminderSchedule->buildForPersona($idPersona, $filter),
        ];
    }

    /**
     * Preferencias de recordatorios (sincronización multi-dispositivo).
     *
     * GET /api/v1/clinical/care-plans/preferencias-recordatorios-como-paciente
     */
    public function actionPreferenciasRecordatoriosComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError(
                'Solo pacientes autenticados pueden consultar preferencias de recordatorios.',
                null,
                400
            );
        }

        return [
            'success' => true,
            'message' => 'Preferencias de recordatorios',
            'data' => $this->reminderPreferences->exportForPersona($idPersona),
        ];
    }

    /**
     * Reemplazo parcial de preferencias (última escritura gana por ámbito).
     *
     * PUT /api/v1/clinical/care-plans/preferencias-recordatorios-como-paciente
     */
    public function actionActualizarPreferenciasRecordatoriosComoPaciente(): array
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona <= 0) {
            return $this->clinicalError(
                'Solo pacientes autenticados pueden guardar preferencias de recordatorios.',
                null,
                400
            );
        }

        $body = Yii::$app->request->getBodyParams();
        if (empty($body)) {
            $body = Yii::$app->request->post();
        }
        if (!is_array($body)) {
            return $this->clinicalError('Cuerpo JSON inválido', null, 400);
        }

        try {
            $this->reminderPreferences->importForPersona($idPersona, $body);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        }

        return [
            'success' => true,
            'message' => 'Preferencias guardadas',
            'data' => $this->reminderPreferences->exportForPersona($idPersona),
        ];
    }

    public function actionActive()
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        try {
            $subjectSvc = new PersonRepresentationSubjectService();
            $idPersona = $subjectSvc->resolveAndAuthorize($params, RepresentationPermission::CLINICAL_CARE_PLAN);
            $subjectSvc->auditDelegatedAction(PersonRelatedAuditLog::ACTION_CARE_PLAN_ACCESSED, $idPersona, []);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        } catch (\yii\web\ForbiddenHttpException $e) {
            return $this->clinicalError($e->getMessage(), null, 403);
        }

        $includeActivities = $req->get('includeActivities', '1') !== '0';
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

        $idPersona = (int) Yii::$app->user->getIdPersona();
        $data = $idPersona > 0 && (int) $plan->subject_persona_id === $idPersona
            ? $this->presentation->toPatientSummary($plan, true, null)
            : CarePlanDto::fromModel($plan, true)->toArray();

        return [
            'success' => true,
            'message' => 'Care plan',
            'data' => $data,
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
