<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\components\Domain\Clinical\Specialty\Inpatient\InpatientClinicalContext;
use common\components\Domain\Clinical\Service\Authorization\EncounterAccessService;
use common\components\Domain\Organization\Service\Efectores\OrganizationEfectorAccess;
use common\models\SegNivelInternacion;
use Yii;

/**
 * Autorización de acceso staff/paciente a una internación concreta.
 */
final class InternacionAccessService
{
    public static function staffCanAccess(SegNivelInternacion $internacion): bool
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
        if ($idEfector > 0 && InternacionEfectorAccess::internacionPerteneceEfector($internacion, $idEfector)) {
            return true;
        }

        return false;
    }
}
