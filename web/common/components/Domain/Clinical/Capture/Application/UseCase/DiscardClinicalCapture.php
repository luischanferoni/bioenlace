<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Pipeline\ClinicalCapturePipelineSupport;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\models\Clinical\EncounterCaptureAudit;

/** Caso de uso: descartar captura abierta. */
final class DiscardClinicalCapture
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

        $domain = $this->pipeline->captureRows()->toAggregate($capture);
        if ($domain->stage() === ClinicalCaptureStage::COMPLETED) {
            return $this->pipeline->fail(409, 'No se puede descartar una captura ya completada.', $capture);
        }

        $this->pipeline->deleteAudioFile($capture);
        $hadAnalysis = $capture->getAnalysisResponse() !== [];
        $previousStage = $capture->stage;
        try {
            $domain->discard();
        } catch (\InvalidArgumentException $e) {
            return $this->pipeline->fail(409, $e->getMessage(), $capture);
        }
        $capture = $this->pipeline->persistDomain($domain);
        $this->pipeline->audit()->record($capture, EncounterCaptureAudit::EVENT_DISCARDED, [
            'previous_stage' => $previousStage,
            'previous_had_analysis' => $hadAnalysis,
        ]);

        return $this->pipeline->ok($capture, 'Captura descartada.');
    }
}
