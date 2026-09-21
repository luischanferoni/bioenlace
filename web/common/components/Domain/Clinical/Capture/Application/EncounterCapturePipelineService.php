<?php

namespace common\components\Domain\Clinical\Capture\Application;

use common\components\Domain\Clinical\Capture\Application\Checkpoint\ClinicalCaptureCheckpoint;
use common\components\Domain\Clinical\Capture\Application\UseCase\AnalyzeClinicalCaptureDraft;
use common\components\Domain\Clinical\Capture\Application\UseCase\ApplyClinicalCaptureResolutions;
use common\components\Domain\Clinical\Capture\Application\UseCase\CreateOrUploadClinicalCapture;
use common\components\Domain\Clinical\Capture\Application\UseCase\DiscardClinicalCapture;
use common\components\Domain\Clinical\Capture\Application\UseCase\ListClinicalCaptures;
use common\components\Domain\Clinical\Capture\Application\UseCase\ResolveClinicalCaptureAudio;
use common\components\Domain\Clinical\Capture\Application\UseCase\SaveClinicalCapture;
use common\components\Domain\Clinical\Capture\Application\UseCase\TranscribeClinicalCapture;
use common\components\Domain\Clinical\Capture\Application\UseCase\ViewClinicalCapture;
use common\models\Clinical\EncounterCapture;
use yii\web\UploadedFile;

/**
 * Facade de compatibilidad hacia los use cases de captura.
 * Preferir los casos de uso (`CreateOrUploadClinicalCapture`, `TranscribeClinicalCapture`, …).
 */
final class EncounterCapturePipelineService
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
    public function crearOSubir(array $body, ?UploadedFile $file = null): array
    {
        return (new CreateOrUploadClinicalCapture($this->checkpoint))->execute($body, $file);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function transcribir(array $body): array
    {
        return (new TranscribeClinicalCapture($this->checkpoint))->execute($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function analizar(array $body): array
    {
        return (new AnalyzeClinicalCaptureDraft($this->checkpoint))->execute($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function guardar(array $body): array
    {
        return (new SaveClinicalCapture($this->checkpoint))->execute($body);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listar(array $query): array
    {
        return (new ListClinicalCaptures($this->checkpoint))->execute($query);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function ver(array $body): array
    {
        return (new ViewClinicalCapture($this->checkpoint))->execute($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function descartar(array $body): array
    {
        return (new DiscardClinicalCapture($this->checkpoint))->execute($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function aplicarResoluciones(array $body): array
    {
        return (new ApplyClinicalCaptureResolutions($this->checkpoint))->execute($body);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function resolveAudioDownload(array $query): array
    {
        return (new ResolveClinicalCaptureAudio($this->checkpoint))->execute($query);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(EncounterCapture $capture, bool $includeAnalysis = false): array
    {
        return $this->checkpoint->toApiArray($capture, $includeAnalysis);
    }
}
