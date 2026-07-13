<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\EfectorAtributosMetadata;

class EfectorAtributosMetadataTest extends Unit
{
    public function testOrigenFinanciamientoSoloPublicoYPrivado(): void
    {
        $opts = EfectorAtributosMetadata::optionsFor(EfectorAtributosMetadata::ATTR_ORIGEN_FINANCIAMIENTO);
        $this->assertSame(['Público', 'Privado'], array_keys($opts));
    }

    public function testPreservaValorActualFueraDeCatalogo(): void
    {
        $opts = EfectorAtributosMetadata::optionsFor(
            EfectorAtributosMetadata::ATTR_TIPOLOGIA,
            'XYZ_LEGACY'
        );
        $this->assertArrayHasKey('XYZ_LEGACY', $opts);
        $this->assertContains('XYZ_LEGACY (actual)', $opts);
    }
}
