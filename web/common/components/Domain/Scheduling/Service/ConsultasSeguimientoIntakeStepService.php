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
        return match ($stepId) {
            self::STEP_TIPO => [
                'title' => 'Consultas y seguimiento',
                'draft_field' => ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO,
            ],
            self::STEP_NECESIDAD => [
                'title' => '¿Qué necesitás?',
                'draft_field' => ConsultasSeguimientoIntakeService::DRAFT_SEGUIMIENTO_NECESIDAD,
            ],
            self::STEP_PREFERENCIA_TURNO => [
                'title' => '¿Cómo preferís el turno?',
                'draft_field' => ConsultasSeguimientoIntakeService::DRAFT_PREFERENCIA_TURNO,
            ],
            default => null,
        };
    }

    /**
     * @return list<array{code: string, label: string, urgency_band: null, halts_booking: false}>
     */
    public function opcionesParaStep(string $stepId): array
    {
        $catalog = new ConsultasSeguimientoIntakeCatalogService();
        $rows = match (trim($stepId)) {
            self::STEP_TIPO => $catalog->opcionesTipo(),
            self::STEP_NECESIDAD => $catalog->opcionesNecesidad(),
            self::STEP_PREFERENCIA_TURNO => $catalog->opcionesPreferenciaTurno(),
            default => [],
        };
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
