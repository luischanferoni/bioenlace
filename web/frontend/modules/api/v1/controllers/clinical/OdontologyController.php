<?php

namespace frontend\modules\api\v1\controllers\clinical;

use common\components\Domain\Clinical\Specialty\EncounterDefinitionSpecialtyRegistry;
use common\components\Domain\Clinical\Specialty\Odontology\OdontologyEncounterService;
use common\models\Clinical\EncounterDefinition;
use frontend\modules\api\v1\controllers\BaseController;

/**
 * Datos odontológicos de un encounter.
 *
 * GET /api/v1/clinical/encounter/<encounterId>/odontology
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
        [$encounter, $err] = $this->requireEncounterAccess((int) $encounterId);
        if ($err !== null) {
            return $err;
        }

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

        return [
            'success' => true,
            'message' => 'Odontología del encounter',
            'data' => array_merge($bundle, [
                'encounterId' => (int) $encounter->id,
                'enabledSpecialties' => $specialties,
            ]),
        ];
    }
}
