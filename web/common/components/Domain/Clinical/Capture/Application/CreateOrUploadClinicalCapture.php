<?php

namespace common\components\Domain\Clinical\Capture\Application;

use common\components\Domain\Clinical\Capture\Application\Pipeline\ClinicalCapturePipelineSupport;
use yii\web\UploadedFile;

/** Caso de uso: crear/actualizar captura + audio/transcript inicial. */
final class CreateOrUploadClinicalCapture
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
    public function execute(array $body, ?UploadedFile $file = null): array
    {
        return $this->pipeline->crearOSubir($body, $file);
    }
}
