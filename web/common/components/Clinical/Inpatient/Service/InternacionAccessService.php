<?php

namespace common\components\Clinical\Inpatient\Service;

use common\components\Clinical\Service\EncounterAccessService;
use common\components\Clinical\Specialty\Inpatient\InpatientClinicalContext;
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
        if ($encounter !== null && EncounterAccessService::userCanAccessEncounterApi($encounter)) {
            return true;
        }

        $idEfector = InternacionEfectorAccess::resolveIdEfector(null);
        if ($idEfector > 0 && InternacionEfectorAccess::internacionPerteneceEfector($internacion, $idEfector)) {
            return true;
        }

        return false;
    }
}
