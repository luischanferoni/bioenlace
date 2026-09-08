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
        $hint = PreprocessRoutingHintCatalog::applyAlias(
            (string) ($firstIa['routing_hint'] ?? PreprocessRoutingHintCatalog::SIN_PEDIDO)
        );
        $areas = is_array($firstIa['context_areas']) ? $firstIa['context_areas'] : [];

        if ($best !== null && $match->isClearWinner) {
            if (
                $best->matchOnly
                && $best->routingResult === PreprocessRoutingHintCatalog::PATH_OUTSIDE
            ) {
                return self::fueraDeHisDecision($best);
            }

            $full = self::match100Decision($best);
            if ($full !== null) {
                return $full;
            }
        }

        if (
            $hint === PreprocessRoutingHintCatalog::PEDIDO_FUERA_HIS
            || ($best !== null && $best->routingResult === PreprocessRoutingHintCatalog::PATH_OUTSIDE)
        ) {
            return self::fueraDeHisDecision($best);
        }

        if ($hint === PreprocessRoutingHintCatalog::SIN_PEDIDO && $areas === []) {
            return new SmartCatalogRoutingDecision(
                PreprocessRoutingHintCatalog::PATH_NO_ACTION,
                PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                    PreprocessRoutingHintCatalog::PATH_NO_ACTION
                ),
                [],
                '',
                '',
                $best,
            );
        }

        // Pedido claro (uno o varios) sin match 100 %, o fila/área HIS → contexto + Guide.
        if (
            $hint === PreprocessRoutingHintCatalog::PEDIDO_CLARO
            || $hint === PreprocessRoutingHintCatalog::PEDIDO_CLARO_MULTIPLE
            || ($best !== null && $best->routingResult === PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT)
            || ($areas !== [] && !$match->isClearWinner)
        ) {
            return new SmartCatalogRoutingDecision(
                PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT,
                PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                    PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT
                ),
                [],
                '',
                '',
                $best,
            );
        }

        return new SmartCatalogRoutingDecision(
            PreprocessRoutingHintCatalog::PATH_NO_ACTION,
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                PreprocessRoutingHintCatalog::PATH_NO_ACTION
            ),
            [],
            '',
            '',
            $best,
        );
    }

    private static function match100Decision(SmartCatalogEntry $best): ?SmartCatalogRoutingDecision
    {
        $goal = PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
            PreprocessRoutingHintCatalog::PATH_MATCH_DIRECT
        );

        if ($best->toolType === 'article' && $best->toolRef !== '') {
            return new SmartCatalogRoutingDecision(
                PreprocessRoutingHintCatalog::PATH_MATCH_DIRECT,
                $goal,
                [],
                '',
                $best->toolRef,
                $best,
            );
        }

        if ($best->responseTemplate !== '') {
            return new SmartCatalogRoutingDecision(
                PreprocessRoutingHintCatalog::PATH_MATCH_DIRECT,
                $goal,
                [],
                $best->responseTemplate,
                '',
                $best,
            );
        }

        if ($best->toolType === 'intent' && $best->toolRef !== '') {
            return new SmartCatalogRoutingDecision(
                PreprocessRoutingHintCatalog::PATH_MATCH_DIRECT,
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
            PreprocessRoutingHintCatalog::PATH_OUTSIDE,
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                PreprocessRoutingHintCatalog::PATH_OUTSIDE
            ),
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
