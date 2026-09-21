<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\RowContract\ClinicalCaptureRowContracts;
use common\components\Domain\Clinical\Capture\Application\Pipeline\ClinicalCapturePipelineSupport;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EncounterCaptureReviewPresenter;
use common\models\Clinical\EncounterCaptureAudit;

/** Caso de uso: aplicar resoluciones de issues sobre datosExtraidos. */
final class ApplyClinicalCaptureResolutions
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
        $capture = $this->pipeline->findOpenCapture($body);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->pipeline->captureRows()->toAggregate($capture);
        if (!in_array($domain->stage(), [
            ClinicalCaptureStage::READY_FOR_REVIEW,
            ClinicalCaptureStage::SAVE_FAILED,
        ], true)) {
            return $this->pipeline->fail(409, 'La captura no tiene análisis para resolver.', $capture);
        }

        $resolutions = $body['resolutions'] ?? $body['resoluciones'] ?? null;
        if (!is_array($resolutions) || $resolutions === []) {
            return $this->pipeline->fail(400, 'resolutions es obligatorio.', $capture);
        }

        $datos = $domain->datosExtraidos();
        if ($datos === []) {
            $datos = $this->pipeline->extractDatosExtraidosFromAnalizar($domain->analysisResponse());
        }
        if ($datos === []) {
            return $this->pipeline->fail(400, 'No hay datos extraídos para resolver.', $capture);
        }

        $categorias = $this->pipeline->resolveCategoriasForCapture($capture, $body);
        $rows = ClinicalCaptureRowContracts::registry();
        $datos = ClinicalCaptureRowContracts::resolutionApplier($rows)->apply($datos, $resolutions, $categorias);

        $completeness = ClinicalCaptureRowContracts::completenessValidator($rows)->validate($datos, $categorias);
        $analysis = $domain->analysisResponse();
        $textoOriginal = trim((string) ($analysis['texto_original'] ?? $domain->transcript() ?? ''));
        $textoProcesado = isset($analysis['texto_procesado'])
            ? (string) $analysis['texto_procesado']
            : ($domain->textoProcesado() !== null ? (string) $domain->textoProcesado() : null);
        $review = (new EncounterCaptureReviewPresenter())->build(
            $datos,
            $categorias,
            $textoOriginal,
            $textoProcesado,
            ($completeness['tiene_datos_faltantes'] ?? false) === true,
            $completeness
        );
        $review = $this->pipeline->applyEpisodeDedupToReview($capture, $review, $textoOriginal);

        if ($analysis === []) {
            $analysis = ['success' => true];
        }
        if (isset($analysis['datos']) && is_array($analysis['datos'])) {
            $analysis['datos']['datosExtraidos'] = $datos;
        }
        $analysis['datosExtraidos'] = $datos;
        $analysis['capture_review'] = $review;
        $analysis['puede_confirmar'] = $review['puede_confirmar'] ?? false;
        $analysis['tiene_datos_faltantes'] = $review['tiene_datos_faltantes'] ?? false;
        $analysis['datos_faltantes_detalle'] = $review['datos_faltantes_detalle'] ?? [
            'missing_categories' => $completeness['missing_categories'] ?? [],
            'incomplete_items' => $completeness['incomplete_items'] ?? [],
            'issues' => $completeness['issues'] ?? [],
            'message' => $completeness['message'] ?? '',
        ];

        try {
            $domain->applyResolutionSnapshot($datos, $analysis);
            $capture = $this->pipeline->persistDomain($domain);
        } catch (\InvalidArgumentException $e) {
            return $this->pipeline->fail(409, $e->getMessage(), $capture);
        } catch (\Throwable $e) {
            return $this->pipeline->fail(500, 'No se pudieron guardar las resoluciones.', $capture);
        }

        $this->pipeline->audit()->record($capture, EncounterCaptureAudit::EVENT_RESOLUTIONS_APPLIED, [
            'issue_ids' => array_values(array_map('strval', array_keys($resolutions))),
            'puede_confirmar' => ($review['puede_confirmar'] ?? false) === true,
        ]);

        return $this->pipeline->ok($capture, 'Resoluciones aplicadas.', true);
    }
}
