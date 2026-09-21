<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Support\ClinicalCaptureSupport;

/** Caso de uso: ver captura con review. */
final class ViewClinicalCapture
{
    private ClinicalCaptureSupport $support;

    public function __construct(?ClinicalCaptureSupport $support = null)
    {
        $this->support = $support ?? new ClinicalCaptureSupport();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {

        $capture = $this->support->findCapture($body, false);
        if (is_array($capture)) {
            return $capture;
        }

        return $this->support->ok($capture, 'OK', true);
    }
}
