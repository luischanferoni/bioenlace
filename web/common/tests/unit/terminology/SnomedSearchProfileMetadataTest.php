<?php

namespace common\tests\unit\terminology;

use Codeception\Test\Unit;
use common\components\Domain\Terminology\Snomed\SnomedSearchProfileCatalog;
use common\components\Platform\Core\Product\SnomedSearchProfileMetadata;

class SnomedSearchProfileMetadataTest extends Unit
{
    protected function _after(): void
    {
        SnomedSearchProfileMetadata::resetCacheForTests();
    }

    public function testClientMethodMapsToProfile(): void
    {
        $this->assertSame('problemas', SnomedSearchProfileCatalog::profileKeyForClientMethod('getProblemas'));
        $this->assertSame('sintomas', SnomedSearchProfileCatalog::profileKeyForClientMethod('getSintomas'));
        $this->assertNull(SnomedSearchProfileCatalog::profileKeyForClientMethod('getDesconocido'));
    }

    public function testEclForKnownProfiles(): void
    {
        foreach (['problemas', 'medicamentos_genericos', 'alergias', 'motivos_consulta'] as $key) {
            $ecl = SnomedSearchProfileCatalog::eclForProfile($key);
            $this->assertIsString($ecl);
            $this->assertNotSame('', trim($ecl));
        }
    }

    public function testInmunizacionesUsesRawApiFormat(): void
    {
        $this->assertSame('raw_api', SnomedSearchProfileCatalog::returnFormatForProfile('inmunizaciones'));
    }
}
