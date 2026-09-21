<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\ClinicalCaptureAnalysisService;

/**
 * Caso de uso: análisis IA de nota clínica (intake Capture).
 */
final class AnalyzeClinicalNote
{
    private ClinicalCaptureAnalysisService $analisis;

    public function __construct(?ClinicalCaptureAnalysisService $analisis = null)
    {
        $this->analisis = $analisis ?? new ClinicalCaptureAnalysisService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {
        return $this->analisis->analizar($body);
    }

    /**
     * @return array<string, mixed>
     */
    public function executeOnProcessedText(
        string $textoProcesado,
        ?string $nombreServicio,
        $idConfiguracion,
        ?int $subjectPersonaId = null
    ): array {
        return $this->analisis->analizarConsultaConIA(
            $textoProcesado,
            $nombreServicio,
            $this->analisis->getModelosPorConfiguracion($idConfiguracion),
            $subjectPersonaId
        );
    }
}
