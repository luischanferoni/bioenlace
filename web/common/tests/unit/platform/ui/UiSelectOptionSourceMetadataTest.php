<?php

namespace common\tests\unit\platform\ui;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\UiSelectOptionSourceMetadata;
use common\components\Platform\Ui\UiSelectOptionSourceProviderRegistry;

class UiSelectOptionSourceMetadataTest extends Unit
{
    protected function _after(): void
    {
        UiSelectOptionSourceMetadata::resetCacheForTests();
        UiSelectOptionSourceProviderRegistry::resetForTests();
    }

    public function testProviderKeyForOrganizationSources(): void
    {
        $this->assertSame('organization', UiSelectOptionSourceMetadata::providerKeyForSource('efectores'));
        $this->assertSame('scheduling', UiSelectOptionSourceMetadata::providerKeyForSource('profesional-efector-servicio'));
    }

    public function testSchedulingSourceReturnsEmptyWithoutParams(): void
    {
        $options = UiSelectOptionSourceProviderRegistry::resolve(
            'profesional-efector-servicio',
            ['filter' => 'efector_rrhh'],
            []
        );
        $this->assertIsArray($options);
        $this->assertSame([], $options);
    }

    public function testAllowsMissingDependsOnForEfectorServicios(): void
    {
        $this->assertTrue(UiSelectOptionSourceMetadata::allowsMissingDependsOn('servicios', [
            'filter' => 'efector_servicios',
        ]));
        $this->assertFalse(UiSelectOptionSourceMetadata::allowsMissingDependsOn('servicios', [
            'filter' => 'other',
        ]));
    }
}
