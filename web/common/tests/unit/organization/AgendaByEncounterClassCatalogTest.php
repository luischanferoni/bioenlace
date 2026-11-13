<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Pes\Domain\Catalog\AgendaByEncounterClassCatalog;
use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use common\models\Clinical\EncounterDefinition;

/**
 * El catálogo de agenda configura por clase; las claves deben coincidir con
 * las clases declaradas y cubrir todas las vendibles.
 */
class AgendaByEncounterClassCatalogTest extends Unit
{
    public function testKindsAreDeclaredEncounterClasses(): void
    {
        $config = AgendaByEncounterClassCatalog::config();

        foreach (array_keys($config['kinds']) as $code) {
            $this->assertArrayHasKey($code, EncounterDefinition::ENCOUNTER_CLASS);
        }
    }

    public function testEverySellableClassHasAnAgendaKind(): void
    {
        $kinds = array_keys(AgendaByEncounterClassCatalog::config()['kinds']);

        foreach (PricingPesByEncounterClassMetadata::sellableClassCodes() as $code) {
            $this->assertContains($code, $kinds, 'Falta la agenda para la clase vendible ' . $code);
        }
    }

    public function testSlotFinderClassesExistAndAllowPatientBooking(): void
    {
        $config = AgendaByEncounterClassCatalog::config();

        foreach ($config['patient_exposure']['slot_finder_encounter_classes'] as $code) {
            $this->assertArrayHasKey($code, $config['kinds']);
            $this->assertTrue((bool) $config['kinds'][$code]['patient_booking']);
        }
    }
}
