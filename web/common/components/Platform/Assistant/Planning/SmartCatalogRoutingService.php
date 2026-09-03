<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\SmartCatalogEntry;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchService;
use common\components\Platform\Assistant\Context\AssistantContextAnchorResolver;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Orquesta match + plan declarativo + log de planificación.
 */
final class SmartCatalogRoutingService
{
    /**
     * @param array<string, mixed> $preprocess
     */
    public static function evaluate(array $preprocess, int $userId, string $rawContent = ''): SmartCatalogRoutingEvaluation
    {
        $firstIa = AssistantFirstIaAdapter::fromPreprocess($preprocess, $rawContent);
        $match = SmartCatalogMatchService::match($firstIa, $userId);
        $extractions = is_array($firstIa['extractions']) ? $firstIa['extractions'] : [];
        $anchors = AssistantContextAnchorResolver::resolve($userId, $extractions);
        $areas = is_array($firstIa['context_areas']) ? $firstIa['context_areas'] : [];

        $declarative = DeclarativePlanService::plan($areas, $extractions, $anchors, $match);

        AssistantPlanningLogService::begin($firstIa, $match->ranked);
        AssistantPlanningLogService::setDeclarativePlan(
            $declarative->toolIds,
            $declarative->reason,
            $declarative->needsPlanner
        );

        $decision = self::resolveRouting($firstIa, $match);
        AssistantPlanningLogService::setRoutingResult($decision->routingResult);

        return new SmartCatalogRoutingEvaluation($firstIa, $match, $decision, $declarative);
    }

    /**
     * @param array<string, mixed> $firstIa
     */
    private static function resolveRouting(
        array $firstIa,
        SmartCatalogMatchResult $match
    ): SmartCatalogRoutingDecision {
        $best = $match->best;
        $hint = trim((string) ($firstIa['routing_hint'] ?? 'dudosa'));
        $areas = is_array($firstIa['context_areas']) ? $firstIa['context_areas'] : [];

        if ($best !== null && $match->isClearWinner) {
            if ($best->matchOnly && $best->routingResult === 'fuera_de_his') {
                return self::fueraDeHisDecision($best);
            }

            $full = self::match100Decision($best);
            if ($full !== null) {
                return $full;
            }
        }

        if ($hint === 'fuera_de_his' || ($best !== null && $best->routingResult === 'fuera_de_his')) {
            return self::fueraDeHisDecision($best);
        }

        if (
            $hint === 'incompletas'
            || ($best !== null && $best->routingResult === 'incompletas')
            || ($areas !== [] && !$match->isClearWinner)
        ) {
            return new SmartCatalogRoutingDecision(
                'incompletas',
                PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint('incompletas'),
                [],
                '',
                '',
                $best,
            );
        }

        $routing = PreprocessRoutingHintCatalog::isValid($hint) ? $hint : 'dudosa';
        if ($routing === 'clara') {
            $routing = 'dudosa';
        }

        return new SmartCatalogRoutingDecision(
            $routing,
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint($routing),
            [],
            '',
            '',
            $best,
        );
    }

    private static function match100Decision(SmartCatalogEntry $best): ?SmartCatalogRoutingDecision
    {
        $goal = PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint('clara');

        if ($best->toolType === 'article' && $best->toolRef !== '') {
            return new SmartCatalogRoutingDecision(
                'clara',
                $goal,
                [],
                '',
                $best->toolRef,
                $best,
            );
        }

        if ($best->responseTemplate !== '') {
            return new SmartCatalogRoutingDecision(
                'clara',
                $goal,
                [],
                $best->responseTemplate,
                '',
                $best,
            );
        }

        if ($best->toolType === 'intent' && $best->toolRef !== '') {
            return new SmartCatalogRoutingDecision(
                'clara',
                $goal,
                [$best->toolRef],
                '',
                '',
                $best,
            );
        }

        return null;
    }

    private static function fueraDeHisDecision(?SmartCatalogEntry $entry): SmartCatalogRoutingDecision
    {
        return new SmartCatalogRoutingDecision(
            'fuera_de_his',
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint('fuera_de_his'),
            [],
            self::fueraDeHisText(),
            '',
            $entry,
        );
    }

    private static function fueraDeHisText(): string
    {
        $config = AssistantMetadataLoader::load(ProductMetadataPaths::smartCatalogRoutingFile());
        $text = AssistantMetadataLoader::dotString($config, 'fuera_de_his_text');

        return $text !== '' ? $text : 'No puedo ayudarte con esa consulta desde el asistente del sistema de salud.';
    }
}
