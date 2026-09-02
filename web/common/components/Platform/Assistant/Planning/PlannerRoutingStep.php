<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Ai\IAManager;
use common\components\Platform\Assistant\Chat\Channels\Planner\PlannerPromptAssembler;
use Yii;

/**
 * Paso planificador: shortlist RBAC → IA → ejecutar tools elegidos.
 */
final class PlannerRoutingStep
{
    /** @var callable(array<string, mixed>, list<array<string, mixed>>, list<string>): ?PlannerPlanResult|null */
    private static $overrideForTests = null;

    /**
     * @param callable(array<string, mixed>, list<array<string, mixed>>, list<string>): ?PlannerPlanResult|null $override
     */
    public static function setOverrideForTests(?callable $override): void
    {
        self::$overrideForTests = $override;
    }

    public static function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['asistente_planner_enabled'] ?? true);
    }

    /**
     * @return DeclarativePlanExecutionResult|null
     */
    public static function run(
        SmartCatalogRoutingEvaluation $evaluation,
        int $userId,
        string $plannerReason
    ): ?DeclarativePlanExecutionResult {
        if (!self::isEnabled()) {
            return null;
        }

        $shortlist = PlannerShortlistBuilder::build(
            $evaluation->firstIa,
            $evaluation->match,
            $userId
        );
        if ($shortlist === []) {
            Yii::info(['planner_empty_shortlist' => true], 'asistente-planning');

            return null;
        }

        $allowedToolIds = array_map(
            static fn (array $row): string => (string) ($row['tool_id'] ?? ''),
            $shortlist
        );

        $plan = self::$overrideForTests !== null
            ? (self::$overrideForTests)($evaluation->firstIa, $shortlist, $evaluation->declarativePlan->toolIds)
            : self::consultPlannerIa(
                $evaluation->firstIa,
                $shortlist,
                $evaluation->declarativePlan->toolIds
            );

        if ($plan === null || $plan->toolIdsOrdered === []) {
            return null;
        }

        AssistantPlanningLogService::setPlannerPlan(
            $plan->toolIdsOrdered,
            $plan->rationale,
            $plannerReason
        );

        return DeclarativePlanExecutor::execute($plan->toolIdsOrdered, $userId, 'planner');
    }

    /**
     * @param array<string, mixed> $firstIa
     * @param list<array{tool_id: string, tool_type: string, description: string, param_schema: \stdClass}> $shortlist
     * @param list<string> $declarativeToolIds
     */
    public static function normalizeFromAi(array $raw, array $allowedToolIds): ?PlannerPlanResult
    {
        $allowed = array_fill_keys($allowedToolIds, true);
        $orderedRaw = $raw['tool_ids_ordered'] ?? [];
        if (!is_array($orderedRaw)) {
            return null;
        }

        $valid = [];
        foreach ($orderedRaw as $toolId) {
            if (!is_string($toolId)) {
                continue;
            }
            $toolId = trim($toolId);
            if ($toolId === '' || !isset($allowed[$toolId]) || in_array($toolId, $valid, true)) {
                continue;
            }
            $valid[] = $toolId;
        }

        if ($valid === []) {
            return null;
        }

        return new PlannerPlanResult(
            $valid,
            trim((string) ($raw['rationale'] ?? ''))
        );
    }

    /**
     * @param array<string, mixed> $firstIa
     * @param list<array{tool_id: string, tool_type: string, description: string, param_schema: \stdClass}> $shortlist
     * @param list<string> $declarativeToolIds
     */
    private static function consultPlannerIa(
        array $firstIa,
        array $shortlist,
        array $declarativeToolIds
    ): ?PlannerPlanResult {
        $allowedToolIds = array_map(
            static fn (array $row): string => (string) ($row['tool_id'] ?? ''),
            $shortlist
        );

        $prompt = PlannerPromptAssembler::build($firstIa, $shortlist, $declarativeToolIds);

        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-planner', 'analysis');
            if (!is_array($raw)) {
                return null;
            }

            return self::normalizeFromAi($raw, $allowedToolIds);
        } catch (\Throwable $e) {
            Yii::warning('PlannerRoutingStep: ' . $e->getMessage(), 'asistente');

            return null;
        }
    }
}
