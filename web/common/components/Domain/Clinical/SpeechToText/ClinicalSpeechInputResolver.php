<?php

namespace common\components\Domain\Clinical\SpeechToText;

use common\components\Platform\Ai\SpeechToText\DeviceSttQualityAssessor;
use common\components\Platform\Ai\SpeechToText\SpeechToTextManager;
use common\components\Platform\Ai\SpeechToText\SttConfigService;
use Yii;

/**
 * Resuelve texto de captura clínica: dispositivo primero, STT servidor si hace falta.
 */
final class ClinicalSpeechInputResolver
{
    public const PROVENANCE_DEVICE = 'device';
    public const PROVENANCE_SERVER = 'server';
    public const PROVENANCE_TEXT_ONLY = 'text_only';

    /**
     * @param array<string, mixed> $body Request analizar / transcribir
     * @return array{
     *   ok: bool,
     *   text: string,
     *   provenance: string,
     *   used_server_stt: bool,
     *   quality: array<string, mixed>|null,
     *   message: string|null
     * }
     */
    public static function resolveFromBody(array $body, string $flowProfile = 'captura_clinica'): array
    {
        $consulta = trim((string) ($body['consulta'] ?? $body['consulta_texto'] ?? ''));
        $stt = self::normalizeSttBlock($body['stt'] ?? null);
        $audio = $body['audio'] ?? $body['audio_data'] ?? null;
        $forceServer = !empty($stt['force_server']) || !empty($body['stt_force_server']);

        $deviceText = trim((string) ($stt['text'] ?? ''));
        $primaryText = $consulta !== '' ? $consulta : $deviceText;

        if ($primaryText === '' && empty($audio)) {
            return self::fail('Falta el texto de la consulta o audio para transcribir.');
        }

        if ($primaryText !== '' && !$forceServer && self::shouldEvaluateDevice($stt)) {
            if ($consulta !== '' && $deviceText !== '' && $consulta !== $deviceText) {
                return self::ok($primaryText, self::PROVENANCE_TEXT_ONLY, false, null);
            }
        }

        if ($primaryText !== '' && !$forceServer && !self::shouldEvaluateDevice($stt)) {
            return self::ok($primaryText, self::PROVENANCE_TEXT_ONLY, false, null);
        }

        if ($forceServer && !SttConfigService::isServerEnabled()) {
            return self::fail('La transcripción en servidor está deshabilitada por configuración.');
        }

        if ($primaryText !== '' && !$forceServer && self::shouldEvaluateDevice($stt)) {
            $quality = DeviceSttQualityAssessor::assess($primaryText, $stt, $flowProfile);
            if ($quality['ok']) {
                self::logRoute(self::PROVENANCE_DEVICE, $quality, false);

                return self::ok($primaryText, self::PROVENANCE_DEVICE, false, $quality);
            }
            if (!empty($audio)) {
                if (!SttConfigService::isServerEnabled()) {
                    return self::fail(
                        'La transcripción del dispositivo no es confiable y el STT en servidor está deshabilitado.',
                        $quality
                    );
                }
                $serverText = self::transcribeServer($audio, (string) ($body['modelo'] ?? 'economico'));
                if ($serverText !== '') {
                    self::logRoute(self::PROVENANCE_SERVER, $quality, true);

                    return self::ok($serverText, self::PROVENANCE_SERVER, true, $quality);
                }

                return self::fail(
                    'No se pudo mejorar la transcripción en servidor. Revise el audio o escriba el texto.',
                    $quality
                );
            }

            Yii::info([
                'provenance' => self::PROVENANCE_TEXT_ONLY,
                'stt_quality_bypass' => true,
                'quality' => $quality,
            ], 'stt-routing');

            return self::ok($primaryText, self::PROVENANCE_TEXT_ONLY, false, $quality);
        }

        if ($forceServer || ($primaryText === '' && !empty($audio))) {
            if (empty($audio)) {
                return self::fail('Se solicitó transcripción en servidor pero no se envió audio.');
            }
            $serverText = self::transcribeServer($audio, (string) ($body['modelo'] ?? 'economico'));
            if ($serverText === '') {
                return self::fail('No se pudo transcribir el audio en servidor.');
            }
            self::logRoute(self::PROVENANCE_SERVER, null, true);

            return self::ok($serverText, self::PROVENANCE_SERVER, true, null);
        }

        return self::ok($primaryText, self::PROVENANCE_TEXT_ONLY, false, null);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private static function normalizeSttBlock($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $stt
     */
    private static function shouldEvaluateDevice(array $stt): bool
    {
        if (!SttConfigService::isDeviceEnabled()) {
            return false;
        }
        if (($stt['provenance'] ?? '') === self::PROVENANCE_DEVICE) {
            return true;
        }
        if (!empty($stt['engine'])) {
            return true;
        }

        return false;
    }

    private static function transcribeServer($audio, string $modelo): string
    {
        if (!SttConfigService::isServerEnabled()) {
            return '';
        }
        $result = SpeechToTextManager::transcribir($audio, $modelo);

        return trim((string) ($result['texto'] ?? ''));
    }

    /**
     * @param array<string, mixed>|null $quality
     * @return array<string, mixed>
     */
    private static function ok(string $text, string $provenance, bool $usedServer, ?array $quality): array
    {
        return [
            'ok' => true,
            'text' => $text,
            'provenance' => $provenance,
            'used_server_stt' => $usedServer,
            'quality' => $quality,
            'message' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $quality
     * @return array<string, mixed>
     */
    private static function fail(string $message, ?array $quality = null): array
    {
        return [
            'ok' => false,
            'text' => '',
            'provenance' => '',
            'used_server_stt' => false,
            'quality' => $quality,
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed>|null $quality
     */
    private static function logRoute(string $provenance, ?array $quality, bool $usedServer): void
    {
        Yii::info([
            'provenance' => $provenance,
            'used_server_stt' => $usedServer,
            'quality' => $quality,
        ], 'stt-routing');
    }
}
