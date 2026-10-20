<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\RowContractService;
use common\components\Domain\Clinical\Capture\Application\Service\CaptureDraftService;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EncounterCaptureReviewPresenter;
use common\models\Clinical\EncounterCaptureAudit;

/** Caso de uso: aplicar resoluciones de issues sobre datosExtraidos. */
final class ApplyCaptureResolutions
{
    private CaptureDraftService $draft;

    public function __construct(?CaptureDraftService $draft = null)
    {
        $this->draft = $draft ?? new CaptureDraftService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {
        $capture = $this->draft->findOpenCapture($body);

        $domain = $this->draft->captureRows()->toAggregate($capture);
        if (!in_array($domain->stage(), [
            ClinicalCaptureStage::READY_FOR_REVIEW,
            ClinicalCaptureStage::SAVE_FAILED,
        ], true)) {
            throw $this->draft->failure('capture_no_analysis_to_resolve', $capture);
        }

        $resolutions = $body['resolutions'] ?? $body['resoluciones'] ?? null;
        if (!is_array($resolutions) || $resolutions === []) {
            throw $this->draft->failure('capture_resolutions_required', $capture);
        }

        $datos = $domain->datosExtraidos();
        if ($datos === []) {
            $datos = $this->draft->extractDatosExtraidos($domain->analysisResponse());
        }
        if ($datos === []) {
            throw $this->draft->failure('capture_no_extracted_data', $capture);
        }

        $categorias = $this->draft->resolveCategoriasForCapture($capture, $body);
        $rows = RowContractService::registry();
        $datos = RowContractService::resolutionApplier($rows)->apply($datos, $resolutions, $categorias);

        $completeness = RowContractService::completenessValidator($rows)->validate($datos, $categorias);
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
        $review = $this->draft->applyEpisodeDedupToReview($capture, $review, $textoOriginal);

        if ($analysis === []) {
            $analysis = ['analyzed' => true];
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
            $capture = $this->draft->persistDomain($domain);
        } catch (\InvalidArgumentException $e) {
            throw $this->draft->failure('capture_conflict', $capture, ['detail' => $e->getMessage()]);
        } catch (\Throwable $e) {
            throw $this->draft->failure('capture_resolutions_save_failed', $capture);
        }

        $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_RESOLUTIONS_APPLIED, [
            'issue_ids' => array_values(array_map('strval', array_keys($resolutions))),
            'puede_confirmar' => ($review['puede_confirmar'] ?? false) === true,
        ]);

        return $this->draft->ok($capture, true);
    }
}
