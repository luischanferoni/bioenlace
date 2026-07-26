<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Enum\ConditionClinicalStatus;
use common\components\Domain\Clinical\Service\ConditionLifecycleService;
use common\components\Domain\Clinical\Service\EncounterOpenProblemsService;
use common\components\Domain\Person\Representation\Enum\RepresentationPermission;
use common\models\Clinical\Condition;
use common\models\Clinical\Encounter;
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

    private ConditionLifecycleService $lifecycle;

    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->lifecycle = new ConditionLifecycleService();
    }

    public function actionIndex($encounterId)
    {
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

        $rows = Condition::find()
            ->where(['encounter_id' => $encounter->id])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->toApiArray($row);
        }

        return [
            'success' => true,
            'message' => 'Condiciones del encounter',
            'data' => $data,
        ];
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
            return $this->clinicalError('subject_persona_id es obligatorio.', null, 400);
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
            if ($sample instanceof Encounter) {
                [$enc, $err] = $this->requireEncounterAccess(
                    (int) $sample->id,
                    RepresentationPermission::CLINICAL_CARE_PLAN
                );
                if ($err !== null) {
                    return $err;
                }
                unset($enc);
            } else {
                return $this->clinicalError('Sin permiso para ver problemas del paciente.', null, 403);
            }
        }

        $open = (new EncounterOpenProblemsService())->forSubject($subjectId);

        return [
            'success' => true,
            'message' => 'Problemas y planes abiertos',
            'data' => $open,
        ];
    }

    public function actionResolve($id)
    {
        $body = $this->mergeRequestBody();
        $note = isset($body['note']) ? (string) $body['note'] : null;

        return $this->transitionOne((int) $id, ConditionClinicalStatus::RESOLVED, $note, 'Condición marcada como resuelta');
    }

    public function actionInactivate($id)
    {
        $body = $this->mergeRequestBody();
        $note = isset($body['note']) ? (string) $body['note'] : null;

        return $this->transitionOne((int) $id, ConditionClinicalStatus::INACTIVE, $note, 'Condición inactivada');
    }

    public function actionTransition($id)
    {
        $body = $this->mergeRequestBody();
        $status = (string) ($body['clinical_status'] ?? $body['status'] ?? '');
        if ($status === '') {
            return $this->clinicalError('clinical_status es obligatorio.', null, 400);
        }
        $note = isset($body['note']) ? (string) $body['note'] : null;

        return $this->transitionOne((int) $id, $status, $note, 'Condición actualizada');
    }

    private function transitionOne(int $id, string $status, ?string $note, string $message): array
    {
        [$condition, $err] = $this->requireConditionAccess($id);
        if ($err !== null) {
            return $err;
        }

        try {
            $condition = $this->lifecycle->transition($condition, $status, $note);
        } catch (\InvalidArgumentException $e) {
            return $this->clinicalError($e->getMessage(), null, 400);
        } catch (\Throwable $e) {
            return $this->clinicalError($e->getMessage(), null, 500);
        }

        return [
            'success' => true,
            'message' => $message,
            'data' => $this->toApiArray($condition),
        ];
    }

    /**
     * @return array{0: Condition|null, 1: array<string, mixed>|null}
     */
    private function requireConditionAccess(int $conditionId): array
    {
        $condition = Condition::findOne($conditionId);
        if ($condition === null || $condition->deleted_at !== null) {
            Yii::$app->response->statusCode = 404;

            return [null, $this->clinicalError('Condición no encontrada', null, 404)];
        }

        $sessionPersona = (int) (Yii::$app->user->getIdPersona() ?? 0);
        if ($sessionPersona > 0 && $sessionPersona === (int) $condition->subject_persona_id) {
            // Paciente no muta sus diagnósticos desde app.
            Yii::$app->response->statusCode = 403;

            return [null, $this->clinicalError('Sin permiso para modificar esta condición', null, 403)];
        }

        $encounterId = (int) ($condition->encounter_id ?? 0);
        if ($encounterId > 0) {
            [$encounter, $err] = $this->requireEncounterAccess(
                $encounterId,
                RepresentationPermission::CLINICAL_CARE_PLAN
            );
            if ($err !== null) {
                return [null, $err];
            }
            unset($encounter);

            return [$condition, null];
        }

        Yii::$app->response->statusCode = 403;

        return [null, $this->clinicalError('Sin permiso para modificar esta condición', null, 403)];
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
