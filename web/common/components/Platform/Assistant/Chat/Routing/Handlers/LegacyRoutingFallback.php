<?php

namespace common\components\Platform\Assistant\Chat\Routing\Handlers;

use common\components\Platform\Assistant\Chat\Channels\Operational\OperationalChannel;
use common\components\Platform\Assistant\Chat\Channels\Synthesis\SynthesisChannel;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Planning\AssistantPlanningLogService;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;

/**
 * Fallback post catálogo inteligente sin {@see GuideChannel} (fase 07).
 */
final class LegacyRoutingFallback
{
    /**
     * @return array<string, mixed>
     */
    public static function handle(
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId
    ): array {
        $legacyGoal = $evaluation->decision->legacyUserGoal;

        if ($legacyGoal === 'operational' || $legacyGoal === 'in_flow_question') {
            AssistantPlanningLogService::setFinalPath('legacy_operational_intent_classifier');

            return OperationalChannel::handle($content, null, $userId);
        }

        if ($legacyGoal === 'ambiguous' || $evaluation->decision->isDudosa()) {
            AssistantPlanningLogService::setFinalPath('1ia_dudosa');

            return DudosaRoutingHandler::handle();
        }

        AssistantPlanningLogService::setFinalPath('synthesis_unavailable');

        $failure = SynthesisChannel::iaFailureEnvelope();
        if (($failure['success'] ?? true) === false && isset($failure['error'])) {
            return AssistantEnvelope::message((string) $failure['error']);
        }

        return AssistantEnvelope::message(
            'No pudimos generar una respuesta en este momento. Probá de nuevo en unos segundos.'
        );
    }
}
