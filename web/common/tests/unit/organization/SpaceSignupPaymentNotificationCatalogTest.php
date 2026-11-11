<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Billing\Application\Catalog\SpaceSignupPaymentNotificationCatalog;

/**
 * Copy del aviso al titular cuando se confirma el pago del espacio de atención.
 */
class SpaceSignupPaymentNotificationCatalogTest extends Unit
{
    protected function _before(): void
    {
        SpaceSignupPaymentNotificationCatalog::resetCacheForTests();
    }

    public function testAvisoDePagoInterpolaElNombreDelEspacio(): void
    {
        $message = (new SpaceSignupPaymentNotificationCatalog())->paymentConfirmed('Clínica del Sol');

        $this->assertNotSame('', $message['subject']);
        $this->assertStringContainsString('Clínica del Sol', $message['body']);
        $this->assertStringNotContainsString('{efector}', $message['body']);
    }

    public function testSinNombreUsaUnTextoGenericoYNoDejaPlaceholders(): void
    {
        $message = (new SpaceSignupPaymentNotificationCatalog())->paymentConfirmed('   ');

        $this->assertStringNotContainsString('{efector}', $message['body']);
        $this->assertNotSame('', $message['body']);
    }
}
