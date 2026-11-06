<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\Laboratory\Infrastructure\External\Connector\SianlabsFhirConnector;
use common\components\Domain\Clinical\Laboratory\Infrastructure\External\Exception\LaboratoryConnectorException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\components\Shared\Infrastructure\Http\RetryPolicy;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;
use yii\httpclient\Client;

/**
 * ACL Sianlabs: token compartido y mapeo de error del conector.
 *
 * @see SianlabsFhirConnector
 */
class SianlabsFhirConnectorTest extends Unit
{
    protected function _before(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    protected function _after(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    public function testResolvePatientPideTokenYConsultaFhir(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"tok-1","expires_in":3600}')
            ->respond(200, '{"resourceType":"Bundle","entry":[{"resource":{"resourceType":"Patient","id":"p-9"}}]}');

        $connector = $this->connector($transport);

        $this->assertSame('p-9', $connector->resolvePatientFhirId('30111222'));

        $sent = $transport->sent();
        $this->assertCount(2, $sent);
        $this->assertSame('https://sianlabs.test/oauth/token', $sent[0]['url']);
        $this->assertSame(
            'https://sianlabs.test/api/fhir/Patient?identifier=30111222',
            $sent[1]['url']
        );
        $this->assertSame('Bearer tok-1', $sent[1]['headers']['authorization']);
        $this->assertStringContainsString('fhir+json', (string) $sent[1]['headers']['accept']);
    }

    public function testSegundaConsultaReusaElTokenCacheado(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"tok-1","expires_in":3600}')
            ->respond(200, '{"resourceType":"Bundle","entry":[]}')
            ->respond(200, '{"resourceType":"Bundle","entry":[]}');

        $connector = $this->connector($transport);
        $connector->fetchDiagnosticReports('p-9');
        $connector->fetchDiagnosticReports('p-9');

        $this->assertCount(3, $transport->sent(), '1 token + 2 consultas: el token se cachea.');
    }

    public function testSinCredencialesFallaSinHacerRed(): void
    {
        $transport = new FakeTransport();
        $connector = new SianlabsFhirConnector([
            'baseUrl' => 'https://sianlabs.test/api/fhir/',
            'tokenUrl' => 'https://sianlabs.test/oauth/token',
            'httpClient' => $this->api($transport),
        ]);

        try {
            $connector->fetchDiagnosticReports('p-9');
            $this->fail('Se esperaba LaboratoryConnectorException.');
        } catch (LaboratoryConnectorException $e) {
            $this->assertStringContainsString('clientId/clientSecret no configurados', $e->getMessage());
        }

        $this->assertCount(0, $transport->sent());
    }

    public function testHttp500SeTraduceAMensajeDelConector(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"tok-1","expires_in":3600}')
            ->respond(503, '{"resourceType":"OperationOutcome"}');

        try {
            $this->connector($transport)->fetchDiagnosticReports('p-9');
            $this->fail('Se esperaba LaboratoryConnectorException.');
        } catch (LaboratoryConnectorException $e) {
            $this->assertSame('Sianlabs HTTP 503 en DiagnosticReport', $e->getMessage());
        }
    }

    private function connector(FakeTransport $transport): SianlabsFhirConnector
    {
        return new SianlabsFhirConnector([
            'baseUrl' => 'https://sianlabs.test/api/fhir/',
            'tokenUrl' => 'https://sianlabs.test/oauth/token',
            'clientId' => 'cid',
            'clientSecret' => 'secret',
            'httpClient' => $this->api($transport),
        ]);
    }

    private function api(FakeTransport $transport): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: 'https://sianlabs.test/api/fhir/',
            defaultHeaders: ['Accept' => 'application/fhir+json, application/json'],
            connectTimeout: 1,
            timeout: 1,
            logCategory: 'laboratory-fhir',
            retry: new RetryPolicy(0, 0, 0),
            provider: 'sianlabs',
            providerLabel: 'Sianlabs',
            httpClient: new Client(['transport' => $transport]),
        );
    }
}
