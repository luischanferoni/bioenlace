<?php

namespace common\components\Domain\Scheduling\Service;

/**
 * Enriquece el draft del flow atencion.consultas-seguimiento antes del SubIntentEngine.
 */
final class ConsultasSeguimientoFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     * @param array<string, mixed> $options ignorado
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $idPersona = (int) (\Yii::$app->user->getIdPersona() ?? 0);

        foreach ([
            'intake_tipo',
            'seguimiento_necesidad',
            'preferencia_profesional_turno',
            'care_plan_id',
            'encounter_id',
            'medication_request_ids',
            'medicacion_operacion',
            'ajuste_motivo',
            'mensaje',
        ] as $key) {
            $v = trim((string) ($draft[$key] ?? ''));
            if ($v !== '') {
                continue;
            }
            $fromBody = trim((string) ($body[$key] ?? ''));
            if ($fromBody !== '') {
                $draft[$key] = $fromBody;
            }
        }

        if (
            trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO] ?? '')) === ''
            && (int) ($draft['encounter_id'] ?? 0) > 0
        ) {
            $draft[ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO]
                = ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO_CONSULTA_PREVIA;
        }

        if (
            trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO] ?? '')) === ''
            && (
                trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_SEGUIMIENTO_NECESIDAD] ?? '')) !== ''
                || (int) ($draft['care_plan_id'] ?? 0) > 0
            )
        ) {
            $draft[ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO]
                = ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO;
        }

        (new ConsultasSeguimientoIntakeService())->prepararDraft($draft, $idPersona);

        if (
            trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_INTAKE_TIPO] ?? ''))
            === ConsultasSeguimientoIntakeCatalogService::INTAKE_SEGUIMIENTO
            && (int) ($draft['care_plan_id'] ?? 0) > 0
            && trim((string) ($draft[ConsultasSeguimientoIntakeService::DRAFT_SEGUIMIENTO_NECESIDAD] ?? '')) === ''
        ) {
            // care_plan sin necesidad: el paso select_necesidad sigue pendiente.
        }

        $body['draft'] = $draft;
    }
}
