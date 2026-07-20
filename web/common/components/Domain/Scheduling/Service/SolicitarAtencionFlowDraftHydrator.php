<?php

namespace common\components\Domain\Scheduling\Service;

/**
 * Hydrator compuesto de Solicitar Atención: triage de reserva + intake de control/seguimiento.
 */
final class SolicitarAtencionFlowDraftHydrator
{
    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     * @param array<string, mixed> $options
     */
    public static function hydrateWithOptions(array &$body, array $options = []): void
    {
        ReservaTurnoTriageFlowDraftHydrator::hydrateWithOptions($body, $options);

        $draft = isset($body['draft']) && is_array($body['draft']) ? $body['draft'] : [];
        $raiz = trim((string) ($draft['triage_raiz'] ?? ''));
        $hasSeguimientoContext = self::hasSeguimientoContext($draft, $body);

        if ($raiz !== 'seguimiento_cronico' && !$hasSeguimientoContext) {
            $body['draft'] = $draft;

            return;
        }

        if ($raiz === '') {
            $draft['triage_raiz'] = 'seguimiento_cronico';
            $body['draft'] = $draft;
        }

        ConsultasSeguimientoFlowDraftHydrator::hydrateWithOptions($body, $options);
    }

    /**
     * @param array<string, mixed> $draft
     * @param array<string, mixed> $body
     */
    private static function hasSeguimientoContext(array $draft, array $body): bool
    {
        foreach (['intake_tipo', 'seguimiento_necesidad', 'care_plan_id', 'encounter_id'] as $key) {
            $v = trim((string) ($draft[$key] ?? ''));
            if ($v === '') {
                $v = trim((string) ($body[$key] ?? ''));
            }
            if ($v !== '' && $v !== '0') {
                return true;
            }
        }

        return false;
    }
}
