<?php

namespace common\tests\unit\terminology;

use Codeception\Test\Unit;
use common\components\Domain\Terminology\Snomed\SnomedCategoryCatalog;
use common\components\Platform\Core\Product\SnomedCategoryMetadata;

class SnomedCategoryMetadataTest extends Unit
{
    protected function _after(): void
    {
        SnomedCategoryMetadata::resetCacheForTests();
    }

    public function testExtractionLabelMapsToCategory(): void
    {
        $this->assertSame('diagnosticos', SnomedCategoryCatalog::resolveCategoryKey('Diagnóstico'));
        $this->assertSame('sintomas', SnomedCategoryCatalog::resolveCategoryKey('Síntomas'));
        $this->assertNull(SnomedCategoryCatalog::resolveCategoryKey('Desconocido'));
    }

    public function testEclDefinedForKnownCategories(): void
    {
        foreach (['diagnosticos', 'medicamentos', 'procedimientos', 'sintomas'] as $key) {
            $ecl = SnomedCategoryCatalog::eclForCategory($key);
            $this->assertIsString($ecl);
            $this->assertNotSame('', trim($ecl));
        }
    }

    public function testSemanticThresholdFromMetadata(): void
    {
        $this->assertSame(0.7, SnomedCategoryCatalog::semanticConfidenceThreshold());
        $this->assertSame(20, SnomedCategoryCatalog::candidateLimit());
    }
}
