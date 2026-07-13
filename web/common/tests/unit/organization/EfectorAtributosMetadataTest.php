<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\EfectorAtributosMetadata;

class EfectorAtributosMetadataTest extends Unit
{
    public function testOrigenFinanciamientoIncluyePublicoYPrivado(): void
    {
        $opts = EfectorAtributosMetadata::optionsFor(EfectorAtributosMetadata::ATTR_ORIGEN_FINANCIAMIENTO);
        $this->assertArrayHasKey('Público', $opts);
        $this->assertArrayHasKey('Privado', $opts);
        $this->assertArrayHasKey('Provincial', $opts);
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
