<?php

namespace common\components\Scheduling\Service;

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

        $body['draft'] = $draft;
    }
}
