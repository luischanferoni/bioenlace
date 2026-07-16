<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\PatientSummary\PatientEncounterSummaryQueryService;

/**
 * Contexto declarativo de intake consultas/seguimiento para bandeja y chat async (staff).
 *
 * Contrato agnóstico de cliente: no incluye URLs ni rutas web. Las referencias de
 * navegación se entregan como datos estructurados (`subject_persona_id`, `encounter_id`)
 * y cada cliente (web SPA, app nativa) resuelve la navegación por su cuenta.
 */
final class ConsultaAsyncIntakeContextService
{
    /**
     * @param array<string, mixed> $meta JSON del campo note del encounter async
     * @return array<string, mixed>|null null si no hay intake de consultas-seguimiento
     */
    public function buildFromMeta(array $meta, int $idPersona): ?array
    {
        $intakeTipo = trim((string) ($meta['intake_tipo'] ?? ''));
        if ($intakeTipo === '') {
            return null;
        }

        $catalog = new ConsultasSeguimientoIntakeCatalogService();
        $tipoLabel = $this->labelTipo($catalog, $intakeTipo);
        $lines = [];
        $references = [];

        $necesidad = trim((string) ($meta['seguimiento_necesidad'] ?? ''));
        if ($necesidad !== '') {
            $def = $catalog->necesidad($necesidad);
            $lines[] = [
                'code' => 'seguimiento_necesidad',
                'label' => 'Necesidad',
                'value' => $def !== null ? $def['label'] : $necesidad,
            ];
        }

        $operacion = trim((string) ($meta['medicacion_operacion'] ?? ''));
        if ($operacion !== '') {
            $opLabel = $operacion === 'renovacion'
                ? 'Renovación'
                : ($operacion === 'ajuste' ? 'Ajuste' : $operacion);
            $lines[] = [
                'code' => 'medicacion_operacion',
                'label' => 'Operación',
                'value' => $opLabel,
            ];
        }

        $medLabels = $meta['medication_labels'] ?? null;
        if (is_array($medLabels) && $medLabels !== []) {
            $labels = [];
            foreach ($medLabels as $label) {
                $s = trim((string) $label);
                if ($s !== '') {
                    $labels[] = $s;
                }
            }
            if ($labels !== []) {
                $lines[] = [
                    'code' => 'medication_request_ids',
                    'label' => 'Medicamentos',
                    'value' => implode('; ', $labels),
                ];
            }
        }

        $ajusteMotivo = trim((string) ($meta['ajuste_motivo'] ?? ''));
        if ($ajusteMotivo !== '') {
            $lines[] = [
                'code' => 'ajuste_motivo',
                'label' => 'Motivo del ajuste',
                'value' => $ajusteMotivo,
            ];
        }

        $carePlanId = (int) ($meta['care_plan_id'] ?? 0);
        if ($carePlanId > 0 && $idPersona > 0) {
            $plan = (new ReservaTriageCarePlanServicioService())->findPlanForPersona($carePlanId, $idPersona);
            if ($plan !== null) {
                $title = trim((string) ($plan->title ?? ''));
                $lines[] = [
                    'code' => 'care_plan',
                    'label' => 'Tratamiento',
                    'value' => $title !== '' ? $title : 'Plan de tratamiento',
                    'care_plan_id' => (int) $plan->id,
                ];
            }
        }

        $refId = (int) ($meta['reference_encounter_id'] ?? 0);
        if ($refId > 0 && $idPersona > 0) {
            $detail = (new PatientEncounterSummaryQueryService())->getDetailForPersona($idPersona, $refId);
            $label = $this->formatEncounterLabel($detail, $refId);
            $lines[] = [
                'code' => 'reference_encounter',
                'label' => 'Atención previa',
                'value' => $label,
                'encounter_id' => $refId,
            ];
            $references[] = [
                'kind' => 'reference_encounter',
                'label' => 'Ver atención de referencia',
                'subject_persona_id' => $idPersona,
                'encounter_id' => $refId,
            ];
        }

        return [
            'intake_tipo' => $intakeTipo,
            'tipo_label' => $tipoLabel,
            'summary' => $this->buildSummary($tipoLabel, $lines),
            'subject_persona_id' => $idPersona > 0 ? $idPersona : null,
            'lines' => $lines,
            'references' => $references,
        ];
    }

    private function labelTipo(ConsultasSeguimientoIntakeCatalogService $catalog, string $code): string
    {
        foreach ($catalog->opcionesTipo() as $row) {
            if (($row['code'] ?? '') === $code) {
                return (string) ($row['label'] ?? $code);
            }
        }

        return $code;
    }

    /**
     * @param array<string, mixed>|null $detail
     */
    private function formatEncounterLabel(?array $detail, int $encounterId): string
    {
        if ($detail === null) {
            return 'Atención #' . $encounterId;
        }
        $parts = [];
        $published = trim((string) ($detail['publishedAt'] ?? $detail['periodEnd'] ?? ''));
        if ($published !== '') {
            $parts[] = $published;
        }
        $efector = trim((string) ($detail['efector']['nombre'] ?? ''));
        if ($efector !== '') {
            $parts[] = $efector;
        }
        $prof = trim((string) ($detail['profesional']['display'] ?? ''));
        if ($prof !== '') {
            $parts[] = $prof;
        }
        if ($parts === []) {
            return 'Atención #' . $encounterId;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param list<array{label: string, value: string}> $lines
     */
    private function buildSummary(string $tipoLabel, array $lines): string
    {
        $chunks = [$tipoLabel];
        foreach ($lines as $line) {
            $value = trim((string) ($line['value'] ?? ''));
            if ($value !== '') {
                $chunks[] = $value;
            }
        }

        return implode(' · ', $chunks);
    }
}
