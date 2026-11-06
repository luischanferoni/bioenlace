<?php

namespace common\tests\unit\platform\ai;

use Codeception\Test\Unit;
use common\components\Platform\Ai\Embeddings\HuggingFaceEmbeddingsClient;
use common\components\Platform\Ai\SpeechToText\GroqSttClient;
use common\components\Platform\Ai\SpeechToText\HuggingFaceSttClient;
use common\components\Shared\Infrastructure\Http\JsonApiClient;
use common\tests\unit\shared\infrastructure\http\Support\FakeTransport;
use Yii;

/**
 * Familias IA y STT: payload, auth y "devolver null" ante error (contrato del facade).
 *
 * @see HuggingFaceEmbeddingsClient
 * @see HuggingFaceSttClient
 * @see GroqSttClient
 */
class PlatformAiHttpClientsTest extends Unit
{
    private FakeTransport $transport;

    protected function _before(): void
    {
        $this->transport = new FakeTransport();
        JsonApiClient::$transportOverride = $this->transport;
        Yii::$app->set('cache', ['class' => \yii\caching\ArrayCache::class]);
    }

    protected function _after(): void
    {
        JsonApiClient::$transportOverride = null;
    }

    public function testEmbeddingMandaBearerYDevuelveElVector(): void
    {
        Yii::$app->params['hf_api_key'] = 'hf-key';
        $this->transport->respond(200, '[0.1,0.2,0.3]');

        $resultado = HuggingFaceEmbeddingsClient::featureExtraction('dolor torácico', 'mini-lm');

        $this->assertSame([0.1, 0.2, 0.3], $resultado);

        $sent = $this->transport->sent()[0];
        $this->assertSame('https://router.huggingface.co/hf-inference/models/mini-lm', $sent['url']);
        $this->assertSame('Bearer hf-key', $sent['headers']['authorization']);
        $this->assertSame(
            ['inputs' => 'dolor torácico', 'options' => ['wait_for_model' => false]],
            json_decode((string) $sent['content'], true)
        );
    }

    public function testEmbeddingDevuelveNullConErrorHttp(): void
    {
        Yii::$app->params['hf_api_key'] = 'hf-key';
        $this->transport->respond(402, '{"error":"quota exceeded"}');

        $this->assertNull(HuggingFaceEmbeddingsClient::featureExtraction('texto', 'mini-lm'));
    }

    public function testEmbeddingSinApiKeyNoHaceRed(): void
    {
        Yii::$app->params['hf_api_key'] = '';

        $this->assertNull(HuggingFaceEmbeddingsClient::featureExtraction('texto', 'mini-lm'));
        $this->assertCount(0, $this->transport->sent());
    }

    public function testSttHuggingFaceDevuelveTexto(): void
    {
        Yii::$app->params['hf_api_key'] = 'hf-key';
        $this->transport->respond(200, '{"text":"hola paciente"}');

        $resultado = HuggingFaceSttClient::transcribe('RIFFxxxx', 'whisper');

        $this->assertSame('hola paciente', $resultado['texto']);
        $this->assertSame(
            base64_encode('RIFFxxxx'),
            json_decode((string) $this->transport->sent()[0]['content'], true)['inputs']
        );
    }

    public function testSttHuggingFaceDevuelveNullConErrorHttp(): void
    {
        Yii::$app->params['hf_api_key'] = 'hf-key';
        $this->transport->respond(503, 'unavailable');

        $this->assertNull(HuggingFaceSttClient::transcribe('RIFFxxxx', 'whisper'));
    }

    public function testGroqSubeElAudioComoMultipartSinArchivoTemporal(): void
    {
        Yii::$app->params['groq_api_key'] = 'groq-key';
        $this->transport->respond(200, '{"text":"consulta de control"}');

        $resultado = GroqSttClient::transcribe('RIFFaudio', 'whisper-large-v3-turbo');

        $this->assertSame('consulta de control', $resultado['texto']);

        $sent = $this->transport->sent()[0];
        $this->assertSame('https://api.groq.com/openai/v1/audio/transcriptions', $sent['url']);
        $this->assertSame('Bearer groq-key', $sent['headers']['authorization']);
        $this->assertStringContainsString('multipart/form-data', (string) $sent['headers']['content-type']);
        $this->assertStringContainsString('filename="audio.wav"', (string) $sent['content']);
        $this->assertStringContainsString('name="model"', (string) $sent['content']);
        $this->assertStringContainsString('whisper-large-v3-turbo', (string) $sent['content']);
        $this->assertStringContainsString('RIFFaudio', (string) $sent['content']);
    }

    public function testGroqDevuelveNullConErrorHttp(): void
    {
        Yii::$app->params['groq_api_key'] = 'groq-key';
        $this->transport->respond(500, '{"error":"boom"}');

        $this->assertNull(GroqSttClient::transcribe('RIFFaudio', 'whisper'));
    }
}
