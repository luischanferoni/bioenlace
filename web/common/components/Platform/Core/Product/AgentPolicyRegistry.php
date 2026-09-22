<?php

namespace common\components\Platform\Core\Product;

use common\components\Domain\Clinical\CareCohort\Application\Agents\CareFollowupBranchingAgentPolicy;
use common\components\Domain\Clinical\HistoryExchange\Application\Agents\IntegrationRetryAgentPolicy;
use common\components\Domain\Clinical\Inpatient\Application\Agents\InpatientBedSuggestionAgentPolicy;
use common\components\Domain\Clinical\Laboratory\Application\Agents\LabEncounterLinkAgentPolicy;
use common\components\Domain\Clinical\Inpatient\Application\Agents\PostDischargeFollowupAgentPolicy;
use common\components\Domain\Clinical\Laboratory\Application\Agents\PostLabClassificationAgentPolicy;
use common\components\Domain\Clinical\Prescription\Application\Agents\PrescriptionRdiPreSubmitAgentPolicy;
use common\components\Domain\Scheduling\Agenda\Application\Agents\ConsultaAsyncBandejaPrioridadAgentPolicy;
use common\components\Domain\Scheduling\Agenda\Application\Agents\ReservaTriagePostCupoRoutingAgentPolicy;
use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoAdvanceOfferAgentPolicy;
use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoAntinoshowAgentPolicy;
use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoResolucionAutoReservaAgentPolicy;
use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoResolucionLoopCloseAgentPolicy;
use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoResolucionMulticanalAgentPolicy;
use common\components\Domain\Scheduling\Agenda\Application\Agents\TurnoResolucionShortlistAgentPolicy;

/**
 * Políticas de agentes autónomos en PHP (`<BC>/<Modulo?>/Application/Agents/*AgentPolicy`).
 * Sustituye `metadata/bioenlace/platform/agents/*.yaml`.
 */
final class AgentPolicyRegistry
{
    /**
     * @var array<string, class-string>
     */
    private const POLICIES = [
        'turno-antinoshow' => TurnoAntinoshowAgentPolicy::class,
        'turno-advance-offer' => TurnoAdvanceOfferAgentPolicy::class,
        'turno-resolucion-loop-close' => TurnoResolucionLoopCloseAgentPolicy::class,
        'turno-resolucion-shortlist' => TurnoResolucionShortlistAgentPolicy::class,
        'turno-resolucion-auto-reserva' => TurnoResolucionAutoReservaAgentPolicy::class,
        'turno-resolucion-multicanal' => TurnoResolucionMulticanalAgentPolicy::class,
        'consulta-async-bandeja-prioridad' => ConsultaAsyncBandejaPrioridadAgentPolicy::class,
        'reserva-triage-post-cupo-routing' => ReservaTriagePostCupoRoutingAgentPolicy::class,
        'lab-encounter-link' => LabEncounterLinkAgentPolicy::class,
        'post-lab-classification' => PostLabClassificationAgentPolicy::class,
        'prescription-rdi-pre-submit' => PrescriptionRdiPreSubmitAgentPolicy::class,
        'care-followup-branching' => CareFollowupBranchingAgentPolicy::class,
        'post-discharge-followup' => PostDischargeFollowupAgentPolicy::class,
        'internacion-cama-sugerencia' => InpatientBedSuggestionAgentPolicy::class,
        'integration-retry' => IntegrationRetryAgentPolicy::class,
    ];

    /**
     * @return list<string>
     */
    public static function agentIds(): array
    {
        $ids = array_keys(self::POLICIES);
        sort($ids);

        return $ids;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function config(string $agentId): ?array
    {
        $id = trim($agentId);
        if ($id === '' || !isset(self::POLICIES[$id])) {
            return null;
        }
        $class = self::POLICIES[$id];
        /** @var array<string, mixed> $config */
        $config = $class::config();

        return $config;
    }

    public static function has(string $agentId): bool
    {
        return isset(self::POLICIES[trim($agentId)]);
    }
}
