<?php

namespace common\components\Domain\Clinical\Specialty\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\RowContract\NutricionEvaluacionRowContract;
use common\models\Clinical\Encounter;

/**
 * Evaluación nutricional y plan del encounter.
 *
 * Especialización de {@see ClinicalPanelService}: el IMC llega derivado del contrato.
 */
final class NutritionEncounterService
{
    private ClinicalPanelService $panels;

    public function __construct(?ClinicalPanelService $panels = null)
    {
        $this->panels = $panels ?? new ClinicalPanelService();
    }

    /**
     * @param mixed $payload filas extraídas de la evaluación
     */
    public function persistAssessment(Encounter $encounter, $payload): int
    {
        return $this->panels->persist(NutricionEvaluacionRowContract::class, $encounter, $payload);
    }

    /**
     * @return list<array{panelId: int, registradoEn: string, encounterId: int|null, values: array<string, array{label: string, value: string, unit: string|null}>}>
     */
    public function assessmentsForPerson(int $personaId): array
    {
        return $this->panels->panelsForPerson(NutricionEvaluacionRowContract::class, $personaId);
    }

    /**
     * @return list<array{panelId: int, registradoEn: string, value: float}>
     */
    public function imcSeriesForPerson(int $personaId): array
    {
        return $this->panels->seriesForPerson(
            NutricionEvaluacionRowContract::class,
            $personaId,
            NutricionEvaluacionRowContract::FIELD_IMC
        );
    }
}
