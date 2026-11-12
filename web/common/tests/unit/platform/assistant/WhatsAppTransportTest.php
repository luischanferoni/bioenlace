<?php

namespace common\tests\unit\platform\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\WhatsApp\WhatsAppChannelScopeCatalog;
use common\components\Platform\Assistant\WhatsApp\WhatsAppEnvelopeRenderer;
use common\components\Platform\Assistant\WhatsApp\WhatsAppLinkToken;
use common\components\Platform\Assistant\WhatsApp\WhatsAppStatusNormalizer;
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

    public function testStatusNormalizerReadsPricing(): void
    {
        $row = WhatsAppStatusNormalizer::normalize([
            'id' => 'wamid.1',
            'recipient_id' => '5491112345678',
            'status' => 'delivered',
            'timestamp' => '1700000000',
            'pricing' => [
                'category' => 'service',
                'type' => 'free_customer_service',
                'billable' => false,
            ],
            'conversation' => ['id' => 'conv-1'],
        ]);

        $this->assertNotNull($row);
        $this->assertSame('wamid.1', $row['wamid']);
        $this->assertSame('service', $row['pricing_category']);
        $this->assertSame('free_customer_service', $row['pricing_type']);
        $this->assertSame(0, $row['billable']);
        $this->assertSame('conv-1', $row['conversation_id']);
        $this->assertNotNull($row['event_at']);
        $this->assertNull(WhatsAppStatusNormalizer::normalize(['status' => 'sent']));
    }
}
