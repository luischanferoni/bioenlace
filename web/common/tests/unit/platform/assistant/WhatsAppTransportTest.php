<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\WhatsApp\WhatsAppChannelScopeCatalog;
use common\components\Platform\Assistant\WhatsApp\WhatsAppEnvelopeRenderer;
use common\components\Platform\Assistant\WhatsApp\WhatsAppLinkToken;
use common\models\Integrations\AsistenteWhatsappVinculo;

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

    public function testVinculoStatesAreClosed(): void
    {
        $this->assertSame(
            [
                AsistenteWhatsappVinculo::ESTADO_PENDIENTE_VERIFICACION,
                AsistenteWhatsappVinculo::ESTADO_ACTIVO,
                AsistenteWhatsappVinculo::ESTADO_RECHAZADO,
                AsistenteWhatsappVinculo::ESTADO_BLOQUEADO,
                AsistenteWhatsappVinculo::ESTADO_EXPIRADO,
            ],
            AsistenteWhatsappVinculo::estadoValues()
        );
    }

    public function testLinkTokenIsHashedAndSingleUseReady(): void
    {
        $generated = WhatsAppLinkToken::generate(600);

        $this->assertSame(64, strlen($generated['token']));
        $this->assertSame(hash('sha256', $generated['token']), $generated['hash']);
        $this->assertFalse(WhatsAppLinkToken::isExpired($generated['expires_at']));
        $this->assertTrue(WhatsAppLinkToken::isExpired(date('Y-m-d H:i:s', time() - 1)));
        $this->assertTrue(WhatsAppLinkToken::isExpired(null));
    }

    public function testChannelScopeBlocksClinicalAndUnknownIntents(): void
    {
        $this->assertTrue(WhatsAppChannelScopeCatalog::isAllowed('turnos.ver-mis-turnos-como-paciente'));
        $this->assertFalse(WhatsAppChannelScopeCatalog::isAllowed('laboratorio.ver-resultados-como-paciente'));
        $this->assertFalse(WhatsAppChannelScopeCatalog::isAllowed('atencion.necesito-atencion'));
        $this->assertFalse(WhatsAppChannelScopeCatalog::isAllowed('intent.inexistente'));
    }

    public function testChannelScopeMarksWrites(): void
    {
        $this->assertTrue(WhatsAppChannelScopeCatalog::isMutating('turnos.cancelar-como-paciente-flow'));
        $this->assertTrue(WhatsAppChannelScopeCatalog::isMutating('turnos.crear-como-paciente'));
        $this->assertFalse(WhatsAppChannelScopeCatalog::isMutating('turnos.ver-mis-turnos-como-paciente'));
    }
}
