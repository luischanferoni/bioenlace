<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Service\HintCandidateProviderRegistry;
use common\components\Platform\Assistant\Service\HintResolutionMetadata;

class HintResolutionMetadataTest extends Unit
{
    protected function _after(): void
    {
        HintResolutionMetadata::resetCacheForTests();
        HintCandidateProviderRegistry::resetForTests();
    }

    public function testSchedulingIntentIdsFromMetadata(): void
    {
        $this->assertTrue(HintResolutionMetadata::intentUsesServiciosAceptaTurnos('turnos.crear-como-paciente'));
        $this->assertTrue(HintResolutionMetadata::intentUsesServiciosAceptaTurnos('turnos.cancelar-como-paciente-flow'));
        $this->assertTrue(HintResolutionMetadata::intentUsesServiciosAceptaTurnos('atencion.necesito-atencion'));
        $this->assertFalse(HintResolutionMetadata::intentUsesServiciosAceptaTurnos('data-access.editar'));
    }

    public function testEntityOwnershipOrder(): void
    {
        $this->assertSame(
            ['scheduling', 'organization'],
            HintResolutionMetadata::providerKeysForEntity('servicio')
        );
        $this->assertSame(['person'], HintResolutionMetadata::providerKeysForEntity('persona'));
    }
}
