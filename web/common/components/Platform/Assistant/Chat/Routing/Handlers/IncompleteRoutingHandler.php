<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Channels\Synthesis\SynthesisChannel;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutionResult;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutor;
use common\components\Platform\Assistant\Planning\PlannerRoutingStep;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;
use Yii;

/**
 * Routing incompletas: plan declarativo ± planificadora + 2ª IA síntesis.
 */
final class IncompleteRoutingHandler
{
    /**
     * @return array<string, mixed>|null Envelope o null para legacy guide.
     */
    public static function handle(
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId
    ): ?array {
        $plan = $evaluation->declarativePlan;
        $declarativeExecution = DeclarativePlanExecutor::execute($plan->toolIds, $userId);

        if ($plan->needsPlanner) {
            return self::handleWithPlanner(
                $evaluation,
                $content,
                $userId,
                $declarativeExecution,
                (string) ($plan->plannerReason ?? 'needs_planner')
            );
        }

        return self::finalizeSynthesis(
            $evaluation,
            $content,
            $userId,
            $declarativeExecution,
            '2ia_synthesis'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function handleWithPlanner(
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId,
        DeclarativePlanExecutionResult $declarativeExecution,
        string $plannerReason
    ): ?array {
        if (!PlannerRoutingStep::isEnabled()) {
            Yii::info(['planner_disabled' => true], 'asistente-planning');

            return self::fallbackWithoutPlanner($evaluation, $content, $userId, $declarativeExecution);
        }

        $plannerExecution = PlannerRoutingStep::run($evaluation, $userId, $plannerReason);
        if ($plannerExecution === null) {
            return self::fallbackWithoutPlanner($evaluation, $content, $userId, $declarativeExecution);
        }

        $execution = DeclarativePlanExecutionResult::merge($declarativeExecution, $plannerExecution);

        return self::finalizeSynthesis(
            $evaluation,
            $content,
            $userId,
            $execution,
            '3ia_planner_synthesis'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fallbackWithoutPlanner(
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId,
        DeclarativePlanExecutionResult $declarativeExecution
    ): ?array {
        if (
            !$declarativeExecution->hasUsefulData
            && $declarativeExecution->scopedSystemRecords === ''
            && $declarativeExecution->articleBlock === ''
        ) {
            return null;
        }

        return self::finalizeSynthesis(
            $evaluation,
            $content,
            $userId,
            $declarativeExecution,
            '2ia_synthesis'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function finalizeSynthesis(
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId,
        DeclarativePlanExecutionResult $execution,
        string $finalPath
    ): ?array {
        if (
            !$execution->hasUsefulData
            && $execution->scopedSystemRecords === ''
            && $execution->articleBlock === ''
        ) {
            Yii::info(['incomplete_no_useful_data' => true], 'asistente-planning');

            return null;
        }

        $envelope = SynthesisChannel::handle(
            $evaluation->firstIa,
            $execution,
            $evaluation,
            $content,
            $userId
        );
        if ($envelope === null) {
            return null;
        }

        AssistantPlanningLogService::setFinalPath($finalPath);

        return $envelope;
    }
}
