<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannel;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutionResult;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutor;
use common\components\Platform\Assistant\Planning\PlannerRoutingStep;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;
use Yii;

/**
 * Routing que llama a la guía: match claro de un intent, o incompletas.
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

        return self::finalizeGuide(
            $evaluation,
            $content,
            $userId,
            $declarativeExecution,
            '2ia_guide'
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

        return self::finalizeGuide(
            $evaluation,
            $content,
            $userId,
            $execution,
            '3ia_planner_guide'
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
        if (!self::canGuide($evaluation, $declarativeExecution, $content)) {
            return null;
        }

        return self::finalizeGuide(
            $evaluation,
            $content,
            $userId,
            $declarativeExecution,
            '2ia_guide'
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function finalizeGuide(
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId,
        DeclarativePlanExecutionResult $execution,
        string $finalPath
    ): ?array {
        if (!self::canGuide($evaluation, $execution, $content)) {
            Yii::info(['incomplete_no_useful_data' => true], 'asistente-planning');

            return null;
        }

        $envelope = GuideChannel::handleIncomplete(
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

    /**
     * El handler ya decidió llamar a la guía. Sin este paso, un pedido sin botón cae en el mensaje de error.
     */
    private static function canGuide(
        SmartCatalogRoutingEvaluation $evaluation,
        DeclarativePlanExecutionResult $execution,
        string $content
    ): bool {
        return true;
    }
}
