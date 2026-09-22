<?php

namespace common\components\Domain\Clinical\Encounter\Application\UseCase;

use common\components\Domain\Clinical\Encounter\Application\Service\EncounterJourneyContextService;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterJourneyEligibilityService;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterJourneyFollowupStateService;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterPhaseWindowsCatalogService;
use common\components\Domain\Clinical\Encounter\Application\Service\EncounterPhaseWindowService;
use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;

/**
 * Orquesta ventanas, elegibilidad y acciones del recorrido pre/post consulta.
 */
final class OrchestrateEncounterJourney
{
    private EncounterJourneyContextService $contextBuilder;
    private EncounterPhaseWindowsCatalogService $windowsCatalog;
    private EncounterPhaseWindowService $windowService;
    private EncounterJourneyEligibilityService $eligibilityService;
    private EncounterJourneyFollowupStateService $followupState;

    public function __construct(
        ?EncounterJourneyContextService $contextBuilder = null,
        ?EncounterPhaseWindowsCatalogService $windowsCatalog = null,
        ?EncounterPhaseWindowService $windowService = null,
        ?EncounterJourneyEligibilityService $eligibilityService = null,
        ?EncounterJourneyFollowupStateService $followupState = null
    ) {
        $this->windowsCatalog = $windowsCatalog ?? new EncounterPhaseWindowsCatalogService();
        $this->contextBuilder = $contextBuilder ?? new EncounterJourneyContextService();
        $this->windowService = $windowService ?? new EncounterPhaseWindowService($this->windowsCatalog);
        $this->eligibilityService = $eligibilityService ?? new EncounterJourneyEligibilityService();
        $this->followupState = $followupState ?? new EncounterJourneyFollowupStateService();
    }

    /**
     * @return array{version: string, phases: array<string, array<string, mixed>>}
     */
    public function buildForTurno(Turno $turno, ?Encounter $encounter = null): array
    {
        $context = $this->contextBuilder->fromTurno($turno, $encounter);
        $phases = [];
        foreach ($this->windowsCatalog->phaseIds() as $phaseId) {
            $phases[$phaseId] = $this->buildPhase($phaseId, $context);
        }

        return [
            'version' => '1',
            'phases' => $phases,
        ];
    }

    /**
     * Compatibilidad con flags legacy del listado de turnos.
     *
     * @return array{motivos_input_abierto: bool, motivos_cierre_minutos: int, asistencia_cohorte_disponible: bool}
     */
    public function legacyFlagsForTurno(Turno $turno, ?Encounter $encounter = null): array
    {
        $journey = $this->buildForTurno($turno, $encounter);
        $motivos = $journey['phases'][EncounterPhaseWindowsCatalogService::PHASE_MOTIVOS] ?? [];
        $asistencia = $journey['phases'][EncounterPhaseWindowsCatalogService::PHASE_ASISTENCIA] ?? [];

        return [
            'motivos_input_abierto' => !empty($motivos['enabled']),
            'motivos_cierre_minutos' => (int) ($motivos['window']['minutos_antes_cierre'] ?? 10),
            'asistencia_cohorte_disponible' => !empty($asistencia['enabled']),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildPhase(string $phaseId, array $context): array
    {
        $elig = $this->eligibilityService->evaluate($phaseId, $context);
        $window = $this->windowService->state($phaseId, $context);
        $followup = null;
        $enabled = $elig['applies'] && !empty($window['input_abierto']);

        if ($phaseId === EncounterPhaseWindowsCatalogService::PHASE_POST) {
            $encounterId = (int) ($context['encounter_id'] ?? 0);
            $followup = $this->followupState->stateForEncounter($encounterId);
            $enabled = $enabled && (int) ($followup['actionable_count'] ?? 0) > 0;
        }

        $phase = [
            'applies' => $elig['applies'],
            'enabled' => $enabled,
            'skip_reason' => $elig['skip_reason'],
            'label' => $elig['label'],
            'surface' => $elig['surface'],
            'intent_id' => $elig['intent_id'],
            'action_id' => $elig['action_id'],
            'api_path' => $elig['api_path'],
            'completed' => $this->isCompleted($phaseId, $context),
            'window' => $window,
        ];
        if ($followup !== null) {
            $phase['followup'] = $followup;
        }

        return $phase;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isCompleted(string $phaseId, array $context): bool
    {
        if ($phaseId === EncounterPhaseWindowsCatalogService::PHASE_MOTIVOS) {
            return !empty($context['motivos_resumen_present']);
        }
        if ($phaseId === EncounterPhaseWindowsCatalogService::PHASE_ASISTENCIA) {
            return !empty($context['asistencia_completada']);
        }
        if ($phaseId === EncounterPhaseWindowsCatalogService::PHASE_POST) {
            $encounterId = (int) ($context['encounter_id'] ?? 0);

            return $this->followupState->isPhaseCompleted($encounterId);
        }

        return false;
    }
}
