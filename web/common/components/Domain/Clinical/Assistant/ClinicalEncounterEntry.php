<?php

namespace common\components\Domain\Clinical\Assistant;

use common\components\Domain\Clinical\Workflow\EncounterCapturePipelineService;
use common\components\Domain\Clinical\Workflow\EncounterDocumentationService;
use yii\web\UploadedFile;

/**
 * Captura clínica en encounter (texto/audio → análisis IA → guardar).
 * Pipeline por etapas: {@see EncounterCapturePipelineService} (síncrono, sin jobs).
 */
final class ClinicalEncounterEntry
{
    /**
     * POST /api/v1/clinical/encounter/analizar
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function analizar(array $body): array
    {
        return (new EncounterDocumentationService())->analizar($body);
    }

    /**
     * POST /api/v1/clinical/encounter/guardar
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function guardar(array $body): array
    {
        return (new EncounterDocumentationService())->guardar($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function capturaCrearOSubir(array $body, ?UploadedFile $file = null): array
    {
        return (new EncounterCapturePipelineService())->crearOSubir($body, $file);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function capturaTranscribir(array $body): array
    {
        return (new EncounterCapturePipelineService())->transcribir($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function capturaAnalizar(array $body): array
    {
        return (new EncounterCapturePipelineService())->analizar($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function capturaGuardar(array $body): array
    {
        return (new EncounterCapturePipelineService())->guardar($body);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function capturaListar(array $query): array
    {
        return (new EncounterCapturePipelineService())->listar($query);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function capturaVer(array $body): array
    {
        return (new EncounterCapturePipelineService())->ver($body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function capturaDescartar(array $body): array
    {
        return (new EncounterCapturePipelineService())->descartar($body);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{path: string, mime: string, filename: string}|array<string, mixed>
     */
    public static function capturaAudio(array $query)
    {
        return (new EncounterCapturePipelineService())->resolveAudioDownload($query);
    }
}
