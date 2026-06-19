<?php

namespace common\tests\unit\ai;

use common\components\Domain\Clinical\SpeechToText\ClinicalSpeechInputResolver;
use common\components\Platform\Ai\SpeechToText\SttConfigService;

class SttConfigServiceTest extends \Codeception\Test\Unit
{
    protected function _before(): void
    {
        \Yii::$app->params['stt'] = [
            'proveedor_servidor' => 'groq',
            'device_enabled' => true,
            'server_enabled' => true,
            'groq_model' => 'whisper-large-v3-turbo',
            'groq_language' => 'es',
        ];
        \Yii::$app->params['stt_device'] = ['enabled' => true];
        \Yii::$app->params['groq_api_key'] = 'gsk_test';
        \Yii::$app->params['hf_api_key'] = '';
    }

    public function testProveedorGroqPorDefectoEnParamsDePrueba(): void
    {
        verify(SttConfigService::serverProvider())->equals(SttConfigService::PROVIDER_GROQ);
        verify(SttConfigService::groqModel())->equals('whisper-large-v3-turbo');
    }

    public function testClientSnapshotSinSecretos(): void
    {
        $snap = SttConfigService::clientSnapshot();
        verify($snap)->arrayHasKey('device_enabled');
        verify($snap)->arrayHasKey('server_enabled');
        verify($snap)->arrayHasKey('proveedor_servidor');
        verify($snap)->arrayHasKey('server_configured');
        verify($snap['server_configured'])->true();
        verify($snap)->arrayNotHasKey('groq_api_key');
    }

    public function testDeviceDeshabilitadoOmiteEvaluacionDevice(): void
    {
        \Yii::$app->params['stt_device'] = ['enabled' => false];

        $r = ClinicalSpeechInputResolver::resolveFromBody([
            'consulta' => 'Paciente con dolor torácico desde ayer.',
            'stt' => [
                'provenance' => 'device',
                'text' => 'Paciente con dolor torácico desde ayer.',
                'confidence' => 0.3,
                'engine' => 'web_speech',
            ],
        ]);

        verify($r['ok'])->true();
        verify($r['provenance'])->equals(ClinicalSpeechInputResolver::PROVENANCE_TEXT_ONLY);
        verify($r['used_server_stt'])->false();
    }

    public function testServerDeshabilitadoRechazaForceServer(): void
    {
        \Yii::$app->params['stt']['server_enabled'] = false;

        $r = ClinicalSpeechInputResolver::resolveFromBody([
            'audio' => base64_encode('fake-audio'),
            'stt_force_server' => true,
        ]);

        verify($r['ok'])->false();
        verify($r['message'])->stringContainsString('deshabilitada');
    }
}
