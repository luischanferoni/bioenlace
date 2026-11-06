<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Connector\MsalNisFhirSchedulingConnector;
use common\components\Domain\Scheduling\Agenda\Infrastructure\External\NisFhir\Exception\FhirSchedulingConnectorException;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\components\Shared\Infrastructure\Http\RetryPolicy;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;
use yii\httpclient\Client;

/**
 * ACL NIS FHIR: modo abierto vs OAuth, query FHIR y actualización de estado.
 *
 * @see MsalNisFhirSchedulingConnector
 */
class MsalNisFhirSchedulingConnectorTest extends Unit
{
    protected function _before(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    protected function _after(): void
    {
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    public function testSinTokenUrlNoPideTokenNiMandaAuthorization(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"resourceType":"Bundle","entry":[]}');

        $bundle = $this->connector($transport)->searchAppointments(['patient' => 'p-1']);

        $this->assertSame('Bundle', $bundle['resourceType']);

        $sent = $transport->sent();
        $this->assertCount(1, $sent, 'Modo abierto: no hay request de token.');
        $this->assertArrayNotHasKey('authorization', $sent[0]['headers']);
    }

    public function testSearchAppointmentsArmaQueryFhir(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"resourceType":"Bundle","entry":[]}');

        $this->connector($transport)->searchAppointments([
            'patient' => 'p-1',
            'date' => 'ge2026-01-01',
            'vacio' => '',
            'nulo' => null,
        ]);

        $url = $transport->sent()[0]['url'];
        $this->assertStringContainsString('/fhir/Appointment?', $url);
        $this->assertStringContainsString('patient=p-1', $url);
        $this->assertStringContainsString('date=ge2026-01-01', $url);
        $this->assertStringNotContainsString('vacio=', $url, 'Los params vacíos se descartan.');
        $this->assertStringNotContainsString('nulo=', $url);
    }

    public function testConTokenUrlUsaElProviderCompartido(): void
    {
        $transport = (new FakeTransport())
            ->respond(200, '{"access_token":"nis-tok","expires_in":3600}')
            ->respond(200, '{"resourceType":"Bundle","entry":[]}');

        $connector = $this->connector($transport, [
            'tokenUrl' => 'https://nis.test/oauth/token',
            'clientId' => 'cid',
            'clientSecret' => 'secret',
        ]);
        $connector->searchSchedules();

        $sent = $transport->sent();
        $this->assertCount(2, $sent);
        $this->assertSame('https://nis.test/oauth/token', $sent[0]['url']);
        $this->assertSame('Bearer nis-tok', $sent[1]['headers']['authorization']);
    }

    public function testUpdateAppointmentMandaFhirJsonYConservaElContentType(): void
    {
        $appointment = json_encode([
            'resourceType' => 'Appointment',
            'id' => 'a-1',
            'status' => 'booked',
        ]);
        $updated = json_encode([
            'resourceType' => 'Appointment',
            'id' => 'a-1',
            'status' => 'cancelled',
        ]);
        $transport = (new FakeTransport())
            ->respond(200, (string) $appointment)
            ->respond(200, (string) $updated);

        $result = $this->connector($transport)->updateAppointmentStatus('a-1', 'CANCELLED');

        $this->assertSame('cancelled', $result['status']);

        $put = $transport->sent()[1];
        $this->assertSame('PUT', $put['method']);
        $this->assertStringContainsString('application/fhir+json', (string) $put['headers']['content-type']);
        $this->assertSame(
            'cancelled',
            json_decode((string) $put['content'], true)['status'] ?? null
        );
    }

    public function testHttp404SeTraduceAMensajeDelConector(): void
    {
        $transport = (new FakeTransport())->respond(404, '{"resourceType":"OperationOutcome"}');

        try {
            $this->connector($transport)->readSchedule('s-1');
            $this->fail('Se esperaba FhirSchedulingConnectorException.');
        } catch (FhirSchedulingConnectorException $e) {
            $this->assertSame('NIS FHIR HTTP 404 en Schedule/s-1', $e->getMessage());
        }
    }

    public function testReadScheduleMandaIncludeComoParametro(): void
    {
        $transport = (new FakeTransport())->respond(200, '{"resourceType":"Schedule","id":"s-1"}');

        $this->connector($transport)->readSchedule('s-1', ['Schedule:actor', 'Schedule:serviceType']);

        $url = rawurldecode($transport->sent()[0]['url']);
        $this->assertStringContainsString('_include=Schedule:actor,Schedule:serviceType', $url);
    }

    /** @param array<string, mixed> $extra */
    private function connector(FakeTransport $transport, array $extra = []): MsalNisFhirSchedulingConnector
    {
        return new MsalNisFhirSchedulingConnector($extra + [
            'baseUrl' => 'https://nis.test/fhir',
            'httpClient' => new JsonApiClient(
                baseUrl: 'https://nis.test/fhir',
                connectTimeout: 1,
                timeout: 1,
                logCategory: 'fhir-scheduling-inbound',
                retry: new RetryPolicy(0, 0, 0),
                provider: 'msal-nis',
                providerLabel: 'NIS FHIR',
                httpClient: new Client(['transport' => $transport]),
            ),
        ]);
    }
}
