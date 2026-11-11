<?php

namespace common\tests\unit\organization;

use Codeception\Test\Unit;
use common\components\Domain\Organization\Billing\Application\Service\SpaceSignupPaymentNotifier;
use common\components\Shared\Infrastructure\Mail\PlainTextMailer;

/**
 * Envío del aviso de pago sin tocar SMTP: los caminos que se pueden verificar sin servidor.
 */
class SpaceSignupPaymentNotifierTest extends Unit
{
    public function testMailerRechazaUnDestinoInvalidoSinLanzar(): void
    {
        $result = (new PlainTextMailer())->send('no-es-un-email', 'Asunto', 'Cuerpo');

        $this->assertFalse($result['sent']);
        $this->assertSame('invalid_email', $result['reason']);
    }

    public function testAvisoConEmailInvalidoDevuelveElMotivo(): void
    {
        $result = (new SpaceSignupPaymentNotifier())->notifyPaymentConfirmed('sin-arroba', 'Clínica del Sol');

        $this->assertFalse($result['sent']);
        $this->assertSame('invalid_email', $result['reason']);
    }
}
