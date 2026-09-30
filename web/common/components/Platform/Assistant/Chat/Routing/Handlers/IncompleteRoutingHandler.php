<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannel;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutionResult;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutor;
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
        $execution = DeclarativePlanExecutor::execute($evaluation->declarativePlan->toolIds, $userId);

        return self::finalizeGuide($evaluation, $content, $userId, $execution);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function finalizeGuide(
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId,
        DeclarativePlanExecutionResult $execution
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

        AssistantPlanningLogService::setFinalPath('2ia_guide');

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
