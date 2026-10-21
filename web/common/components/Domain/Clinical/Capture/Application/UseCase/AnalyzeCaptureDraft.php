<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\CaptureDraftService;
use common\components\Domain\Clinical\Capture\Application\UseCase\AnalyzeClinicalNote;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\components\Platform\Core\Http\Contract\ApiErrorCatalog;
use common\components\Platform\Core\Http\Contract\ApiException;
use common\models\Clinical\EncounterCaptureAudit;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterCaptureAuditService;

/** Caso de uso: análisis IA del transcript del checkpoint. */
final class AnalyzeCaptureDraft
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

        $textoOverride = trim((string) ($body['consulta'] ?? $body['texto'] ?? ''));
        if ($textoOverride !== '') {
            $domain->setTranscriptOverride($textoOverride);
        }

        if (!$domain->hasTranscript()) {
            throw $this->draft->failure('capture_no_transcript', $capture);
        }

        if ($domain->stage() === ClinicalCaptureStage::READY_FOR_REVIEW
            && $domain->analysisResponse() !== []
            && empty($body['force'])
        ) {
            return $this->draft->ok($capture, true);
        }

        $analyzeBody = $body;
        $analyzeBody['consulta'] = $domain->transcript();
        $analyzeBody['id_persona'] = $domain->subjectPersonaId();
        $analyzeBody['subject_persona_id'] = $domain->subjectPersonaId();
        if ($domain->parentType() !== null) {
            $analyzeBody['parent'] = $domain->parentType();
        }
        if ($domain->parentId() !== null) {
            $analyzeBody['parent_id'] = $domain->parentId();
        }
        unset($analyzeBody['audio'], $analyzeBody['audio_data'], $analyzeBody['file']);
        $analyzeBody['stt'] = array_merge($domain->sttMeta(), [
            'text' => $domain->transcript(),
            'force_server' => false,
        ]);

        try {
            $out = (new AnalyzeClinicalNote())->execute($analyzeBody);
        } catch (ApiException $e) {
            $domain->markAnalysisFailed(ApiErrorCatalog::resolve($e->errorCode(), $e->params())['message']);
            $capture = $this->draft->persistDomain($domain);
            $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_ANALYSIS_FAILED, [
                'attempts_analysis' => $domain->attemptsAnalysis(),
                'error_code' => 'analysis_failed',
            ]);

            throw $this->draft->failure($e->errorCode(), $capture, $e->params(), $e->meta());
        }

        $textoProcesado = isset($out['texto_procesado'])
            ? (string) $out['texto_procesado']
            : (string) $domain->transcript();
        $extraidos = $this->draft->extractDatosExtraidos($out);
        $snapshot = $out;
        unset($snapshot['html']);
        $encounterId = isset($out['encounter_id']) ? (int) $out['encounter_id'] : null;
        $staged = null;
        $review = $out['capture_review'] ?? null;
        if (is_array($review) && isset($review['default_staged_item_ids']) && is_array($review['default_staged_item_ids'])) {
            $staged = array_map('strval', $review['default_staged_item_ids']);
        }
        $token = isset($out['analysis_cache_token']) ? (string) $out['analysis_cache_token'] : null;

        $domain->markReadyForReview(
            $textoProcesado,
            $extraidos,
            $snapshot,
            $token,
            $staged,
            $encounterId
        );
        $capture = $this->draft->persistDomain($domain);
        $this->draft->audit()->record(
            $capture,
            EncounterCaptureAudit::EVENT_ANALYZED,
            array_merge(
                ['attempts_analysis' => $domain->attemptsAnalysis()],
                is_array($review) ? EncounterCaptureAuditService::buildAnalyzedMeta($review) : []
            )
        );

        return $this->draft->ok($capture, true);
    }
}
