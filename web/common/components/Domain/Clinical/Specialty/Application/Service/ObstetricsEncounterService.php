<?php

namespace common\components\Domain\Clinical\Specialty\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\RowContract\ObstetriciaControlRowContract;
use common\models\Clinical\Encounter;

/**
 * Control prenatal del encounter.
 *
 * Especialización de {@see ClinicalPanelService} sobre
 * {@see ObstetriciaControlRowContract}: la mecánica del panel es compartida.
 */
final class ObstetricsEncounterService
{
    private ClinicalPanelService $panels;

    public function __construct(?ClinicalPanelService $panels = null)
    {
        $this->panels = $panels ?? new ClinicalPanelService();
    }

    /**
     * @param mixed $payload filas extraídas del control prenatal
     */
    public function persistControl(Encounter $encounter, $payload): int
    {
        return $this->panels->persist(ObstetriciaControlRowContract::class, $encounter, $payload);
    }

    /**
     * Controles prenatales del paciente, del más reciente al más antiguo.
     *
     * @return list<array{panelId: int, registradoEn: string, encounterId: int|null, values: array<string, array{label: string, value: string, unit: string|null}>}>
     */
    public function controlsForPerson(int $personaId): array
    {
        return $this->panels->panelsForPerson(ObstetriciaControlRowContract::class, $personaId);
    }

    /**
     * Serie de un parámetro para graficar su evolución.
     *
     * @return list<array{panelId: int, registradoEn: string, value: float}>
     */
    public function seriesForPerson(int $personaId, string $field): array
    {
        return $this->panels->seriesForPerson(ObstetriciaControlRowContract::class, $personaId, $field);
    }

    public static function labelFor(string $field): string
    {
        return $field;
    }
}