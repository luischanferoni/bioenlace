<?php

namespace common\components\Domain\Clinical\Capture\Domain\Port;

/**
 * Puerto: obtener texto clínico a partir de body (device STT / audio / texto).
 * Implementación en Infrastructure (delega a Platform/Ai).
 */
interface SpeechToTextPort
{
    public const PROVENANCE_DEVICE = 'device';
    public const PROVENANCE_SERVER = 'server';
    public const PROVENANCE_TEXT_ONLY = 'text_only';

    /**
     * @param array<string, mixed> $body
     * @return array{
     *   ok: bool,
     *   text: string,
     *   provenance: string,
     *   used_server_stt: bool,
     *   quality: array<string, mixed>|null,
     *   message: string|null
     * }
     */
    public function resolveFromBody(array $body, string $flowProfile = 'captura_clinica'): array;
}
