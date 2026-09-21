<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Checkpoint\ClinicalCaptureCheckpoint;

/** Caso de uso: ver captura con review. */
final class ViewClinicalCapture
{
    private ClinicalCaptureCheckpoint $checkpoint;

    public function __construct(?ClinicalCaptureCheckpoint $checkpoint = null)
    {
        $this->checkpoint = $checkpoint ?? new ClinicalCaptureCheckpoint();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {

        $capture = $this->checkpoint->findCapture($body, false);
        if (is_array($capture)) {
            return $capture;
        }

        return $this->checkpoint->ok($capture, 'OK', true);
    }
}
