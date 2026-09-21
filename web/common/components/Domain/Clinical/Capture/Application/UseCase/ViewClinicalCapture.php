<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Pipeline\ClinicalCapturePipelineSupport;

/** Caso de uso: ver captura con review. */
final class ViewClinicalCapture
{
    private ClinicalCapturePipelineSupport $pipeline;

    public function __construct(?ClinicalCapturePipelineSupport $pipeline = null)
    {
        $this->pipeline = $pipeline ?? new ClinicalCapturePipelineSupport();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {

        $capture = $this->pipeline->findCapture($body, false);
        if (is_array($capture)) {
            return $capture;
        }

        return $this->pipeline->ok($capture, 'OK', true);
    }
}
