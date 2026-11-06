<?php

namespace common\tests\unit\platform\push;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\WhatsApp\WhatsAppCloudApiClient;
use common\components\Platform\Core\Service\Push\FcmHttpSender;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;

/**
 * Familias push y WhatsApp: contrato "devuelve false ante error" y payload del proveedor.
 */
class PlatformPushHttpClientsTest extends Unit
{
    private FakeTransport $transport;

    protected function _before(): void
    {
        $this->transport = new FakeTransport();
        JsonApiClient::$transportOverride = $this->transport;
    }

    protected function _after(): void
    {
        JsonApiClient::$transportOverride = null;
    }

    public function testFcmLegacyMandaServerKeyYDevuelveTrue(): void
    {
        $this->setFcmConfig(['fcmServerKey' => 'server-key']);
        $this->transport->respond(200, '{"success":1}');

        $this->assertTrue(FcmHttpSender::send('device-1', 'Título', 'Cuerpo', ['type' => 'turno']));

        $sent = $this->transport->sent()[0];
        $this->assertSame('https://fcm.googleapis.com/fcm/send', $sent['url']);
        $this->assertSame('key=server-key', $sent['headers']['authorization']);
        $payload = json_decode((string) $sent['content'], true);
        $this->assertSame('device-1', $payload['to']);
        $this->assertSame('Título', $payload['notification']['title']);
    }

    public function testFcmDevuelveFalseConErrorHttp(): void
    {
        $this->setFcmConfig(['fcmServerKey' => 'server-key']);
        $this->transport->respond(503, 'unavailable');

        $this->assertFalse(FcmHttpSender::send('device-1', 'Título', 'Cuerpo'));
    }

    public function testFcmSinConfiguracionNoHaceRed(): void
    {
        $this->setFcmConfig([]);

        $this->assertFalse(FcmHttpSender::send('device-1', 'Título', 'Cuerpo'));
        $this->assertCount(0, $this->transport->sent());
    }

    public function testWhatsAppMandaBearerYPayload(): void
    {
        $this->setWhatsAppConfig('phone-1', 'wa-token');
        $this->transport->respond(200, '{"messages":[{"id":"wamid.1"}]}');

        $this->assertTrue((new WhatsAppCloudApiClient())->sendText('5491122334455', 'Hola'));

        $sent = $this->transport->sent()[0];
        $this->assertSame('https://graph.facebook.com/v21.0/phone-1/messages', $sent['url']);
        $this->assertSame('Bearer wa-token', $sent['headers']['authorization']);
        $payload = json_decode((string) $sent['content'], true);
        $this->assertSame('5491122334455', $payload['to']);
        $this->assertSame('whatsapp', $payload['messaging_product']);
    }

    public function testWhatsAppReintentaSinEl9CuandoFallaElPrimero(): void
    {
        $this->setWhatsAppConfig('phone-1', 'wa-token');
        $this->transport
            ->respond(400, '{"error":{"message":"invalid number"}}')
            ->respond(200, '{"messages":[{"id":"wamid.2"}]}');

        $this->assertTrue((new WhatsAppCloudApiClient())->sendText('5491122334455', 'Hola'));

        $sent = $this->transport->sent();
        $this->assertCount(2, $sent);
        $this->assertSame('5491122334455', json_decode((string) $sent[0]['content'], true)['to']);
        $this->assertSame('541122334455', json_decode((string) $sent[1]['content'], true)['to']);
    }

    public function testWhatsAppDevuelveFalseSiFallaTodo(): void
    {
        $this->setWhatsAppConfig('phone-1', 'wa-token');
        $this->transport
            ->respond(400, '{"error":{"message":"invalid number"}}')
            ->respond(400, '{"error":{"message":"invalid number"}}');

        $this->assertFalse((new WhatsAppCloudApiClient())->sendText('5491122334455', 'Hola'));
    }

    public function testWhatsAppSinConfiguracionNoHaceRed(): void
    {
        $this->setWhatsAppConfig('', '');

        $this->assertFalse((new WhatsAppCloudApiClient())->sendText('5491122334455', 'Hola'));
        $this->assertCount(0, $this->transport->sent());
    }

    /** @param array<string, mixed> $values */
    private function setFcmConfig(array $values): void
    {
        Yii::$app->params['fcmPush'] = $values;
        unset(Yii::$app->params['turnosPush']);
    }

    private function setWhatsAppConfig(string $phoneNumberId, string $token): void
    {
        Yii::$app->params['whatsapp'] = [
            'phoneNumberId' => $phoneNumberId,
            'accessToken' => $token,
            'apiVersion' => 'v21.0',
        ];
    }
}
