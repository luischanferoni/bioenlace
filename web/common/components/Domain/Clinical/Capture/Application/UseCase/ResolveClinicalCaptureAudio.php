<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\ClinicalCaptureCheckpoint;

/** Caso de uso: resolver path de audio para descarga. */
final class ResolveClinicalCaptureAudio
{
    private ClinicalCaptureCheckpoint $checkpoint;

    public function __construct(?ClinicalCaptureCheckpoint $checkpoint = null)
    {
        $this->checkpoint = $checkpoint ?? new ClinicalCaptureCheckpoint();
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function execute(array $query): array
    {
        $capture = $this->checkpoint->findCapture($query, false);
        if (is_array($capture)) {
            return $capture;
        }
        if (!$capture->hasAudio()) {
            return $this->checkpoint->fail(404, 'La captura no tiene audio.', $capture);
        }
        $absolute = $this->checkpoint->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            return $this->checkpoint->fail(404, 'Archivo de audio no encontrado.', $capture);
        }

        return [
            'path' => $absolute,
            'mime' => $capture->audio_mime ?: 'audio/mp4',
            'filename' => basename($absolute),
            'capture' => $capture,
        ];
    }
}
