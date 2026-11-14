<?php

namespace common\components\Domain\Clinical\Specialty\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\RowContract\KinesiologiaSesionRowContract;
use common\models\Clinical\Encounter;

/**
 * Sesión de kinesiología o fisiatría del encounter.
 *
 * Especialización de {@see ClinicalPanelService}: el dolor y la fuerza se validan
 * contra la escala de su contrato.
 */
final class KinesiologyEncounterService
{
    private ClinicalPanelService $panels;

    public function __construct(?ClinicalPanelService $panels = null)
    {
        $this->panels = $panels ?? new ClinicalPanelService();
    }

    /**
     * @param mixed $payload filas extraídas de la sesión
     */
    public function persistSession(Encounter $encounter, $payload): int
    {
        return $this->panels->persist(KinesiologiaSesionRowContract::class, $encounter, $payload);
    }

    /**
     * @return list<array{panelId: int, registradoEn: string, encounterId: int|null, values: array<string, array{label: string, value: string, unit: string|null}>}>
     */
    public function sessionsForPerson(int $personaId): array
    {
        return $this->panels->panelsForPerson(KinesiologiaSesionRowContract::class, $personaId);
    }

    /**
     * Evolución del dolor (EVA).
     *
     * @return list<array{panelId: int, registradoEn: string, value: float}>
     */
    public function painSeriesForPerson(int $personaId): array
    {
        return $this->panels->seriesForPerson(
            KinesiologiaSesionRowContract::class,
            $personaId,
            KinesiologiaSesionRowContract::FIELD_DOLOR
        );
    }
}
