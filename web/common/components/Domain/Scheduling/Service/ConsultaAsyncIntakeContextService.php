<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\PatientSummary\PatientEncounterSummaryQueryService;

/**
 * Contexto declarativo de intake consultas/seguimiento para bandeja y chat async (staff).
 *
 * Contrato agnóstico de cliente: no incluye URLs ni rutas web. Las referencias de
 * navegación se entregan como datos estructurados (`subject_persona_id`, `encounter_id`)
 * y cada cliente (web SPA, app nativa) resuelve la navegación por su cuenta.
 *
 * `reference_encounter.detail`: resumen lean del encounter de origen (solo si se pide).
 * `references`: CTAs tipados (`clinical_history`, `reference_encounter`).
 */
final class ConsultaAsyncIntakeContextService
{
    /**
     * @param array<string, mixed> $meta JSON del campo note del encounter async
     * @param array{include_reference_detail?: bool} $options
     *   - include_reference_detail: true (default) carga resumen de atención previa (chat).
     *     false deja solo encounter_id + línea liviana (bandeja staff).
     * @return array<string, mixed>|null null si no hay intake de consultas-seguimiento
     */
    public function buildFromMeta(array $meta, int $idPersona, array $options = []): ?array
    {
        $intakeTipo = trim((string) ($meta['intake_tipo'] ?? ''));
        if ($intakeTipo === '') {
            return null;
        }
        $includeRefDetail = !array_key_exists('include_reference_detail', $options)
            || (bool) $options['include_reference_detail'];

        $catalog = new ConsultasSeguimientoIntakeCatalogService();
        $labels = (new ConsultaAsyncBandejaCatalogService())->intakeContextLabels();
        $tipoLabel = $this->labelTipo($catalog, $intakeTipo);
        $lines = [];
        $references = [];
        $referenceEncounter = null;

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
            $labelsMed = [];
            foreach ($medLabels as $label) {
                $s = trim((string) $label);
                if ($s !== '') {
                    $labelsMed[] = $s;
                }
            }
            if ($labelsMed !== []) {
                $lines[] = [
                    'code' => 'medication_request_ids',
                    'label' => 'Medicamentos',
                    'value' => implode('; ', $labelsMed),
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
            $lineLabel = $labels['reference_encounter_line_label'];
            $lineLabel = $lineLabel !== '' ? $lineLabel : 'Atención previa';
            if ($includeRefDetail) {
                $detail = (new PatientEncounterSummaryQueryService())->getDetailForPersona($idPersona, $refId);
                $lean = $this->leanEncounterDetail($detail, $refId, $labels);
                $lines[] = [
                    'code' => 'reference_encounter',
                    'label' => $lineLabel,
                    'value' => $lean['headline'],
                    'encounter_id' => $refId,
                ];
                $referenceEncounter = [
                    'encounter_id' => $refId,
                    'detail' => $lean,
                ];
            } else {
                $lines[] = [
                    'code' => 'reference_encounter',
                    'label' => $lineLabel,
                    'value' => 'Atención #' . $refId,
                    'encounter_id' => $refId,
                ];
                $referenceEncounter = [
                    'encounter_id' => $refId,
                ];
            }
            $references[] = [
                'kind' => 'reference_encounter',
                'label' => $labels['reference_encounter_action'] !== ''
                    ? $labels['reference_encounter_action']
                    : 'Ver atención de referencia',
                'subject_persona_id' => $idPersona,
                'encounter_id' => $refId,
            ];
        }

        if ($idPersona > 0) {
            $references[] = [
                'kind' => 'clinical_history',
                'label' => $labels['clinical_history_action'] !== ''
                    ? $labels['clinical_history_action']
                    : 'Ver historia clínica',
                'subject_persona_id' => $idPersona,
            ];
        }

        return [
            'intake_tipo' => $intakeTipo,
            'tipo_label' => $tipoLabel,
            'section_label' => $labels['section_label'] !== ''
                ? $labels['section_label']
                : 'Contexto de la solicitud',
            'subject_persona_id' => $idPersona > 0 ? $idPersona : null,
            'lines' => $lines,
            'reference_encounter' => $referenceEncounter,
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
     * Resumen lean para embutir en bandeja/chat (sin payload enorme).
     *
     * @param array<string, mixed>|null $detail
     * @param array<string, string> $labels
     * @return array<string, mixed>
     */
    private function leanEncounterDetail(?array $detail, int $encounterId, array $labels): array
    {
        if ($detail === null) {
            $empty = $labels['encounter_detail_empty'] ?? '';

            return [
                'encounterId' => $encounterId,
                'published' => false,
                'headline' => 'Atención #' . $encounterId,
                'title' => $labels['encounter_detail_title'] ?? 'Atención de referencia',
                'narrativeText' => $empty !== '' ? $empty : 'No hay un resumen publicado de esa atención.',
                'publishedAt' => null,
                'periodEnd' => null,
                'efector' => null,
                'profesional' => null,
            ];
        }

        $headline = $this->formatEncounterLabel($detail, $encounterId);
        $narrative = trim((string) ($detail['narrativeText'] ?? ''));
        if (mb_strlen($narrative) > 2500) {
            $narrative = mb_substr($narrative, 0, 2500) . '…';
        }

        $efectorNombre = trim((string) ($detail['efector']['nombre'] ?? ''));
        $profDisplay = trim((string) ($detail['profesional']['display'] ?? ''));

        return [
            'encounterId' => (int) ($detail['encounterId'] ?? $encounterId),
            'published' => true,
            'headline' => $headline,
            'title' => $labels['encounter_detail_title'] ?? 'Atención de referencia',
            'narrativeText' => $narrative,
            'publishedAt' => $detail['publishedAt'] ?? null,
            'periodEnd' => $detail['periodEnd'] ?? null,
            'efector' => $efectorNombre !== '' ? ['nombre' => $efectorNombre] : null,
            'profesional' => $profDisplay !== '' ? ['display' => $profDisplay] : null,
        ];
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
}
