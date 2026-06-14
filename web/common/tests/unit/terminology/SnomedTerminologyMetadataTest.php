<?php

namespace common\tests\unit\terminology;

use Codeception\Test\Unit;
use common\components\Domain\Terminology\Snomed\SnomedCategoryCatalog;
use common\components\Domain\Terminology\Snomed\SnomedSearchProfileCatalog;
use common\components\Platform\Core\Product\SnomedTerminologyMetadata;

class SnomedTerminologyMetadataTest extends Unit
{
    protected function _after(): void
    {
        SnomedTerminologyMetadata::resetCacheForTests();
    }

    public function testCategoryAndSearchProfileShareCanonicalEcl(): void
    {
        $fromCategory = SnomedCategoryCatalog::eclForCategory('diagnosticos');
        $fromProfile = SnomedSearchProfileCatalog::eclForProfile('problemas');

        $this->assertIsString($fromCategory);
        $this->assertSame($fromCategory, $fromProfile);
    }

    public function testEclRefResolvesFromDefinitions(): void
    {
        $ecl = SnomedTerminologyMetadata::eclForRef('procedimiento');
        $this->assertIsString($ecl);
        $this->assertStringContainsString('71388002', $ecl);
    }
}
