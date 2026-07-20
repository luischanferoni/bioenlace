<?php

namespace common\components\Domain\Scheduling\Service;

/**
 * Pasos UI del intake consultas / seguimiento (definidos en metadata, no en orquestador).
 */
final class ConsultasSeguimientoIntakeStepService
{
    public const STEP_TIPO = 'tipo';

    public const STEP_NECESIDAD = 'necesidad';

    public const STEP_PREFERENCIA_TURNO = 'preferencia_turno';

    /**
     * @return array{title: string, draft_field: string}|null
     */
    public function stepDefinition(string $stepId): ?array
    {
        $stepId = trim($stepId);
        if ($stepId === '') {
            return null;
        }
        $def = (new ConsultasSeguimientoIntakeCatalogService())->uiStep($stepId);
        if ($def === null) {
            return null;
        }

        return [
            'title' => $def['title'],
            'draft_field' => $def['draft_field'],
        ];
    }

    /**
     * @return list<array{code: string, label: string, urgency_band: null, halts_booking: false}>
     */
    public function opcionesParaStep(string $stepId): array
    {
        $catalog = new ConsultasSeguimientoIntakeCatalogService();
        $def = $catalog->uiStep(trim($stepId));
        $rows = $def !== null ? $catalog->opcionesPorClave($def['opciones']) : [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'code' => (string) ($row['code'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'urgency_band' => null,
                'halts_booking' => false,
            ];
        }

        return $out;
    }
}
