<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\CaptureDraftService;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\models\Clinical\EncounterCaptureAudit;

/** Caso de uso: descartar captura abierta. */
final class DiscardCapture
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

        $capture = $this->draft->findCapture($body, false);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->draft->captureRows()->toAggregate($capture);
        if ($domain->stage() === ClinicalCaptureStage::COMPLETED) {
            return $this->draft->fail(409, 'No se puede descartar una captura ya completada.', $capture);
        }

        $this->draft->deleteAudioFile($capture);
        $hadAnalysis = $capture->getAnalysisResponse() !== [];
        $previousStage = $capture->stage;
        try {
            $domain->discard();
        } catch (\InvalidArgumentException $e) {
            return $this->draft->fail(409, $e->getMessage(), $capture);
        }
        $capture = $this->draft->persistDomain($domain);
        $this->draft->audit()->record($capture, EncounterCaptureAudit::EVENT_DISCARDED, [
            'previous_stage' => $previousStage,
            'previous_had_analysis' => $hadAnalysis,
        ]);

        return $this->draft->ok($capture, 'Captura descartada.');
    }
}
