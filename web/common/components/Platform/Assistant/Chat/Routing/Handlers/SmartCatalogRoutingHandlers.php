<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;

/**
 * Despacha handlers post catálogo inteligente (camino 1 IA).
 */
final class SmartCatalogRoutingHandlers
{
    /**
     * @return array<string, mixed>|null Envelope público o null para flujo legacy.
     */
    public static function tryHandle(
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId
    ): ?array {
        $decision = $evaluation->decision;

        if ($decision->isFueraDeHis()) {
            AssistantPlanningLogService::setFinalPath('1ia_fuera');

            return FueraDeHisHandler::handle($decision);
        }

        if ($decision->isMatch100()) {
            if ($decision->isDirectArticle() || $decision->isDirectTemplate()) {
                $envelope = DirectMatchHandler::handle($decision, $content, $userId);
                if ($envelope !== null) {
                    AssistantPlanningLogService::setFinalPath('1ia_direct');

                    return $envelope;
                }

                return null;
            }

            if ($decision->shouldRouteIntentDirectly()) {
                AssistantPlanningLogService::setFinalPath('1ia_clara');

                return ClaraRoutingHandler::handleSingle($content, $decision->primaryIntentId(), $userId);
            }
        }

        if ($decision->isDudosa()) {
            AssistantPlanningLogService::setFinalPath('1ia_dudosa');

            return DudosaRoutingHandler::handle();
        }

        if ($decision->isIncompletas()) {
            return IncompleteRoutingHandler::handle($evaluation, $content, $userId);
        }

        return null;
    }
}
