<?php

namespace common\tests\unit\terminology;

use common\components\Domain\Terminology\Snomed\SnowstormClient;
use common\components\Domain\Terminology\Snomed\SnomedCodeSystem;
use common\components\Platform\Core\Product\SnomedTerminologyMetadata;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yii;
use yii\console\Application;

final class SnowstormClientTestable extends SnowstormClient
{
    public string $requestedUrl = '';
    public string $requestedVerb = '';

    /** @var array<string, string> */
    public array $requestedHeaders = [];

    /** @return array<string, mixed> */
    protected function request(string $url, string $verb, array $headers): array
    {
        $this->requestedUrl = $url;
        $this->requestedVerb = $verb;
        $this->requestedHeaders = $headers;

        return [
            'items' => [
                ['conceptId' => '123', 'pt' => ['term' => 'Medicamento']],
            ],
        ];
    }
}

final class SnowstormClientTest extends TestCase
{
    public function testUsesConfiguredEndpointAndBearerToken(): void
    {
        $client = new SnowstormClientTestable([
            'baseUrl' => 'https://terminology.example.test/MAIN',
            'token' => 'secret-token',
        ]);

        $result = $client->busquedaSinFiltrar('amoxicilina', 5);

        $this->assertSame('123', $result['items'][0]['conceptId']);
        $this->assertStringStartsWith(
            'https://terminology.example.test/MAIN/concepts?',
            $client->requestedUrl
        );
        $this->assertSame('GET', $client->requestedVerb);
        $this->assertSame('Bearer secret-token', $client->requestedHeaders['Authorization']);
        $this->assertSame('es', $client->requestedHeaders['Accept-Language']);
    }

    public function testOmitsAuthorizationForPublicServer(): void
    {
        $client = new SnowstormClientTestable([
            'baseUrl' => 'https://terminology.example.test/MAIN/',
            'token' => null,
        ]);

        $client->busquedaSinFiltrar('asma');

        $this->assertArrayNotHasKey('Authorization', $client->requestedHeaders);
    }

    public function testRejectsMissingBaseUrlBeforeSendingRequest(): void
    {
        $client = new SnowstormClientTestable(['baseUrl' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Snowstorm no tiene baseUrl configurada.');

        $client->busquedaSinFiltrar('asma');
    }

    public function testTreatmentRequestCodingIsDeclaredInProductMetadata(): void
    {
        if (Yii::$app === null) {
            new Application([
                'id' => 'snowstorm-test',
                'basePath' => Yii::getAlias('@common'),
            ]);
        }
        SnomedTerminologyMetadata::resetCacheForTests();
        $resources = SnomedTerminologyMetadata::config()['request_coding']['resources'];

        $this->assertSame('medicamentos', $resources['medication_request']['snomed_category']);
        $this->assertSame('procedimientos', $resources['service_request']['snomed_category']);
        $this->assertNotContains(
            'counseling',
            $resources['service_request']['allowed_categories']
        );
        $this->assertSame('http://snomed.info/sct', SnomedCodeSystem::URI);
    }
}
