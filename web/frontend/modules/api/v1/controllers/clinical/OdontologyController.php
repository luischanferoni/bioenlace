<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Specialty\Domain\EncounterDefinitionSpecialtyRegistry;
use common\components\Domain\Clinical\Specialty\Application\Service\OdontologyEncounterService;
use common\models\Clinical\EncounterDefinition;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * Datos odontológicos de un encounter.
 *
 * GET /api/v1/clinical/encounter/<encounterId>/odontology
 *
 * Devuelve las prácticas y diagnósticos del encounter, el estado de las piezas
 * registrado en ese encounter y el odontograma acumulado del paciente hasta ese
 * encuentro. La autorización es la del encounter (`assertEncounterAccess`).
 */
class OdontologyController extends BaseController
{
    use ClinicalAccessTrait;

    private OdontologyEncounterService $service;

    public function init()
    {
        parent::init();
        $this->service = new OdontologyEncounterService();
    }

    public function actionIndex($encounterId)
    {
        $encounter = $this->assertEncounterAccess((int) $encounterId);

        $bundle = $this->service->bundleForEncounter($encounter->id);
        $specialties = [];
        if ($encounter->service_id && $encounter->encounter_class) {
            $def = EncounterDefinition::findOne([
                'service_id' => $encounter->service_id,
                'encounter_class' => $encounter->encounter_class,
            ]);
            if ($def !== null) {
                $registry = new EncounterDefinitionSpecialtyRegistry();
                $specialties = $registry->specialtiesForDefinition($def);
            }
        }

        return $this->ok(array_merge($bundle, [
            'encounterId' => (int) $encounter->id,
            'odontogram' => $this->service->odontogramForEncounter($encounter),
            'enabledSpecialties' => $specialties,
            'specialtyMetadata' => (new EncounterDefinitionSpecialtyRegistry())
                ->metadataForSpecialties($specialties),
        ]));
    }
}
