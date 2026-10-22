<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Person\Identity\Application\Catalog\PatientAddressVerificationNotificationCatalog;

final class PatientAddressVerificationNotificationCatalogTest extends Unit
{
    protected function _after(): void
    {
        PatientAddressVerificationNotificationCatalog::resetCacheForTests();
    }

    public function testReturnsVerificationNotificationCopy(): void
    {
        $this->assertSame(
            [
                'title' => 'Domicilio verificado',
                'body' => 'Tu domicilio fue verificado. Ya podés usar todos los servicios de la app.',
            ],
            (new PatientAddressVerificationNotificationCatalog())->verified()
        );
    }
}
