<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Pipeline\ClinicalCapturePipelineSupport;

/** Caso de uso: resolver path de audio para descarga. */
final class ResolveClinicalCaptureAudio
{
    private ClinicalCapturePipelineSupport $pipeline;

    public function __construct(?ClinicalCapturePipelineSupport $pipeline = null)
    {
        $this->pipeline = $pipeline ?? new ClinicalCapturePipelineSupport();
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function execute(array $query): array
    {
        $capture = $this->pipeline->findCapture($query, false);
        if (is_array($capture)) {
            return $capture;
        }
        if (!$capture->hasAudio()) {
            return $this->pipeline->fail(404, 'La captura no tiene audio.', $capture);
        }
        $absolute = $this->pipeline->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            return $this->pipeline->fail(404, 'Archivo de audio no encontrado.', $capture);
        }

        return [
            'path' => $absolute,
            'mime' => $capture->audio_mime ?: 'audio/mp4',
            'filename' => basename($absolute),
            'capture' => $capture,
        ];
    }
}
