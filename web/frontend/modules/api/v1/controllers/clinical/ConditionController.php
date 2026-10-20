<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Encounter\Domain\ConditionClinicalStatus;
use common\components\Domain\Clinical\Encounter\Application\UseCase\AdvanceConditionLifecycle;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterOpenProblemsService;
use common\components\Domain\Person\Representation\Domain\Model\RepresentationPermission;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
use common\components\Platform\Core\Http\Contract\ApiException;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;

/**
 * Condition (diagnósticos / problemas).
 *
 * GET  /api/v1/clinical/encounter/<encounterId>/conditions
 * GET  /api/v1/clinical/conditions/open-problems?subject_persona_id=
 * POST /api/v1/clinical/conditions/<id>/resolve
 * POST /api/v1/clinical/conditions/<id>/inactivate
 * POST /api/v1/clinical/conditions/<id>/transition  body: { clinical_status, note? }
 */
class ConditionController extends BaseController
{
    use ClinicalAccessTrait;

    private AdvanceConditionLifecycle $lifecycle;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->lifecycle = new AdvanceConditionLifecycle();
    }

    public function actionIndex($encounterId)
    {
        $encounter = $this->assertEncounterAccess((int) $encounterId);

        $rows = Condition::find()
            ->where(['encounter_id' => $encounter->id])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->toApiArray($row);
        }

        return $this->ok($data);
    }

    /**
     * Problemas y planes abiertos del paciente (sin preselección) para cierre de atención.
     */
    public function actionOpenProblems()
    {
        $subjectId = (int) (Yii::$app->request->get('subject_persona_id')
            ?? Yii::$app->request->get('id_persona')
            ?? 0);
        if ($subjectId <= 0) {
            $body = $this->mergeRequestBody();
            $subjectId = (int) ($body['subject_persona_id'] ?? $body['id_persona'] ?? 0);
        }
        if ($subjectId <= 0) {
            return $this->fail('subject_required');
        }

        $sessionPersona = (int) (Yii::$app->user->getIdPersona() ?? 0);
        if ($sessionPersona > 0 && $sessionPersona === $subjectId) {
            // Paciente sobre sí mismo: ok (lectura).
        } else {
            $sample = Encounter::find()
                ->where(['subject_persona_id' => $subjectId])
                ->andWhere(['deleted_at' => null])
                ->orderBy(['id' => SORT_DESC])
                ->limit(1)
                ->one();
            if (!$sample instanceof Encounter) {
                return $this->fail('patient_problems_forbidden');
            }
            $this->assertEncounterAccess((int) $sample->id, RepresentationPermission::CLINICAL_CARE_PLAN);
        }

        return $this->ok((new EncounterOpenProblemsService())->forSubject($subjectId));
    }

    public function actionResolve($id)
    {
        $body = $this->mergeRequestBody();
        $note = isset($body['note']) ? (string) $body['note'] : null;

        return $this->transitionOne((int) $id, ConditionClinicalStatus::RESOLVED, $note, 'Diagnóstico marcado como resuelto');
    }

    public function actionInactivate($id)
    {
        $body = $this->mergeRequestBody();
        $note = isset($body['note']) ? (string) $body['note'] : null;

        return $this->transitionOne((int) $id, ConditionClinicalStatus::INACTIVE, $note, 'Diagnóstico inactivado');
    }

    public function actionTransition($id)
    {
        $body = $this->mergeRequestBody();
        $status = (string) ($body['clinical_status'] ?? $body['status'] ?? '');
        if ($status === '') {
            return $this->fail('condition_status_required');
        }
        $note = isset($body['note']) ? (string) $body['note'] : null;

        return $this->transitionOne((int) $id, $status, $note, 'Diagnóstico actualizado');
    }

    private function transitionOne(int $id, string $status, ?string $note, string $message): array
    {
        $condition = $this->assertConditionAccess($id);

        try {
            $condition = $this->lifecycle->transition($condition, $status, $note);
        } catch (\InvalidArgumentException $e) {
            return $this->fail('request_invalid', ['detail' => $e->getMessage()]);
        }

        return $this->ok($this->toApiArray($condition), ['message' => $message]);
    }

    /**
     * @throws ApiException
     */
    private function assertConditionAccess(int $conditionId): Condition
    {
        $condition = Condition::findOne($conditionId);
        if ($condition === null || $condition->deleted_at !== null) {
            throw new ApiException('condition_not_found');
        }

        $sessionPersona = (int) (Yii::$app->user->getIdPersona() ?? 0);
        $encounterId = (int) ($condition->encounter_id ?? 0);
        // Paciente no muta sus diagnósticos desde app.
        if (($sessionPersona > 0 && $sessionPersona === (int) $condition->subject_persona_id) || $encounterId <= 0) {
            throw new ApiException('condition_forbidden');
        }

        $this->assertEncounterAccess($encounterId, RepresentationPermission::CLINICAL_CARE_PLAN);

        return $condition;
    }

    /**
     * @return array<string, mixed>
     */
    private function toApiArray(Condition $row): array
    {
        return [
            'resourceType' => 'Condition',
            'id' => (int) $row->id,
            'encounterId' => $row->encounter_id !== null ? (int) $row->encounter_id : null,
            'subjectPersonaId' => (int) $row->subject_persona_id,
            'code' => $row->code,
            'display' => $row->display,
            'clinicalStatus' => $row->clinical_status,
            'verificationStatus' => $row->verification_status,
            'note' => $row->note,
        ];
    }
}
