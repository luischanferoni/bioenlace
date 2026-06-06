<?php

namespace common\components\Assistant\SubIntentEngine;

use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAgendaFlowDraftHydrator;
use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioCrearFlowDraftHydrator;
use common\components\Core\DataAccess\DataAccessFlowDraftHydrator;
use common\components\Scheduling\Service\ReservaTurnoTriageFlowDraftHydrator;

/**
 * Registro de handlers de enriquecimiento de draft (lógica de dominio en capas inferiores).
 *
 * Los intents YAML declaran `draft_hydrator.handler`; el orquestador del chat no lista intents.
 *
 * @phpstan-type HydratorCallable callable(array<string, mixed>&, array<string, mixed>): void
 */
final class FlowDraftHydratorRegistry
{
    /** @var array<string, HydratorCallable> */
    private const HANDLERS = [
        'organization.pes_crear_alta' => [ProfesionalEfectorServicioCrearFlowDraftHydrator::class, 'hydrateWithOptions'],
        'organization.pes_from_servicio' => [ProfesionalEfectorServicioAgendaFlowDraftHydrator::class, 'hydrateWithOptions'],
        'data_access.metric_flow' => [DataAccessFlowDraftHydrator::class, 'hydrateWithOptions'],
        'scheduling.reserva_triage' => [ReservaTurnoTriageFlowDraftHydrator::class, 'hydrateWithOptions'],
    ];

    /**
     * @param array<string, mixed> $body request del asistente (mutado in-place)
     * @param array<string, mixed> $options opciones del manifiesto YAML (`draft_hydrator`)
     */
    public static function apply(string $handlerId, array &$body, array $options = []): void
    {
        $handlerId = trim($handlerId);
        if ($handlerId === '' || !isset(self::HANDLERS[$handlerId])) {
            throw new \InvalidArgumentException(
                'draft_hydrator.handler desconocido: ' . $handlerId
                . '. Registrados: ' . implode(', ', array_keys(self::HANDLERS))
            );
        }

        $callable = self::HANDLERS[$handlerId];
        $callable($body, $options);
    }

    /**
     * @return list<string>
     */
    public static function registeredHandlerIds(): array
    {
        return array_keys(self::HANDLERS);
    }
}
