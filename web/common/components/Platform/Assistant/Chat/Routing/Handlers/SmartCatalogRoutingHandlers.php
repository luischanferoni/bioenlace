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
            // Artículo / template / intent: siempre Guide (2ª IA). Sin DirectMatch.
            if (
                $decision->isDirectArticle()
                || $decision->isDirectTemplate()
                || $decision->shouldRouteIntentDirectly()
            ) {
                $envelope = IncompleteRoutingHandler::handle($evaluation, $content, $userId);
                if ($envelope !== null) {
                    return $envelope;
                }

                if ($decision->shouldRouteIntentDirectly()) {
                    return ClaraRoutingHandler::handleSingle($content, $decision->primaryIntentId(), $userId);
                }

                return null;
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
