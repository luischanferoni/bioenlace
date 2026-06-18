<?php

namespace common\components\Domain\Scheduling\Service;

/**
 * Enriquece el draft del asistente tras pasos de triage (halt, banda, sugerencia teleconsulta).
 */
final class ReservaTurnoTriageFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     * @param array<string, mixed> $options ignorado
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $catalog = new ReservaTurnoTriageCatalogService();
        $compiled = $catalog->compileSelections($draft);

        $draft['reserva_triage_halt'] = $compiled['reserva_triage_halt'] ? '1' : '0';
        $draft['urgency_band'] = $compiled['urgency_band'];
        if ($compiled['suggests_tipo_atencion'] !== null && trim((string) ($draft['tipo_atencion'] ?? '')) === '') {
            $draft['tipo_atencion_sugerido'] = $compiled['suggests_tipo_atencion'];
        }

        (new TeleconsultaElegibilidadService())->aplicarFlagsEnDraft($draft);
        (new ReservaModalidadAtencionService())->aplicarFlagsEnDraft($draft);
        self::autoSeleccionarCarePlanUnico($draft);
        (new ReservaTriageServicioSugeridoService())->aplicarFlagsEnDraft($draft);

        $body['draft'] = $draft;
    }

    /**
     * Si el paciente tiene un solo plan activo, lo preselecciona en seguimiento crónico.
     *
     * @param array<string, mixed> $draft
     */
    private static function autoSeleccionarCarePlanUnico(array &$draft): void
    {
        if (trim((string) ($draft['triage_raiz'] ?? '')) !== 'seguimiento_cronico') {
            return;
        }
        if ((int) ($draft['care_plan_id'] ?? 0) > 0) {
            return;
        }
        $idPersona = (int) (\Yii::$app->user->getIdPersona() ?? 0);
        if ($idPersona <= 0) {
            return;
        }
        $plans = (new \common\components\Domain\Clinical\Service\PatientActiveCarePlanQuery())->listActive($idPersona);
        if (count($plans) === 1) {
            $draft['care_plan_id'] = (string) (int) $plans[0]->id;
        }
    }
}
