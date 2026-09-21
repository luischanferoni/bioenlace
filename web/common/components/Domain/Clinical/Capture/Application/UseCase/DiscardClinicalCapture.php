<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Support\ClinicalCaptureSupport;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureStage;
use common\models\Clinical\EncounterCaptureAudit;

/** Caso de uso: descartar captura abierta. */
final class DiscardClinicalCapture
{
    private ClinicalCaptureSupport $support;

    public function __construct(?ClinicalCaptureSupport $support = null)
    {
        $this->support = $support ?? new ClinicalCaptureSupport();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {

        $capture = $this->support->findCapture($body, false);
        if (is_array($capture)) {
            return $capture;
        }

        $domain = $this->support->captureRows()->toAggregate($capture);
        if ($domain->stage() === ClinicalCaptureStage::COMPLETED) {
            return $this->support->fail(409, 'No se puede descartar una captura ya completada.', $capture);
        }

        $this->support->deleteAudioFile($capture);
        $hadAnalysis = $capture->getAnalysisResponse() !== [];
        $previousStage = $capture->stage;
        try {
            $domain->discard();
        } catch (\InvalidArgumentException $e) {
            return $this->support->fail(409, $e->getMessage(), $capture);
        }
        $capture = $this->support->persistDomain($domain);
        $this->support->audit()->record($capture, EncounterCaptureAudit::EVENT_DISCARDED, [
            'previous_stage' => $previousStage,
            'previous_had_analysis' => $hadAnalysis,
        ]);

        return $this->support->ok($capture, 'Captura descartada.');
    }
}
