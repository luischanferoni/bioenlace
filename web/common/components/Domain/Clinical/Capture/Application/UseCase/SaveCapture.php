<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\RowContractService;
use common\components\Domain\Clinical\Capture\Application\Service\CaptureDraftService;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EncounterCaptureReviewPresenter;
use common\models\Clinical\EncounterCaptureAudit;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterCaptureAuditService;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;

/** Caso de uso: guardar draft de captura → persistencia Encounter. */
final class SaveCapture
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
        if (is_array($capture)) {
            return $capture;
        }

        $analysis = $capture->getAnalysisResponse();
        if ($analysis === [] && $capture->getDatosExtraidos() === []) {
            return $this->draft->fail(400, 'No hay análisis para guardar. Ejecute captura-analizar.', $capture);
        }

        $stagedFromBody = $body['staged_item_ids'] ?? $body['stagedItemIds'] ?? null;
        if (is_array($stagedFromBody)) {
            $capture->setStagedItemIds(array_map('strval', $stagedFromBody));
        }

        $datosExtraidos = $body['datosExtraidos'] ?? null;
        if (!is_array($datosExtraidos) || $datosExtraidos === []) {
            $datosExtraidos = $capture->getDatosExtraidos();
            if ($datosExtraidos === [] && $analysis !== []) {
                $datosExtraidos = $this->draft->extractDatosExtraidos($analysis);
            }
        }

        $resolutions = $body['resolutions'] ?? $body['resoluciones'] ?? null;
        if (!is_array($resolutions)) {
            $resolutions = [];
        }
        $analysisLocal = is_array($analysis) ? $analysis : [];
        $prevResolutions = [];
        if (isset($analysisLocal['client_resolutions']) && is_array($analysisLocal['client_resolutions'])) {
            $prevResolutions = $analysisLocal['client_resolutions'];
        }
        $mergedResolutions = array_merge($prevResolutions, $resolutions);

        $fullExtracted = $capture->getDatosExtraidos();
        if ($fullExtracted === [] && $analysisLocal !== []) {
            $fullExtracted = $this->draft->extractDatosExtraidos($analysisLocal);
        }
        if ($mergedResolutions !== [] && $fullExtracted !== []) {
            $categorias = $this->draft->resolveCategoriasForCapture($capture, $body);
            $fullExtracted = RowContractService::resolutionApplier()->apply(
                $fullExtracted,
                $mergedResolutions,
                $categorias
            );
            $capture->setDatosExtraidos($fullExtracted);
        }
        if ($mergedResolutions !== []) {
            $analysisLocal['client_resolutions'] = $mergedResolutions;
            $capture->setAnalysisResponse($analysisLocal);
        }
        if ($mergedResolutions !== [] || $fullExtracted !== []) {
            $capture->updated_at = date('Y-m-d H:i:s');
            $capture->save(false);
        }

        $blocking = EncounterCaptureReviewPresenter::blockingErrorFromExtraidos(
            is_array($datosExtraidos) ? $datosExtraidos : []
        );
        if ($blocking !== null) {
            $msg = trim((string) ($blocking['texto'] ?? 'No se puede guardar: el análisis tiene errores.'));

            return $this->draft->fail(400, $msg !== '' ? $msg : 'No se puede guardar.', $capture);
        }

        $saveBody = $body;
        $saveBody['id_persona'] = $capture->subject_persona_id;
        $saveBody['subject_persona_id'] = $capture->subject_persona_id;
        $saveBody['datosExtraidos'] = $datosExtraidos;
        $saveBody['resolutions'] = $mergedResolutions;
        $saveBody['analisis_datos_extraidos'] = $fullExtracted !== []
            ? $fullExtracted
            : $capture->getDatosExtraidos();
        $saveBody['texto_original'] = $saveBody['texto_original']
            ?? $capture->transcript
            ?? '';
        $saveBody['texto_procesado'] = $saveBody['texto_procesado']
            ?? $capture->texto_procesado
            ?? $capture->transcript
            ?? '';
        if ($capture->parent_type !== null) {
            $saveBody['parent'] = $capture->parent_type;
        }
        if ($capture->parent_id !== null) {
            $saveBody['parent_id'] = $capture->parent_id;
        }
        if ($capture->analysis_cache_token) {
            $saveBody['analysis_cache_token'] = $capture->analysis_cache_token;
        }
        if ($capture->encounter_id) {
            $saveBody['id_consulta'] = $capture->encounter_id;
            $saveBody['encounter_id'] = $capture->encounter_id;
        }
        if (isset($analysis['id_configuracion']) && !isset($saveBody['id_configuracion'])) {
            $saveBody['id_configuracion'] = $analysis['id_configuracion'];
        }

        $out = $this->draft->documentation()->guardar($saveBody);
        // Si el dominio devolvió checkpoint resuelto, persistirlo para el próximo intento.
        $domain = $this->draft->captureRows()->toAggregate($capture);
        if (isset($out['analisis_datos_extraidos']) && is_array($out['analisis_datos_extraidos'])) {
            $domain->replaceDatosExtraidos($out['analisis_datos_extraidos']);
        } elseif (empty($out['success']) && $fullExtracted !== []) {
            $domain->replaceDatosExtraidos($fullExtracted);
        }

        $reviewForAudit = is_array($analysis['capture_review'] ?? null) ? $analysis['capture_review'] : [];
        $acceptanceMeta = EncounterCaptureAuditService::buildAcceptanceMeta(
            $reviewForAudit,
            $capture->getStagedItemIds(),
            $mergedResolutions !== [] ? $mergedResolutions : null
        );

        if (empty($out['success'])) {
            $msg = trim((string) ($out['message'] ?? 'Error al guardar.'));
            $domain->markSaveFailed($msg !== '' ? $msg : 'Error al guardar.');
            $capture = $this->draft->persistDomain($domain);
            $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_SAVE_FAILED, array_merge(
                [
                    'attempts_save' => $domain->attemptsSave(),
                    'error_code' => 'save_failed',
                ],
                $acceptanceMeta
            ));
            $status = (int) ($out['__statusCode'] ?? 500);

            return $this->draft->fail($status > 0 ? $status : 500, $capture->last_error, $capture, [
                'guardar' => $out,
            ]);
        }

        $encounterId = isset($out['encounter_id']) ? (int) $out['encounter_id'] : null;
        $domain->complete($encounterId);
        $capture = $this->draft->persistDomain($domain);
        $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_SAVED, array_merge(
            ['attempts_save' => $domain->attemptsSave()],
            $acceptanceMeta
        ));

        // Tras completar, el audio crudo ya no es necesario para el pipeline.
        $this->draft->deleteAudioFile($capture);

        $payload = $this->draft->toApiArray($capture);
        $payload['guardar'] = $out;

        return [
            'success' => true,
            'message' => (string) ($out['message'] ?? 'Captura guardada.'),
            'capture' => $payload,
            'guardar' => $out,
        ];
    }
}
