<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Support\ClinicalCaptureSupport;

/** Caso de uso: resolver path de audio para descarga. */
final class ResolveClinicalCaptureAudio
{
    private ClinicalCaptureSupport $support;

    public function __construct(?ClinicalCaptureSupport $support = null)
    {
        $this->support = $support ?? new ClinicalCaptureSupport();
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function execute(array $query): array
    {
        $capture = $this->support->findCapture($query, false);
        if (is_array($capture)) {
            return $capture;
        }
        if (!$capture->hasAudio()) {
            return $this->support->fail(404, 'La captura no tiene audio.', $capture);
        }
        $absolute = $this->support->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            return $this->support->fail(404, 'Archivo de audio no encontrado.', $capture);
        }

        return [
            'path' => $absolute,
            'mime' => $capture->audio_mime ?: 'audio/mp4',
            'filename' => basename($absolute),
            'capture' => $capture,
        ];
    }
}
