<?php

namespace common\components\Clinical\CareCohort\Service;

use common\components\Clinical\CareCohort\CohortKeyBuilder;
use common\components\Clinical\CareCohort\Enum\CarePackType;
use common\components\Clinical\CareCohort\Presentation\CarePackAssistancePresenter;
use common\components\Clinical\Service\Authorization\EncounterAccessService;
use common\components\Clinical\Service\EncounterAppointmentReasonLookupService;
use common\components\Person\Representation\Enum\RepresentationPermission;
use common\components\Person\Representation\Service\PersonRepresentationSubjectService;
use common\models\Person\PersonRelatedAuditLog;
use common\models\Clinical\CareAssistanceResponse;
use common\models\Clinical\CareCohortPack;
use common\models\Clinical\CareEncounterPack;
use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

final class CarePackAssistanceService
{
    private CarePackRepository $repository;
    private CareEncounterOrchestrator $orchestrator;
    private CarePackAssistancePresenter $presenter;
    private EncounterAppointmentReasonLookupService $lookup;

    public function __construct(
        ?CarePackRepository $repository = null,
        ?CareEncounterOrchestrator $orchestrator = null,
        ?CarePackAssistancePresenter $presenter = null,
        ?EncounterAppointmentReasonLookupService $lookup = null
    ) {
        $this->repository = $repository ?? new CarePackRepository();
        $this->orchestrator = $orchestrator ?? new CareEncounterOrchestrator();
        $this->presenter = $presenter ?? new CarePackAssistancePresenter();
        $this->lookup = $lookup ?? new EncounterAppointmentReasonLookupService();
    }

    /**
     * @param array<string, mixed> $params encounter_id | turno_id
     * @return array<string, mixed>
     */
    public function renderAssistance(array $params): array
    {
        if (!CarePackConfig::isEnabled()) {
            throw new NotFoundHttpException('Asistencia por cohorte no habilitada.');
        }

        $encounter = $this->resolveEncounter($params);
        $this->assertPatientAccess($encounter, $params);

        $existing = CareAssistanceResponse::findOne(['encounter_id' => (int) $encounter->id]);
        if ($existing !== null) {
            return $this->presenter->buildSubmittedUi((int) $encounter->id);
        }

        $pack = $this->resolveAssistancePack($encounter);
        if ($pack === null) {
            return $this->presenter->buildPendingUi((int) $encounter->id);
        }

        $content = $pack->getContentArray();
        if ($content === null) {
            return $this->presenter->buildPendingUi((int) $encounter->id);
        }

        return $this->presenter->buildUiJson(
            $content,
            (int) $encounter->id,
            (int) $pack->id
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function submitResponses(array $body): array
    {
        if (!CarePackConfig::isEnabled()) {
            throw new NotFoundHttpException('Asistencia por cohorte no habilitada.');
        }

        $encounter = $this->resolveEncounter($body);
        $this->assertPatientAccess($encounter, $body);

        if (CareAssistanceResponse::find()->where(['encounter_id' => (int) $encounter->id])->exists()) {
            return [
                'kind' => 'ui_submit_result',
                'success' => true,
                'data' => [
                    'mensaje' => 'Las respuestas ya estaban registradas.',
                    'encounter_id' => (int) $encounter->id,
                ],
            ];
        }

        $packId = (int) ($body['pack_id'] ?? 0);
        $pack = $this->resolveAssistancePack($encounter);
        if ($pack === null || ($packId > 0 && (int) $pack->id !== $packId)) {
            return [
                'kind' => 'ui_submit_result',
                'success' => false,
                'message' => 'El cuestionario aún no está listo. Intentá de nuevo en unos segundos.',
                'errors' => ['_form' => 'pack_pending'],
            ];
        }

        $content = $pack->getContentArray() ?? [];
        $answers = $this->extractAnswers($body, $content);
        $validationError = $this->validateRequired($answers, $content);
        if ($validationError !== null) {
            return [
                'kind' => 'ui_submit_result',
                'success' => false,
                'message' => $validationError,
                'errors' => ['_form' => $validationError],
            ];
        }

        $delta = (new CareAssistanceDeltaEvaluator())->needsAdaptation($answers, $content);
        $now = date('Y-m-d H:i:s');

        $row = new CareAssistanceResponse();
        $row->encounter_id = (int) $encounter->id;
        $row->subject_persona_id = (int) $encounter->subject_persona_id;
        $row->pack_id = (int) $pack->id;
        $row->answers_json = json_encode($answers, JSON_UNESCAPED_UNICODE);
        $row->delta_requested = $delta;
        $row->submitted_at = $now;
        $row->created_at = $now;
        $row->updated_at = $now;
        if (!$row->save()) {
            return [
                'kind' => 'ui_submit_result',
                'success' => false,
                'message' => 'No se pudieron guardar las respuestas.',
                'errors' => $row->getErrors(),
            ];
        }

        if ($delta) {
            (new CarePackDeltaAdaptService($this->repository))->requestAssistanceDelta(
                $encounter,
                $pack,
                $answers
            );
        }

        return [
            'kind' => 'ui_submit_result',
            'success' => true,
            'data' => [
                'mensaje' => 'Gracias. Registramos tus respuestas para la consulta.',
                'encounter_id' => (int) $encounter->id,
                'pack_id' => (int) $pack->id,
                'delta_requested' => $delta,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveEncounter(array $params): Encounter
    {
        $encounterId = (int) ($params['encounter_id'] ?? $params['consulta_id'] ?? 0);
        if ($encounterId <= 0) {
            $turnoId = (int) ($params['turno_id'] ?? $params['id_turno'] ?? 0);
            if ($turnoId > 0) {
                $encounterId = (int) ($this->lookup->encounterIdParaTurno($turnoId) ?? 0);
            }
        }

        if ($encounterId <= 0) {
            throw new \InvalidArgumentException('Indicá encounter_id o turno_id.');
        }

        $encounter = Encounter::findOne(['id' => $encounterId, 'deleted_at' => null]);
        if ($encounter === null) {
            throw new NotFoundHttpException('Encounter no encontrado.');
        }

        return $encounter;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function assertPatientAccess(Encounter $encounter, array $params = []): void
    {
        unset($params);
        $subjectSvc = new PersonRepresentationSubjectService();
        $subjectId = (int) $encounter->subject_persona_id;
        $subjectSvc->assertCanAct($subjectId, RepresentationPermission::CLINICAL_CARE_PACK_ASSISTANCE);
        if (!EncounterAccessService::canAccess(
            $encounter,
            'Encounter.access',
            RepresentationPermission::CLINICAL_CARE_PACK_ASSISTANCE
        )) {
            throw new ForbiddenHttpException('No tiene permiso para este encounter.');
        }
        $subjectSvc->auditDelegatedAction(
            PersonRelatedAuditLog::ACTION_CARE_PACK_ASSISTANCE,
            $subjectId,
            ['encounter_id' => (int) $encounter->id]
        );
    }

    private function resolveAssistancePack(Encounter $encounter): ?CareCohortPack
    {
        $binding = $this->repository->findEncounterBinding((int) $encounter->id);
        if ($binding === null) {
            $this->orchestrator->onEncounterEnsured($encounter);
            $binding = $this->repository->findEncounterBinding((int) $encounter->id);
        }

        if ($binding !== null && (int) $binding->assistance_pack_id > 0) {
            $pack = CareCohortPack::findOne(['id' => (int) $binding->assistance_pack_id]);
            if ($pack instanceof CareCohortPack && !$pack->isExpired()) {
                return $pack;
            }
        }

        if ($binding !== null) {
            $pack = $this->repository->findValidPack(
                CarePackType::ASSISTANCE_QUESTIONS,
                $binding->cohort_key
            );
            if ($pack !== null) {
                $this->repository->attachPackToEncounter(
                    (int) $encounter->id,
                    CarePackType::ASSISTANCE_QUESTIONS,
                    (int) $pack->id
                );

                return $pack;
            }
        }

        $built = (new CohortKeyBuilder())->buildForPersona((int) $encounter->subject_persona_id, $encounter);
        $this->orchestrator->onEncounterEnsured($encounter);

        return $this->repository->findValidPack(CarePackType::ASSISTANCE_QUESTIONS, $built['cohort_key']);
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $packContent
     * @return array<string, mixed>
     */
    private function extractAnswers(array $body, array $packContent): array
    {
        $answers = [];
        $questions = $packContent['questions'] ?? [];
        if (!is_array($questions)) {
            return $answers;
        }

        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }
            $id = trim((string) ($q['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            if (array_key_exists($id, $body)) {
                $answers[$id] = $body[$id];
            }
        }

        return $answers;
    }

    /**
     * @param array<string, mixed> $answers
     * @param array<string, mixed> $packContent
     */
    private function validateRequired(array $answers, array $packContent): ?string
    {
        $questions = $packContent['questions'] ?? [];
        if (!is_array($questions)) {
            return null;
        }

        foreach ($questions as $q) {
            if (!is_array($q) || empty($q['required'])) {
                continue;
            }
            $id = trim((string) ($q['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $val = trim((string) ($answers[$id] ?? ''));
            if ($val === '') {
                return 'Completá todas las preguntas obligatorias.';
            }
        }

        return null;
    }
}
