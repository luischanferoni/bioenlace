<?php

namespace common\components\Platform\Ai\SpeechToText;

use Yii;

/**
 * Configuración declarativa de STT (params) — proveedor servidor, device vs cloud.
 *
 * @see web/docs/costos/estrategias-reduccion/stt.md
 */
final class SttConfigService
{
    public const PROVIDER_GROQ = 'groq';
    public const PROVIDER_HUGGINGFACE = 'huggingface';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        $stt = Yii::$app->params['stt'] ?? [];
        if (!is_array($stt)) {
            $stt = [];
        }

        $defaults = [
            'proveedor_servidor' => self::PROVIDER_GROQ,
            'device_enabled' => true,
            'server_enabled' => true,
            'groq_model' => 'whisper-large-v3-turbo',
            'groq_language' => 'es',
        ];

        return array_merge($defaults, $stt);
    }

    public static function isDeviceEnabled(): bool
    {
        $device = Yii::$app->params['stt_device'] ?? [];
        if (is_array($device) && array_key_exists('enabled', $device)) {
            return (bool) $device['enabled'];
        }

        return (bool) (self::config()['device_enabled'] ?? true);
    }

    public static function isServerEnabled(): bool
    {
        return (bool) (self::config()['server_enabled'] ?? true);
    }

    public static function serverProvider(): string
    {
        $provider = strtolower(trim((string) (self::config()['proveedor_servidor'] ?? self::PROVIDER_GROQ)));

        if ($provider === self::PROVIDER_HUGGINGFACE) {
            return self::PROVIDER_HUGGINGFACE;
        }

        return self::PROVIDER_GROQ;
    }

    public static function groqModel(): string
    {
        return trim((string) (self::config()['groq_model'] ?? 'whisper-large-v3-turbo'));
    }

    public static function groqLanguage(): string
    {
        return trim((string) (self::config()['groq_language'] ?? 'es'));
    }

    public static function huggingFaceModel(string $modeloAlias = 'economico'): string
    {
        $configured = Yii::$app->params['hf_stt_model'] ?? null;
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $map = [
            'economico' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish',
            'balanceado' => 'jonatasgrosman/wav2vec2-large-xlsr-53-spanish',
            'premium' => 'openai/whisper-large-v2',
        ];

        return $map[$modeloAlias] ?? $map['economico'];
    }

    public static function isServerProviderConfigured(): bool
    {
        if (!self::isServerEnabled()) {
            return false;
        }

        if (self::serverProvider() === self::PROVIDER_GROQ) {
            return !empty(Yii::$app->params['groq_api_key']);
        }

        return !empty(Yii::$app->params['hf_api_key']);
    }

    /**
     * Snapshot seguro para clientes (sin secretos).
     *
     * @return array<string, mixed>
     */
    public static function clientSnapshot(): array
    {
        $device = Yii::$app->params['stt_device'] ?? [];
        if (!is_array($device)) {
            $device = [];
        }
        $profilesOut = [];
        $profiles = $device['profiles'] ?? [];
        if (is_array($profiles)) {
            foreach ($profiles as $id => $cfg) {
                if (!is_array($cfg)) {
                    continue;
                }
                $profilesOut[(string) $id] = [
                    'min_confidence' => isset($cfg['min_confidence'])
                        ? (float) $cfg['min_confidence']
                        : (float) ($device['min_confidence'] ?? 0.75),
                ];
            }
        }

        return [
            'device_enabled' => self::isDeviceEnabled(),
            'server_enabled' => self::isServerEnabled(),
            'proveedor_servidor' => self::serverProvider(),
            'server_configured' => self::isServerProviderConfigured(),
            'min_confidence' => (float) ($device['min_confidence'] ?? 0.75),
            'profiles' => $profilesOut,
        ];
    }
}
