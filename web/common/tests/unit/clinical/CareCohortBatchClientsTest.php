<?php

namespace common\tests\unit\clinical;

use Codeception\Test\Unit;
use common\components\Domain\Clinical\CareCohort\Infrastructure\External\Gcs\GcsApiClient;
use common\components\Domain\Clinical\CareCohort\Infrastructure\External\Vertex\VertexBatchApiClient;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;

/**
 * ACL de batch: GCS (subida/descarga de JSONL) y Vertex (creación/consulta de jobs).
 *
 * Cubre el contrato "false/null ante error" sin red.
 */
class CareCohortBatchClientsTest extends Unit
{
    private FakeTransport $transport;

    private string $credentialsFile = '';

    protected function _before(): void
    {
        $this->transport = new FakeTransport();
        JsonApiClient::$transportOverride = $this->transport;
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);

        $this->credentialsFile = Yii::getAlias('@runtime') . '/test-google-creds.json';
        file_put_contents($this->credentialsFile, json_encode([
            'project_id' => 'proyecto-test',
            'client_email' => 'cuenta@test.iam.gserviceaccount.com',
            'private_key' => 'no-se-usa-con-token-cacheado',
        ]));

        Yii::$app->params['google_cloud_api_key'] = '';
        Yii::$app->params['google_cloud_credentials_path'] = $this->credentialsFile;
        Yii::$app->params['google_cloud_project_id'] = 'proyecto-test';
        Yii::$app->params['google_cloud_region'] = 'us-central1';
    }

    protected function _after(): void
    {
        JsonApiClient::$transportOverride = null;
        if ($this->credentialsFile !== '' && is_file($this->credentialsFile)) {
            @unlink($this->credentialsFile);
        }
    }

    public function testSinTokenNoHaceRedYDevuelveFalse(): void
    {
        $this->assertFalse(
            (new GcsApiClient())->uploadString('bucket', 'care-batch/input/a.jsonl', '{"a":1}')
        );
        $this->assertNull((new VertexBatchApiClient())->createGeminiBatchJob('gs://b/in.jsonl', 'gs://b/out'));
        $this->assertCount(0, $this->transport->sent());
    }

    public function testSubidaGcsUsaUploadTypeMediaYNombreDelObjeto(): void
    {
        $this->fakeToken();
        $this->transport->respond(200, '{"name":"care-batch/input/a.jsonl"}');

        $this->assertTrue(
            (new GcsApiClient())->uploadString('bucket', 'care-batch/input/a.jsonl', "{\"a\":1}\n")
        );

        $sent = $this->transport->sent()[0];
        $this->assertSame('POST', $sent['method']);
        $this->assertStringContainsString('/upload/storage/v1/b/bucket/o', $sent['url']);
        $this->assertStringContainsString('uploadType=media', $sent['url']);
        $this->assertStringContainsString('name=care-batch%2Finput%2Fa.jsonl', $sent['url']);
        $this->assertSame('application/jsonl', $sent['headers']['content-type']);
        $this->assertSame("{\"a\":1}\n", $sent['content']);
    }

    public function testListadoGcsDevuelveNombres(): void
    {
        $this->fakeToken();
        $this->transport->respond(200, '{"items":[{"name":"care-batch/out/a.jsonl"},{"name":"care-batch/out/b.txt"}]}');

        $names = (new GcsApiClient())->listObjectNames('bucket', 'care-batch/out/', 10);

        $this->assertSame(['care-batch/out/a.jsonl', 'care-batch/out/b.txt'], $names);
        $this->assertStringContainsString('prefix=care-batch%2Fout%2F', $this->transport->sent()[0]['url']);
    }

    public function testCreacionDeJobVertexMandaElPayloadDeBatch(): void
    {
        $this->fakeToken();
        $this->transport->respond(200, '{"name":"projects/p/locations/us-central1/batchPredictionJobs/123"}');

        $created = (new VertexBatchApiClient())->createGeminiBatchJob(
            'gs://bucket/in/batch.jsonl',
            'gs://bucket/out/batch'
        );

        $this->assertSame('projects/p/locations/us-central1/batchPredictionJobs/123', $created['name']);

        $sent = $this->transport->sent()[0];
        $this->assertSame(
            'https://us-central1-aiplatform.googleapis.com/v1/projects/proyecto-test/locations/us-central1/batchPredictionJobs',
            $sent['url']
        );

        $payload = json_decode((string) $sent['content'], true);
        $this->assertSame('publishers/google/models/gemini-2.5-flash-lite', $payload['model']);
        $this->assertSame(['gs://bucket/in/batch.jsonl'], $payload['inputConfig']['gcsSource']['uris']);
        $this->assertSame('gs://bucket/out/batch/', $payload['outputConfig']['gcsDestination']['outputUriPrefix']);
    }

    public function testErrorHttpEnVertexDevuelveNull(): void
    {
        $this->fakeToken();
        $this->transport->respond(403, '{"error":{"message":"permission denied"}}');

        $this->assertNull(
            (new VertexBatchApiClient())->createGeminiBatchJob('gs://b/in.jsonl', 'gs://b/out')
        );
    }

    public function testGetJobUsaLaRegionDelNombreDelJob(): void
    {
        $this->fakeToken();
        $this->transport->respond(200, '{"state":"JOB_STATE_SUCCEEDED"}');

        $job = (new VertexBatchApiClient())->getJob('projects/p/locations/europe-west4/batchPredictionJobs/9');

        $this->assertSame('JOB_STATE_SUCCEEDED', $job['state']);
        $this->assertSame(
            'https://europe-west4-aiplatform.googleapis.com/v1/projects/p/locations/europe-west4/batchPredictionJobs/9',
            $this->transport->sent()[0]['url']
        );
    }

    private function fakeToken(): void
    {
        // Token cacheado con la clave real del provider: evita firmar JWT y usar red de auth.
        Yii::$app->cache->set(
            'google_oauth_token_' . md5((string) realpath($this->credentialsFile)),
            'tok-gcs'
        );
    }
}
