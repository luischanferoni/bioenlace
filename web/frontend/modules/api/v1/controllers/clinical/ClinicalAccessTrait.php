<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Clinical\Inpatient\Service\InternacionAccessService;
use common\components\Core\Permission\Domain\EncounterDomainAccessService;
use common\components\Core\Permission\Domain\EfectorDomainAccessService;
use common\components\Core\Permission\Domain\DomainOperationAuthorizer;
use common\components\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Person\Representation\Enum\RepresentationPermission;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use common\models\SegNivelInternacion;
use Yii;
use yii\web\ForbiddenHttpException;

trait ClinicalAccessTrait
{
    /**
     * @return array{0: Encounter|null, 1: array<string, mixed>|null}
     */
    protected function requireEncounterAccess(int $encounterId, ?string $representationPermission = null): array
    {
        $encounter = Encounter::findOne($encounterId);
        if (!$encounter) {
            Yii::$app->response->statusCode = 404;

            return [null, $this->clinicalError('Encounter no encontrado', null, 404)];
        }

        try {
            EncounterDomainAccessService::assertAccess(
                $encounter,
                'Encounter.access',
                $representationPermission
            );
        } catch (DomainOperationForbiddenException $e) {
            Yii::$app->response->statusCode = 403;

            return [null, $this->clinicalError($e->getMessage() !== '' ? $e->getMessage() : 'No tiene permiso para acceder a este encounter', null, 403)];
        }

        return [$encounter, null];
    }

    protected function canAccessEncounterDomain(
        Encounter $encounter,
        string $operationKey = 'Encounter.access',
        ?string $representationPermission = null
    ): bool {
        return EncounterDomainAccessService::canAccess(
            $encounter,
            $operationKey,
            $representationPermission
        );
    }

    protected function staffCanAccessInternacion(SegNivelInternacion $internacion): bool
    {
        return InternacionAccessService::staffCanAccess($internacion);
    }

    /**
     * @return array{0: SegNivelInternacion|null, 1: array<string, mixed>|null}
     */
    protected function requireInternacionStaffAccess(int $internacionId, string $operationKey = 'Internacion.staff_access'): array
    {
        $internacion = SegNivelInternacion::findOne($internacionId);
        if ($internacion === null) {
            Yii::$app->response->statusCode = 404;

            return [null, $this->clinicalError('Internación no encontrada', null, 404)];
        }

        try {
            (new DomainOperationAuthorizer())->assert($operationKey, $internacion);
        } catch (DomainOperationForbiddenException $e) {
            Yii::$app->response->statusCode = 403;

            return [null, $this->clinicalError($e->getMessage() !== '' ? $e->getMessage() : 'No tiene permiso para acceder a esta internación', null, 403)];
        }

        return [$internacion, null];
    }

    protected function canAccessCarePlan(CarePlan $plan): bool
    {
        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0 && (int) $plan->subject_persona_id === $idPersona) {
            return true;
        }
        if ($plan->encounter_id) {
            $encounter = Encounter::findOne((int) $plan->encounter_id);

            return $encounter !== null && $this->canAccessEncounterDomain(
                $encounter,
                'Encounter.access',
                RepresentationPermission::CLINICAL_CARE_PLAN
            );
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

    /**
     * Valida política de dominio por efector y devuelve id_efector resuelto.
     *
     * @throws \yii\web\ForbiddenHttpException
     */
    protected function resolveIdEfectorForDomainOperation(string $operationKey): int
    {
        $req = Yii::$app->request;
        $params = array_merge($req->get(), $req->post());
        try {
            return EfectorDomainAccessService::assertAndResolveIdEfector($operationKey, $params);
        } catch (DomainOperationForbiddenException $e) {
            throw new ForbiddenHttpException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.');
        }
    }
}
