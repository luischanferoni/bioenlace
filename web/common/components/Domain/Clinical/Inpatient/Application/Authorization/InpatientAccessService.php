<?php

namespace common\components\Domain\Clinical\Inpatient\Application\Authorization;

use common\components\Domain\Clinical\Inpatient\Domain\InpatientClinicalContext;
use common\components\Domain\Clinical\Encounter\Application\Authorization\EncounterAccessService;
use common\components\Domain\Organization\Efector\Application\Authorization\OrganizationEfectorAccess;
use common\models\Clinical\InpatientStay;
use Yii;

/**
 * Autorización de acceso staff/paciente a una internación concreta.
 */
final class InpatientAccessService
{
    public static function staffCanAccess(InpatientStay $internacion): bool
    {
        if (Yii::$app->user->isSuperadmin) {
            return true;
        }

        $idPersona = (int) Yii::$app->user->getIdPersona();
        if ($idPersona > 0 && (int) $internacion->id_persona === $idPersona) {
            return true;
        }

        $encounter = InpatientClinicalContext::findOpenInpatientEncounter((int) $internacion->id);
        if ($encounter !== null && EncounterAccessService::canAccess($encounter)) {
            return true;
        }

        $idEfector = OrganizationEfectorAccess::resolveIdEfector(null);
        if ($idEfector > 0 && InpatientEfectorAccess::internacionPerteneceEfector($internacion, $idEfector)) {
            return true;
        }

        return false;
    }
}
