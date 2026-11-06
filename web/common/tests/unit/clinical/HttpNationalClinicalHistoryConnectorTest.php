<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Connector\HttpNationalClinicalHistoryConnector;
use common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Exception\ClinicalHistoryExchangeException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\components\Shared\Infrastructure\Http\RetryPolicy;
use common\models\Clinical\ClinicalHistoryOutboundJob;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;
use yii\httpclient\Client;

/**
 * ACL de historia clínica nacional: `Location`, retry en `401` y clave de caché histórica.
 *
 * @see HttpNationalClinicalHistoryConnector
 */
class HttpNationalClinicalHistoryConnectorTest extends Unit
{
    protected function _before(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    protected function _after(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    public function testSubmitResuelveExternalIdDesdeLocation(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"nac-tok","expires_in":3600}')
            ->respond(201, '', ['Location' => 'https://nac.test/fhir/Bundle/enc-123/_history/1']);

        $result = $this->connector($transport)->submitEncounterBundle($this->job(), '{"resourceType":"Bundle"}');

        $this->assertTrue($result->success);
        $this->assertSame('1', $result->externalId, 'El id sale del último segmento del Location.');

        $post = $transport->sent()[1];
        $this->assertSame('POST', $post['method']);
        $this->assertSame('https://nac.test/fhir/Bundle', $post['url']);
        $this->assertSame('Bearer nac-tok', $post['headers']['authorization']);
        $this->assertSame('application/fhir+json', $post['headers']['content-type']);
        $this->assertSame('{"resourceType":"Bundle"}', $post['content']);
    }

    public function testSubmitConError4xxNoEsRetryable(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"nac-tok","expires_in":3600}')
            ->respond(422, '{"resourceType":"OperationOutcome"}');

        $result = $this->connector($transport)->submitEncounterBundle($this->job(), '{}');

        $this->assertFalse($result->success);
        $this->assertFalse($result->retryable);
        $this->assertSame('HTTP 422 al enviar Bundle FHIR', $result->message);
    }

    public function testSubmitConError5xxEsRetryable(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"nac-tok","expires_in":3600}')
            ->respond(503, '{"resourceType":"OperationOutcome"}');

        $result = $this->connector($transport)->submitEncounterBundle($this->job(), '{}');

        $this->assertFalse($result->success);
        $this->assertTrue($result->retryable);
    }

    public function testRetryEn401InvalidaElTokenYRepiteUnaVez(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"tok-viejo","expires_in":3600}')
            ->respond(401, '{"resourceType":"OperationOutcome"}')
            ->respond(200, '{"access_token":"tok-nuevo","expires_in":3600}')
            ->respond(201, '', ['Location' => 'https://nac.test/fhir/Bundle/enc-9']);

        $result = $this->connector($transport)->submitEncounterBundle($this->job(), '{}');

        $this->assertTrue($result->success);

        $sent = $transport->sent();
        $this->assertCount(4, $sent, 'token + 401 + token nuevo + reintento');
        $this->assertSame('Bearer tok-viejo', $sent[1]['headers']['authorization']);
        $this->assertSame('Bearer tok-nuevo', $sent[3]['headers']['authorization']);
    }

    public function testPolling404EsNotFound(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"nac-tok","expires_in":3600}')
            ->respond(404, '{"resourceType":"OperationOutcome"}');

        $result = $this->connector($transport, ['statusPath' => '/fhir/Bundle/{id}'])
            ->pollSubmissionStatus($this->job());

        $this->assertTrue($result->supported);
        $this->assertFalse($result->found);
    }

    public function testPollingDevuelveEstadoRemoto(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"nac-tok","expires_in":3600}')
            ->respond(200, '{"id":"enc-9","status":"final"}');

        $result = $this->connector($transport, ['statusPath' => '/fhir/Bundle/{id}'])
            ->pollSubmissionStatus($this->job());

        $this->assertTrue($result->found);
        $this->assertSame('enc-9', $result->externalId);
        $this->assertSame('final', $result->status);
        $this->assertSame(
            'https://nac.test/fhir/Bundle/bioenlace-encounter-77',
            $transport->sent()[1]['url']
        );
    }

    public function testDeshabilitadoNoHaceRed(): void
    {
        $transport = new FakeTransport();

        $result = $this->connector($transport, ['enabled' => false])
            ->submitEncounterBundle($this->job(), '{}');

        $this->assertTrue($result->skipped);
        $this->assertCount(0, $transport->sent());
    }

    public function testSinCredencialesFallaSinHacerRed(): void
    {
        $transport = new FakeTransport();
        $connector = new HttpNationalClinicalHistoryConnector([
            'enabled' => true,
            'baseUrl' => 'https://nac.test',
            'httpClient' => $this->api($transport),
        ]);

        $this->expectException(ClinicalHistoryExchangeException::class);
        $this->expectExceptionMessage('clientId/clientSecret requeridos');

        $connector->submitEncounterBundle($this->job(), '{}');
    }

    public function testUsaLaClaveDeCacheHistorica(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"nac-tok","expires_in":3600}')
            ->respond(201, '', ['Location' => 'https://nac.test/fhir/Bundle/enc-1']);

        $this->connector($transport)->submitEncounterBundle($this->job(), '{}');

        $this->assertSame(
            'nac-tok',
            Yii::$app->cache->get(
                'clinical_history_exchange_token_' . md5('nacional-fhir' . 'cid')
            ),
            'El esquema de clave no cambia al desplegar: los tokens vigentes se reutilizan.'
        );
    }

    /** @param array<string, mixed> $extra */
    private function connector(FakeTransport $transport, array $extra = []): HttpNationalClinicalHistoryConnector
    {
        return new HttpNationalClinicalHistoryConnector($extra + [
            'enabled' => true,
            'baseUrl' => 'https://nac.test',
            'tokenUrl' => 'https://nac.test/oauth/token',
            'clientId' => 'cid',
            'clientSecret' => 'secret',
            'httpClient' => $this->api($transport),
        ]);
    }

    private function api(FakeTransport $transport): JsonApiClient
    {
        return new JsonApiClient(
            baseUrl: 'https://nac.test',
            connectTimeout: 1,
            timeout: 1,
            logCategory: 'clinical-history-exchange',
            retry: new RetryPolicy(0, 0, 0),
            provider: 'nacional-fhir',
            providerLabel: 'Historia clínica nacional',
            httpClient: new Client(['transport' => $transport]),
        );
    }

    private function job(): ClinicalHistoryOutboundJob
    {
        $job = new ClinicalHistoryOutboundJob();
        $job->id = 5;
        $job->encounter_id = 77;
        $job->external_id = null;

        return $job;
    }
}
