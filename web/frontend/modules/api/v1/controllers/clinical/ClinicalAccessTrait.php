<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Service\EncounterAccessService;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use Yii;

trait ClinicalAccessTrait
{
    /**
     * @return array{0: Encounter|null, 1: array<string, mixed>|null}
     */
    protected function requireEncounterAccess(int $encounterId): array
    {
        $encounter = Encounter::findOne($encounterId);
        if (!$encounter) {
            Yii::$app->response->statusCode = 404;

            return [null, $this->clinicalError('Encounter no encontrado', null, 404)];
        }
        if (!EncounterAccessService::userCanAccessEncounterApi($encounter)) {
            Yii::$app->response->statusCode = 403;

            return [null, $this->clinicalError('No tiene permiso para acceder a este encounter', null, 403)];
        }

        return [$encounter, null];
    }

    protected function canAccessCarePlan(CarePlan $plan): bool
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0 && (int) $plan->subject_persona_id === $idPersona) {
            return true;
        }
        if ($plan->encounter_id) {
            $encounter = Encounter::findOne((int) $plan->encounter_id);

            return $encounter !== null && EncounterAccessService::userCanAccessEncounterApi($encounter);
        }

        return false;
    }

    /**
     * @return array{0: CarePlan|null, 1: array<string, mixed>|null}
     */
    protected function requireCarePlanAccess(int $carePlanId): array
    {
        $plan = CarePlan::findOne($carePlanId);
        if (!$plan) {
            Yii::$app->response->statusCode = 404;

            return [null, $this->clinicalError('CarePlan no encontrado', null, 404)];
        }
        if (!$this->canAccessCarePlan($plan)) {
            Yii::$app->response->statusCode = 403;

            return [null, $this->clinicalError('No tiene permiso para acceder a este care plan', null, 403)];
        }

        return [$plan, null];
    }

    /**
     * @param mixed $errors
     * @return array<string, mixed>
     */
    protected function clinicalError(string $message, $errors = null, int $code = 400): array
    {
        return [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            '__statusCode' => $code,
        ];
    }
}
