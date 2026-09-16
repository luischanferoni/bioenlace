<?php

namespace common\components\Platform\Core\Product;

use common\components\Domain\Clinical\CareCohort\Application\Agent\CareFollowupBranchingAgentPolicy;
use common\components\Domain\Clinical\HistoryExchange\Application\Agent\IntegrationRetryAgentPolicy;
use common\components\Domain\Clinical\Inpatient\Application\Agent\InternacionCamaSugerenciaAgentPolicy;
use common\components\Domain\Clinical\Laboratory\Application\Agent\LabEncounterLinkAgentPolicy;
use common\components\Domain\Clinical\Inpatient\Application\Agent\PostDischargeFollowupAgentPolicy;
use common\components\Domain\Clinical\Laboratory\Application\Agent\PostLabClassificationAgentPolicy;
use common\components\Domain\Clinical\Prescription\Application\Agent\PrescriptionRdiPreSubmitAgentPolicy;
use common\components\Domain\Scheduling\Application\Agent\ConsultaAsyncBandejaPrioridadAgentPolicy;
use common\components\Domain\Scheduling\Application\Agent\ReservaTriagePostCupoRoutingAgentPolicy;
use common\components\Domain\Scheduling\Application\Agent\TurnoAdvanceOfferAgentPolicy;
use common\components\Domain\Scheduling\Application\Agent\TurnoAntinoshowAgentPolicy;
use common\components\Domain\Scheduling\Application\Agent\TurnoResolucionAutoReservaAgentPolicy;
use common\components\Domain\Scheduling\Application\Agent\TurnoResolucionLoopCloseAgentPolicy;
use common\components\Domain\Scheduling\Application\Agent\TurnoResolucionMulticanalAgentPolicy;
use common\components\Domain\Scheduling\Application\Agent\TurnoResolucionShortlistAgentPolicy;

/**
 * Políticas de agentes autónomos en PHP (`<BC>/<Modulo?>/Application/Agent/*AgentPolicy`).
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
        'internacion-cama-sugerencia' => InternacionCamaSugerenciaAgentPolicy::class,
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
