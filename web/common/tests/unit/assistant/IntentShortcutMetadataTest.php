<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Catalog\IntentShortcutMetadata;

class IntentShortcutMetadataTest extends Unit
{
    protected function _before(): void
    {
        \common\components\Platform\Core\Permission\IntentManifestIndex::resetCache();
    }

    public function testTableroGuardiaOpensNativeUi(): void
    {
        $this->assertTrue(IntentShortcutMetadata::opensNativeUi('urgencias.ver-tablero-guardia'));
    }

    public function testTriageGuardiaDoesNotOpenNativeUi(): void
    {
        $this->assertFalse(IntentShortcutMetadata::opensNativeUi('urgencias.triage-paciente-guardia'));
    }

    public function testBlankIntentDoesNotOpenNativeUi(): void
    {
        $this->assertFalse(IntentShortcutMetadata::opensNativeUi(''));
    }

    public function testHiddenFlowIsNotEligibleShortcut(): void
    {
        $this->assertFalse(IntentShortcutMetadata::isEligibleCatalogShortcut([
            'action_id' => 'internacion.cambio-cama-flow',
            'shortcut_hidden' => true,
            'has_subintents' => true,
        ]));
    }

    public function testIntentWithoutSubintentsIsNotEligibleShortcut(): void
    {
        $this->assertFalse(IntentShortcutMetadata::isEligibleCatalogShortcut([
            'action_id' => 'internacion.epicrisis-plantilla-admin',
            'shortcut_hidden' => false,
            'has_subintents' => false,
        ]));
    }

    public function testRunnableFlowIsEligibleShortcut(): void
    {
        $this->assertTrue(IntentShortcutMetadata::isEligibleCatalogShortcut([
            'action_id' => 'internacion.mapa-camas-flow',
            'shortcut_hidden' => false,
            'has_subintents' => true,
        ]));
    }

    public function testEgresoAndStaffHoursIntentsAreHiddenInYaml(): void
    {
        foreach ([
            'urgencias.egreso-estructurado-flow',
            'profesional-agenda.configurar-staff',
            'profesional-cobertura.gestionar-staff',
        ] as $intentId) {
            $file = \common\components\Platform\Assistant\Catalog\IntentSchemaPaths::resolveFileForIntentId($intentId);
            $this->assertNotNull($file, $intentId);
            $data = \Symfony\Component\Yaml\Yaml::parseFile($file);
            $this->assertIsArray($data);
            $this->assertTrue(IntentShortcutMetadata::isHidden($data), $intentId);
        }
    }
}
