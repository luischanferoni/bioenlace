<?php

namespace common\components\Domain\Clinical\Capture\Application;

/**
 * Caso de uso: análisis IA de nota clínica (intake Capture).
 * Entry point preferido frente a {@see EncounterDocumentationService::analizar}.
 */
final class AnalyzeClinicalNote
{
    private ConsultaProcesamientoService $analisis;

    public function __construct(?ConsultaProcesamientoService $analisis = null)
    {
        $this->analisis = $analisis ?? new ConsultaProcesamientoService();
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
