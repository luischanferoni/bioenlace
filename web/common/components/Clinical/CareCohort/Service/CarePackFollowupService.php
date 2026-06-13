<?php

namespace common\components\Clinical\CareCohort\Service;

use common\components\Clinical\CareCohort\Presentation\CareEducationModuleResolver;
use common\components\Clinical\CareCohort\Presentation\CarePackFollowupPresenter;
use common\components\Core\Permission\Domain\EncounterDomainAccessService;
use common\components\Person\Representation\Enum\RepresentationPermission;
use common\components\Person\Representation\Service\PersonRepresentationSubjectService;
use common\models\Clinical\CareCohortPack;
use common\models\Person\PersonRelatedAuditLog;
use common\models\Clinical\CareFollowupResponse;
use common\models\Clinical\CareFollowupTouchpointQueue;
use common\models\Clinical\Encounter;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

final class CarePackFollowupService
{
    private CarePackFollowupPresenter $presenter;
    private CareEducationModuleResolver $educationResolver;

    public function __construct(
        ?CarePackFollowupPresenter $presenter = null,
        ?CareEducationModuleResolver $educationResolver = null
    ) {
        $this->presenter = $presenter ?? new CarePackFollowupPresenter();
        $this->educationResolver = $educationResolver ?? new CareEducationModuleResolver();
    }

    /**
     * @param array<string, mixed> $params touchpoint_id | encounter_id + touchpoint_key
     * @return array<string, mixed>
     */
    public function renderFollowup(array $params): array
    {
        if (!CarePackConfig::isEnabled()) {
            throw new NotFoundHttpException('Seguimiento por cohorte no habilitado.');
        }

        $queue = $this->resolveTouchpointQueue($params);
        $this->assertPatientAccess($queue);

        $existing = CareFollowupResponse::findOne(['touchpoint_queue_id' => (int) $queue->id]);
        if ($existing !== null) {
            return $this->presenter->buildSubmittedUi(
                (int) $queue->id,
                (int) $queue->encounter_id,
                (string) $queue->title
            );
        }

        $educationModules = $this->resolveEducationModules($queue);

        return $this->presenter->buildUiJson(
            [
                'title' => $queue->title,
                'purpose' => $queue->purpose,
                'form_kind' => $queue->form_kind,
                'touchpoint_key' => $queue->touchpoint_key,
            ],
            (int) $queue->id,
            (int) $queue->encounter_id,
            $educationModules
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function submitResponses(array $body): array
    {
        if (!CarePackConfig::isEnabled()) {
            throw new NotFoundHttpException('Seguimiento por cohorte no habilitado.');
        }

        $queue = $this->resolveTouchpointQueue($body);
        $this->assertPatientAccess($queue);

        if (CareFollowupResponse::find()->where(['touchpoint_queue_id' => (int) $queue->id])->exists()) {
            return [
                'kind' => 'ui_submit_result',
                'success' => true,
                'data' => [
                    'mensaje' => 'La evolución ya estaba registrada.',
                    'touchpoint_id' => (int) $queue->id,
                    'encounter_id' => (int) $queue->encounter_id,
                ],
            ];
        }

        $answers = $this->extractAnswers($body, (string) $queue->form_kind);
        $validationError = $this->validateRequired($answers, (string) $queue->form_kind);
        if ($validationError !== null) {
            return [
                'kind' => 'ui_submit_result',
                'success' => false,
                'message' => $validationError,
                'errors' => ['_form' => $validationError],
            ];
        }

        $now = date('Y-m-d H:i:s');
        $row = new CareFollowupResponse();
        $row->touchpoint_queue_id = (int) $queue->id;
        $row->encounter_id = (int) $queue->encounter_id;
        $row->subject_persona_id = (int) $queue->subject_persona_id;
        $row->touchpoint_key = (string) $queue->touchpoint_key;
        $row->answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE);
        $row->submitted_at = $now;
        $row->created_at = $now;
        if (!$row->save()) {
            return [
                'kind' => 'ui_submit_result',
                'success' => false,
                'message' => 'No se pudo guardar la evolución.',
                'errors' => $row->getErrors(),
            ];
        }

        $queue->estado = CareFollowupTouchpointQueue::ESTADO_COMPLETADA;
        $queue->updated_at = $now;
        $queue->save(false);

        $encounter = Encounter::findOne(['id' => (int) $queue->encounter_id, 'deleted_at' => null]);
        if ($encounter !== null) {
            (new PersonRepresentationSubjectService())->auditDelegatedAction(
                PersonRelatedAuditLog::ACTION_CARE_PACK_FOLLOWUP,
                (int) $encounter->subject_persona_id,
                [
                    'encounter_id' => (int) $queue->encounter_id,
                    'touchpoint_id' => (int) $queue->id,
                ]
            );
        }

        $this->evaluateWorseningSignal($queue, $answers);

        return [
            'kind' => 'ui_submit_result',
            'success' => true,
            'data' => [
                'mensaje' => 'Gracias. Registramos tu evolución.',
                'touchpoint_id' => (int) $queue->id,
                'encounter_id' => (int) $queue->encounter_id,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveTouchpointQueue(array $params): CareFollowupTouchpointQueue
    {
        $touchpointId = (int) ($params['touchpoint_id'] ?? $params['id'] ?? 0);
        if ($touchpointId > 0) {
            $queue = CareFollowupTouchpointQueue::findOne(['id' => $touchpointId]);
            if ($queue instanceof CareFollowupTouchpointQueue) {
                return $queue;
            }
            throw new NotFoundHttpException('Touchpoint no encontrado.');
        }

        $encounterId = (int) ($params['encounter_id'] ?? 0);
        $touchpointKey = trim((string) ($params['touchpoint_key'] ?? ''));
        if ($encounterId <= 0 || $touchpointKey === '') {
            throw new \InvalidArgumentException('Indicá touchpoint_id o encounter_id + touchpoint_key.');
        }

        $queue = CareFollowupTouchpointQueue::findOne([
            'encounter_id' => $encounterId,
            'touchpoint_key' => $touchpointKey,
        ]);
        if ($queue instanceof CareFollowupTouchpointQueue) {
            return $queue;
        }

        throw new NotFoundHttpException('Touchpoint no encontrado.');
    }

    private function assertPatientAccess(CareFollowupTouchpointQueue $queue): void
    {
        $encounter = Encounter::findOne(['id' => (int) $queue->encounter_id, 'deleted_at' => null]);
        if ($encounter === null) {
            throw new NotFoundHttpException('Encounter no encontrado.');
        }

        $subjectId = (int) $encounter->subject_persona_id;
        (new PersonRepresentationSubjectService())->assertCanAct(
            $subjectId,
            RepresentationPermission::CLINICAL_CARE_PACK_ASSISTANCE
        );
        if (!EncounterDomainAccessService::canAccess(
            $encounter,
            'Encounter.access',
            RepresentationPermission::CLINICAL_CARE_PACK_ASSISTANCE
        )) {
            throw new ForbiddenHttpException('No tiene permiso para este encounter.');
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveEducationModules(CareFollowupTouchpointQueue $queue): array
    {
        $packId = (int) ($queue->education_pack_id ?? 0);
        if ($packId <= 0) {
            return [];
        }

        $pack = CareCohortPack::findOne(['id' => $packId]);
        if (!$pack instanceof CareCohortPack) {
            return [];
        }

        return $this->educationResolver->resolveModules(
            $pack->getContentArray(),
            $queue->getEducationRefsArray()
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function extractAnswers(array $body, string $formKind): array
    {
        $fields = $this->fieldNamesForFormKind($formKind);
        $answers = [];
        foreach ($fields as $name) {
            if (array_key_exists($name, $body)) {
                $answers[$name] = $body[$name];
            }
        }

        return $answers;
    }

    /**
     * @param array<string, mixed> $answers
     */
    private function validateRequired(array $answers, string $formKind): ?string
    {
        $required = $this->requiredFieldsForFormKind($formKind);
        foreach ($required as $name) {
            $val = trim((string) ($answers[$name] ?? ''));
            if ($val === '') {
                return 'Completá todos los campos obligatorios.';
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function fieldNamesForFormKind(string $formKind): array
    {
        switch (strtolower($formKind)) {
            case 'adherence':
                return ['tomo_medicacion', 'observaciones'];
            case 'symptoms':
                return ['sintomas_actuales', 'intensidad'];
            case 'evolution_short':
            default:
                return ['sintomas_evolucion', 'comparacion'];
        }
    }

    /**
     * @return list<string>
     */
    private function requiredFieldsForFormKind(string $formKind): array
    {
        switch (strtolower($formKind)) {
            case 'adherence':
                return ['tomo_medicacion'];
            case 'symptoms':
                return ['sintomas_actuales', 'intensidad'];
            case 'evolution_short':
            default:
                return ['sintomas_evolucion', 'comparacion'];
        }
    }

    /**
     * @param array<string, mixed> $answers
     */
    private function evaluateWorseningSignal(CareFollowupTouchpointQueue $queue, array $answers): void
    {
        $worsening = false;
        $comparacion = strtolower(trim((string) ($answers['comparacion'] ?? '')));
        if ($comparacion === 'peor') {
            $worsening = true;
        }
        $intensidad = (int) ($answers['intensidad'] ?? 0);
        if ($intensidad >= 8) {
            $worsening = true;
        }

        if ($worsening) {
            Yii::info(
                'Care followup worsening signal encounter=' . (int) $queue->encounter_id
                . ' touchpoint=' . (int) $queue->id,
                'care-cohort-followup'
            );
        }
    }
}
