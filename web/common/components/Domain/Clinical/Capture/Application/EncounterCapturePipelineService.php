<?php

namespace common\components\Domain\Clinical\Capture\Application;

use common\components\Domain\Clinical\Capture\Application\Pipeline\ClinicalCapturePipelineSupport;
use common\models\Clinical\EncounterCapture;
use yii\web\UploadedFile;

/**
 * Facade de compatibilidad del pipeline de captura.
 * Preferir los casos de uso (`CreateOrUploadClinicalCapture`, `TranscribeClinicalCapture`, …).
 */
final class EncounterCapturePipelineService
{
    private ClinicalCapturePipelineSupport $support;

    public function __construct(?ClinicalCapturePipelineSupport $support = null)
    {
        $this->support = $support ?? new ClinicalCapturePipelineSupport();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function crearOSubir(array $body, ?UploadedFile $file = null): array
    {
        return (new CreateOrUploadClinicalCapture($this->support))->execute($body, $file);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function transcribir(array $body): array
    {
        return (new TranscribeClinicalCapture($this->support))->execute($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function analizar(array $body): array
    {
        return (new AnalyzeClinicalCaptureDraft($this->support))->execute($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function guardar(array $body): array
    {
        return (new SaveClinicalCapture($this->support))->execute($body);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listar(array $query): array
    {
        return (new ListClinicalCaptures($this->support))->execute($query);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function ver(array $body): array
    {
        return (new ViewClinicalCapture($this->support))->execute($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function descartar(array $body): array
    {
        return (new DiscardClinicalCapture($this->support))->execute($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function aplicarResoluciones(array $body): array
    {
        return (new ApplyClinicalCaptureResolutions($this->support))->execute($body);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function resolveAudioDownload(array $query): array
    {
        return (new ResolveClinicalCaptureAudio($this->support))->execute($query);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(EncounterCapture $capture, bool $includeAnalysis = false): array
    {
        return $this->support->toApiArray($capture, $includeAnalysis);
    }
}
