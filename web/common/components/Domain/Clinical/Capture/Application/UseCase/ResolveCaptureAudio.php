<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\CaptureDraftService;

/** Caso de uso: resolver path de audio para descarga. */
final class ResolveCaptureAudio
{
    private CaptureDraftService $draft;

    public function __construct(?CaptureDraftService $draft = null)
    {
        $this->draft = $draft ?? new CaptureDraftService();
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function execute(array $query): array
    {
        $capture = $this->draft->findCapture($query, false);
        if (!$capture->hasAudio()) {
            throw $this->draft->failure('capture_no_audio', $capture);
        }
        $absolute = $this->draft->absoluteAudioPath($capture);
        if ($absolute === null || !is_file($absolute)) {
            throw $this->draft->failure('audio_file_not_found', $capture);
        }

        return [
            'path' => $absolute,
            'mime' => $capture->audio_mime ?: 'audio/mp4',
            'filename' => basename($absolute),
            'capture' => $capture,
        ];
    }
}
