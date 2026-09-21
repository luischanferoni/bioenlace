<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\ClinicalCaptureCheckpoint;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\models\Clinical\EncounterCaptureAudit;

/** Caso de uso: descartar captura abierta. */
final class DiscardClinicalCapture
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
    public function execute(array $body): array
    {

        $capture = $this->checkpoint->findCapture($body, false);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->checkpoint->captureRows()->toAggregate($capture);
        if ($domain->stage() === ClinicalCaptureStage::COMPLETED) {
            return $this->checkpoint->fail(409, 'No se puede descartar una captura ya completada.', $capture);
        }

        $this->checkpoint->deleteAudioFile($capture);
        $hadAnalysis = $capture->getAnalysisResponse() !== [];
        $previousStage = $capture->stage;
        try {
            $domain->discard();
        } catch (\InvalidArgumentException $e) {
            return $this->checkpoint->fail(409, $e->getMessage(), $capture);
        }
        $capture = $this->checkpoint->persistDomain($domain);
        $this->checkpoint->audit()->record($capture, EncounterCaptureAudit::EVENT_DISCARDED, [
            'previous_stage' => $previousStage,
            'previous_had_analysis' => $hadAnalysis,
        ]);

        return $this->checkpoint->ok($capture, 'Captura descartada.');
    }
}
