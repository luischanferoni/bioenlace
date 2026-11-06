<?php

namespace common\tests\unit\geo;

use Codeception\Test\Unit;
use common\components\Domain\Geo\Infrastructure\External\GoogleGeocoding\GoogleGeocodingApiClient;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;

/**
 * ACL de Google Geocoding: contrato del resolver de direcciones.
 *
 * `ZERO_RESULTS` es un resultado válido (sin warning); una falla sí loguea en `geo.geocoding`.
 */
class GoogleGeocodingApiClientTest extends Unit
{
    private FakeTransport $transport;

    protected function _before(): void
    {
        $this->transport = new FakeTransport();
        JsonApiClient::$transportOverride = $this->transport;
        Yii::$app->params['google_maps_geocoding_api_key'] = 'maps-key';
    }

    protected function _after(): void
    {
        JsonApiClient::$transportOverride = null;
        unset(
            Yii::$app->params['google_maps_geocoding_api_key'],
            Yii::$app->params['google_cloud_api_key']
        );
    }

    public function testSinApiKeyNoEstaConfiguradoYNoHaceRed(): void
    {
        Yii::$app->params['google_maps_geocoding_api_key'] = '';
        Yii::$app->params['google_cloud_api_key'] = '';

        $client = new GoogleGeocodingApiClient();

        $this->assertFalse($client->isConfigured());
        $this->assertNull($client->reverse(-31.4, -64.1));
        $this->assertCount(0, $this->transport->sent());
    }

    public function testCaeAGoogleCloudApiKeyCuandoNoHayKeyDeMaps(): void
    {
        unset(Yii::$app->params['google_maps_geocoding_api_key']);
        Yii::$app->params['google_cloud_api_key'] = 'cloud-key';

        $this->assertTrue((new GoogleGeocodingApiClient())->isConfigured());
    }

    public function testRespuestaOkDevuelveLaJerarquiaYNormalizaLosNiveles(): void
    {
        $this->transport->respond(200, json_encode([
            'status' => 'OK',
            'results' => [[
                'formatted_address' => 'Av. Colón 123, Córdoba, Argentina',
                'address_components' => [
                    ['long_name' => 'Argentina', 'short_name' => 'AR', 'types' => ['country']],
                    ['long_name' => 'Córdoba', 'short_name' => 'X', 'types' => ['administrative_area_level_1']],
                    ['long_name' => 'Capital', 'short_name' => 'Capital', 'types' => ['administrative_area_level_2']],
                    ['long_name' => 'Córdoba', 'short_name' => 'Córdoba', 'types' => ['locality']],
                ],
            ]],
        ]));

        $resultado = (new GoogleGeocodingApiClient())->reverse(-31.4135, -64.1810);

        $this->assertSame('Av. Colón 123, Córdoba, Argentina', $resultado['direccion']);
        $this->assertSame('Argentina', $resultado['pais']);
        $this->assertSame('AR', $resultado['pais_iso2']);
        $this->assertSame(['X', 'Córdoba'], $resultado['provincia']);
        $this->assertSame(['Capital'], $resultado['departamento']);
        $this->assertSame(['Córdoba'], $resultado['localidad']);

        $url = $this->transport->sent()[0]['url'];
        $this->assertStringContainsString('latlng=-31.4135000%2C-64.1810000', $url);
        $this->assertStringContainsString('key=maps-key', $url);
        $this->assertStringContainsString('language=es', $url);
    }

    public function testZeroResultsDevuelveNullSinWarning(): void
    {
        $logger = Yii::getLogger();
        $logger->getAndClearLog();
        $this->transport->respond(200, '{"status":"ZERO_RESULTS","results":[]}');

        $this->assertNull((new GoogleGeocodingApiClient())->reverse(0.0, 0.0));

        $log = method_exists($logger, 'getAndClearLog') ? $logger->getAndClearLog() : '';
        $this->assertStringNotContainsString('Google Geocoding status', $log);
    }

    public function testHttp500DevuelveNullYLogueaWarning(): void
    {
        $logger = Yii::getLogger();
        $logger->getAndClearLog();
        $this->transport->respond(500, '{"error":"boom"}');

        $this->assertNull((new GoogleGeocodingApiClient())->reverse(-31.4, -64.1));

        $log = method_exists($logger, 'getAndClearLog') ? $logger->getAndClearLog() : '';
        $this->assertStringContainsString('geo.geocoding', $log);
        $this->assertStringContainsString('Google Geocoding falló', $log);
        $this->assertStringNotContainsString('maps-key', $log, 'La API key no va al log.');
    }

    public function testTimeoutDelTransporteDevuelveNull(): void
    {
        $this->transport->fail('cURL error 28: Operation timed out');

        $this->assertNull((new GoogleGeocodingApiClient())->reverse(-31.4, -64.1));
    }
}
