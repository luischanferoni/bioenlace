<?php

namespace common\components\Domain\Clinical\Specialty\Application\Service;

use common\components\Domain\Clinical\Capture\Domain\RowContract\SaludMentalSesionRowContract;
use common\components\Domain\Clinical\Specialty\Domain\Catalog\MentalHealthInstrumentCatalog;
use common\models\Clinical\Encounter;

/**
 * Sesión de salud mental (o de psicología) del encounter.
 *
 * Especialización de {@see ClinicalPanelService}: agrega la severidad de cada escala,
 * derivada del puntaje por {@see MentalHealthInstrumentCatalog}.
 */
final class MentalHealthEncounterService
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
        return $this->panels->persist(SaludMentalSesionRowContract::class, $encounter, $payload);
    }

    /**
     * Sesiones del paciente, de la más reciente a la más antigua, con la severidad
     * de cada instrumento derivada de su puntaje.
     *
     * @return list<array{panelId: int, registradoEn: string, encounterId: int|null, values: array<string, array{label: string, value: string, unit: string|null}>, scores: list<array{code: string, label: string, value: float, unit: string, severity: array{label: string, intent: string}|null}>}>
     */
    public function sessionsForPerson(int $personaId): array
    {
        $sessions = $this->panels->panelsForPerson(SaludMentalSesionRowContract::class, $personaId);

        foreach ($sessions as &$session) {
            $session['scores'] = [];
            foreach (MentalHealthInstrumentCatalog::all() as $code => $instrument) {
                $entry = $session['values'][$instrument['label']] ?? null;
                if (!is_array($entry)) {
                    continue;
                }
                $value = (float) $entry['value'];
                $session['scores'][] = [
                    'code' => $code,
                    'label' => $instrument['label'],
                    'value' => $value,
                    'unit' => $instrument['unit'],
                    'severity' => MentalHealthInstrumentCatalog::severityFor($code, $value),
                ];
            }
        }
        unset($session);

        return $sessions;
    }

    /**
     * Serie de un instrumento para graficar su evolución.
     *
     * @return list<array{panelId: int, registradoEn: string, value: float, severity: array{label: string, intent: string}|null}>
     */
    public function instrumentSeriesForPerson(int $personaId, string $instrumentCode): array
    {
        $instrument = MentalHealthInstrumentCatalog::get($instrumentCode);
        if ($instrument === null) {
            return [];
        }

        $series = $this->panels->seriesForPerson(
            SaludMentalSesionRowContract::class,
            $personaId,
            $instrument['label']
        );

        foreach ($series as &$point) {
            $point['severity'] = MentalHealthInstrumentCatalog::severityFor($instrumentCode, $point['value']);
        }
        unset($point);

        return $series;
    }
}