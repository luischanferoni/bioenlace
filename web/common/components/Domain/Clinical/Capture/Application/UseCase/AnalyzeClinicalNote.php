<?php

namespace common\components\Domain\Clinical\Capture\Application\UseCase;

use common\components\Domain\Clinical\Capture\Application\Service\CaptureExtractionService;

/**
 * Caso de uso: análisis IA de nota clínica (intake Capture).
 */
final class AnalyzeClinicalNote
{
    private CaptureExtractionService $extraction;

    public function __construct(?CaptureExtractionService $extraction = null)
    {
        $this->extraction = $extraction ?? new CaptureExtractionService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function execute(array $body): array
    {
        return $this->extraction->extract($body);
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
        return $this->extraction->extractFromProcessedText(
            $textoProcesado,
            $nombreServicio,
            $this->extraction->categoriesForConfig($idConfiguracion),
            $subjectPersonaId
        );
    }
}
