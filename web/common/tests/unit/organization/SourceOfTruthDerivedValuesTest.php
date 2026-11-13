<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Pes\Domain\Catalog\PricingPesByEncounterClassCatalog;
use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use common\models\Clinical\EncounterDefinition;

/**
 * Contrasta los valores derivados contra su fuente declarada.
 */
class SourceOfTruthDerivedValuesTest extends Unit
{
    public function testClinicalHistoryExportClassesComeFromTheDomainCatalog(): void
    {
        $params = require dirname(__DIR__, 3) . '/config/params.php';

        $this->assertSame(
            EncounterDefinition::ENCOUNTER_CLASS_CLINICAL_EXPORT,
            $params['clinicalHistoryExchange']['encounter_classes']
        );
    }

    public function testClinicalHistoryExportClassesAreDeclaredEncounterClasses(): void
    {
        foreach (EncounterDefinition::ENCOUNTER_CLASS_CLINICAL_EXPORT as $code) {
            $this->assertArrayHasKey($code, EncounterDefinition::ENCOUNTER_CLASS);
        }
    }

    public function testEntitlementDefaultsMatchTheCatalogPerClass(): void
    {
        foreach (PricingPesByEncounterClassMetadata::sellableClassCodes() as $class) {
            $values = PricingPesByEncounterClassMetadata::addonPlanValues($class);
            foreach (PricingPesByEncounterClassMetadata::addonPlanFields() as $addonId => $field) {
                $state = PricingPesByEncounterClassMetadata::addonState($addonId, $class);
                $this->assertSame(
                    $state === PricingPesByEncounterClassCatalog::ADDON_INCLUDED,
                    $values[$field],
                    $class . '/' . $addonId
                );
            }
        }
    }
}
