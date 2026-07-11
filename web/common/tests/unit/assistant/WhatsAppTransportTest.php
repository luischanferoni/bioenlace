<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\WhatsApp\WhatsAppEnvelopeRenderer;
use common\components\Platform\Assistant\WhatsApp\WhatsAppIdentityService;

class WhatsAppTransportTest extends Unit
{
    public function testDecodeCallbackPayloads(): void
    {
        $this->assertSame(
            ['type' => 'intent_id', 'value' => 'turnos.cancelar-como-paciente-flow'],
            WhatsAppEnvelopeRenderer::decodeCallbackPayload('i:turnos.cancelar-como-paciente-flow')
        );
        $this->assertSame(
            ['type' => 'action_id', 'value' => 'laboratorio.ver-resultados-como-paciente'],
            WhatsAppEnvelopeRenderer::decodeCallbackPayload('a:laboratorio.ver-resultados-como-paciente')
        );
        $this->assertSame(
            ['type' => 'hint', 'value' => 'mañana'],
            WhatsAppEnvelopeRenderer::decodeCallbackPayload('h:mañana')
        );
        $this->assertNull(WhatsAppEnvelopeRenderer::decodeCallbackPayload('plain'));
    }

    public function testPhoneMatchNormalizesSuffix(): void
    {
        $this->assertTrue(WhatsAppIdentityService::phonesMatch('5491112345678', '01112345678'));
        $this->assertTrue(WhatsAppIdentityService::phonesMatch('5491112345678', '5491112345678'));
        $this->assertFalse(WhatsAppIdentityService::phonesMatch('5491112345678', '5491199999999'));
        $this->assertSame('5491112345678', WhatsAppIdentityService::digitsOnly('+54 9 11 1234-5678'));
    }
}
