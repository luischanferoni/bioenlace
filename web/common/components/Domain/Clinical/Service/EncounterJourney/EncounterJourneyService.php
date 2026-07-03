<?php

namespace common\components\Domain\Clinical\Service\EncounterJourney;

use common\models\Clinical\Encounter;
use common\models\Scheduling\Turno;

/**
 * Orquesta ventanas, elegibilidad y acciones del recorrido pre/post consulta.
 */
final class EncounterJourneyService
{
    private EncounterJourneyContextBuilder $contextBuilder;
    private EncounterPhaseWindowsCatalogService $windowsCatalog;
    private EncounterPhaseWindowService $windowService;
    private EncounterJourneyEligibilityService $eligibilityService;

    public function __construct(
        ?EncounterJourneyContextBuilder $contextBuilder = null,
        ?EncounterPhaseWindowsCatalogService $windowsCatalog = null,
        ?EncounterPhaseWindowService $windowService = null,
        ?EncounterJourneyEligibilityService $eligibilityService = null
    ) {
        $this->windowsCatalog = $windowsCatalog ?? new EncounterPhaseWindowsCatalogService();
        $this->contextBuilder = $contextBuilder ?? new EncounterJourneyContextBuilder();
        $this->windowService = $windowService ?? new EncounterPhaseWindowService($this->windowsCatalog);
        $this->eligibilityService = $eligibilityService ?? new EncounterJourneyEligibilityService();
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
            'motivos_cierre_minutos' => (int) ($motivos['window']['minutos_antes_cierre'] ?? 2),
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
        $enabled = $elig['applies'] && !empty($window['input_abierto']);

        return [
            'applies' => $elig['applies'],
            'enabled' => $enabled,
            'skip_reason' => $elig['skip_reason'],
            'label' => $elig['label'],
            'surface' => $elig['surface'],
            'intent_id' => $elig['intent_id'],
            'action_id' => $elig['action_id'],
            'completed' => $this->isCompleted($phaseId, $context),
            'window' => $window,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isCompleted(string $phaseId, array $context): bool
    {
        return match ($phaseId) {
            EncounterPhaseWindowsCatalogService::PHASE_MOTIVOS => !empty($context['motivos_resumen_present']),
            EncounterPhaseWindowsCatalogService::PHASE_ASISTENCIA => !empty($context['asistencia_completada']),
            default => false,
        };
    }
}
