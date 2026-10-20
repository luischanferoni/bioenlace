<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Inpatient\Application\InpatientAccessService;
use common\components\Domain\Clinical\Encounter\Application\Authorization\EncounterAccessService;
use common\components\Domain\Organization\Efector\Application\Authorization\EfectorOperationAccess;
use common\components\Platform\Core\Permission\Domain\DomainOperationAuthorizer;
use common\components\Platform\Core\Permission\Domain\DomainOperationForbiddenException;
use common\components\Domain\Person\Representation\Domain\Model\RepresentationPermission;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\models\Clinical\CarePlan;
use common\models\Clinical\Encounter;
use common\models\Clinical\InpatientStay;
use Yii;
use yii\web\ForbiddenHttpException;

trait ClinicalAccessTrait
{
    /**
     * @throws ApiException
     */
    protected function assertEncounterAccess(int $encounterId, ?string $representationPermission = null): Encounter
    {
        $encounter = Encounter::findOne($encounterId);
        if (!$encounter) {
            throw new ApiException('encounter_not_found');
        }

        try {
            EncounterAccessService::assertAccess(
                $encounter,
                'Encounter.access',
                $representationPermission
            );
        } catch (DomainOperationForbiddenException $e) {
            throw new ApiException('encounter_forbidden', ['detail' => $e->getMessage()]);
        }

        return $encounter;
    }

    protected function canAccessEncounterDomain(
        Encounter $encounter,
        string $operationKey = 'Encounter.access',
        ?string $representationPermission = null
    ): bool {
        return EncounterAccessService::canAccess(
            $encounter,
            $operationKey,
            $representationPermission
        );
    }

    protected function staffCanAccessInternacion(InpatientStay $internacion): bool
    {
        return InpatientAccessService::staffCanAccess($internacion);
    }

    protected function assertInternacionStaffAccess(int $internacionId, string $operationKey = 'Internacion.staff_access'): InpatientStay
    {
        $internacion = InpatientStay::findOne($internacionId);
        if ($internacion === null) {
            throw new ApiException('inpatient_not_found');
        }

        try {
            (new DomainOperationAuthorizer())->assert($operationKey, $internacion);
        } catch (DomainOperationForbiddenException $e) {
            throw new ApiException('inpatient_forbidden', [], [], $e);
        }

        return $internacion;
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

    protected function assertCarePlanAccess(int $carePlanId): CarePlan
    {
        $plan = CarePlan::findOne($carePlanId);
        if (!$plan) {
            throw new ApiException('care_plan_not_found');
        }
        if (!$this->canAccessCarePlan($plan)) {
            throw new ApiException('care_plan_forbidden');
        }

        return $plan;
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
            return EfectorOperationAccess::assertAndResolveIdEfector($operationKey, $params);
        } catch (DomainOperationForbiddenException $e) {
            throw new ForbiddenHttpException($e->getMessage() !== '' ? $e->getMessage() : 'No autorizado.');
        }
    }
}
