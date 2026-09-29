<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\DiscoveryIndex;
use common\components\Platform\Assistant\Catalog\DiscoveryResult;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;
use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\Context\AssistantContextAreaDerivation;
use common\components\Platform\Assistant\Context\AssistantContextAnchorResolver;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Orquesta discovery (tags) + plan declarativo + decisión de canal.
 *
 * Ya no usa smart-catalog / direct-doors en el hot path.
 */
final class SmartCatalogRoutingService
{
    /**
     * @param array<string, mixed> $preprocess
     */
    public static function evaluate(array $preprocess, int $userId, string $rawContent = ''): SmartCatalogRoutingEvaluation
    {
        $firstIa = AssistantFirstIaAdapter::fromPreprocess($preprocess, $rawContent);
        $message = trim($rawContent);
        if ($message === '') {
            $message = trim((string) ($firstIa['normalized_text'] ?? ''));
        }

        $discovery = DiscoveryIndex::match($firstIa, $message, $userId);
        $match = self::emptyMatch();

        $firstIa['context_areas'] = self::mergeDerivedAreas(
            is_array($firstIa['context_areas']) ? $firstIa['context_areas'] : [],
            AssistantContextAreaDerivation::fromIntentIds($discovery->intentIds(8))
        );
        // Artículo discovery: área person si el hit es representación-like y no hay intent.
        if ($discovery->primaryArticleTopic() !== '' && $firstIa['context_areas'] === []) {
            $topic = $discovery->primaryArticleTopic();
            if ($topic === 'representacion' || str_contains($topic, 'represent')) {
                $firstIa['context_areas'] = [AssistantContextHISArea::PERSON];
            }
        }

        $extractions = is_array($firstIa['extractions']) ? $firstIa['extractions'] : [];
        $anchors = AssistantContextAnchorResolver::resolve($userId, $extractions);
        $areas = is_array($firstIa['context_areas']) ? $firstIa['context_areas'] : [];

        $declarative = DeclarativePlanService::plan($areas, $extractions, $anchors, null);
        $declarative = self::mergeDiscoveryArticles($declarative, $discovery);

        AssistantPlanningLogService::begin($firstIa, []);
        AssistantPlanningLogService::setDeclarativePlan(
            $declarative->toolIds,
            $declarative->reason,
            $declarative->needsPlanner
        );

        $decision = self::resolveRouting($firstIa, $discovery, $message);
        AssistantPlanningLogService::setRoutingResult($decision->routingResult);

        return new SmartCatalogRoutingEvaluation($firstIa, $match, $decision, $declarative);
    }

    /**
     * @param array<string, mixed> $firstIa
     */
    private static function resolveRouting(
        array $firstIa,
        DiscoveryResult $discovery,
        string $message
    ): SmartCatalogRoutingDecision {
        if (ChatChannelPolicy::isGreetingOnly($message)) {
            return self::greetingDecision();
        }

        $hint = PreprocessRoutingHintCatalog::applyAlias(
            (string) ($firstIa['routing_hint'] ?? PreprocessRoutingHintCatalog::SIN_PEDIDO)
        );
        $areas = is_array($firstIa['context_areas']) ? $firstIa['context_areas'] : [];
        $tags = is_array($firstIa['tags'] ?? null) ? $firstIa['tags'] : [];

        if ($hint === PreprocessRoutingHintCatalog::FUERA_HIS || self::hasFueraHisTag($tags)) {
            return self::fueraDeHisDecision();
        }

        $fromStates = self::stateTagDecision($discovery);
        if ($fromStates !== null) {
            return $fromStates;
        }

        if ($hint === PreprocessRoutingHintCatalog::SIN_PEDIDO && $areas === [] && $discovery->isEmpty()) {
            return new SmartCatalogRoutingDecision(
                PreprocessRoutingHintCatalog::PATH_NO_ACTION,
                PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                    PreprocessRoutingHintCatalog::PATH_NO_ACTION
                ),
                [],
                '',
                '',
                null,
            );
        }

        if (
            $hint === PreprocessRoutingHintCatalog::GUIDE
            || !$discovery->isEmpty()
            || $areas !== []
        ) {
            return new SmartCatalogRoutingDecision(
                PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT,
                PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                    PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT
                ),
                $discovery->intentIds(4),
                '',
                $discovery->primaryArticleTopic(),
                null,
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
            null,
        );
    }

    private static function stateTagDecision(DiscoveryResult $discovery): ?SmartCatalogRoutingDecision
    {
        $ids = $discovery->intentIds(4);
        if ($ids === []) {
            return null;
        }

        $path = count($ids) === 1
            ? PreprocessRoutingHintCatalog::PATH_MATCH_DIRECT
            : PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT;

        return new SmartCatalogRoutingDecision(
            $path,
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint($path),
            $ids,
            '',
            $discovery->primaryArticleTopic(),
            null,
        );
    }

    private static function greetingDecision(): SmartCatalogRoutingDecision
    {
        return new SmartCatalogRoutingDecision(
            PreprocessRoutingHintCatalog::PATH_NO_ACTION,
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                PreprocessRoutingHintCatalog::PATH_NO_ACTION
            ),
            [],
            '',
            '',
            null,
        );
    }

    private static function fueraDeHisDecision(): SmartCatalogRoutingDecision
    {
        return new SmartCatalogRoutingDecision(
            PreprocessRoutingHintCatalog::PATH_OUTSIDE,
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                PreprocessRoutingHintCatalog::PATH_OUTSIDE
            ),
            [],
            self::fueraDeHisText(),
            '',
            null,
        );
    }

    private static function fueraDeHisText(): string
    {
        $config = AssistantMetadataLoader::load(ProductMetadataPaths::smartCatalogRoutingFile());
        $text = AssistantMetadataLoader::dotString($config, 'fuera_de_his_text');

        return $text !== ''
            ? $text
            : 'No puedo ayudarte con esa consulta desde el asistente del sistema de salud.';
    }

    /**
     * @param list<string> $existing
     * @param list<string> $derived
     * @return list<string>
     */
    private static function mergeDerivedAreas(array $existing, array $derived): array
    {
        $kept = [];
        foreach ($existing as $area) {
            if (!is_string($area)) {
                continue;
            }
            $area = trim($area);
            if ($area !== '' && AssistantContextHISArea::isContextOnly($area)) {
                $kept[] = $area;
            }
        }

        return AssistantContextHISArea::sortByProductPriority(array_merge($kept, $derived));
    }

    private static function mergeDiscoveryArticles(
        DeclarativePlanResult $plan,
        DiscoveryResult $discovery
    ): DeclarativePlanResult {
        $toolIds = $plan->toolIds;
        $reasons = $plan->reason !== '' ? [$plan->reason] : [];
        foreach ($discovery->articleHits as $hit) {
            $topic = trim((string) ($hit['topic'] ?? ''));
            if ($topic === '') {
                continue;
            }
            $toolId = 'article:' . $topic;
            if (!in_array($toolId, $toolIds, true)) {
                $toolIds[] = $toolId;
                $reasons[] = 'discovery:article:' . $topic;
            }
        }
        $toolIds = array_values(array_unique($toolIds));
        $needsPlanner = $plan->needsPlanner;
        $plannerReason = $plan->plannerReason;
        if ($toolIds !== [] && $plannerReason === 'empty_plan') {
            $needsPlanner = false;
            $plannerReason = null;
        }

        return new DeclarativePlanResult(
            $toolIds,
            implode('; ', array_filter($reasons)),
            $needsPlanner,
            $plannerReason,
        );
    }

    /**
     * @param list<mixed> $tags
     */
    private static function hasFueraHisTag(array $tags): bool
    {
        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            if (PreprocessRoutingHintCatalog::applyAlias(trim($tag)) === PreprocessRoutingHintCatalog::FUERA_HIS
                || trim($tag) === 'fuera_his'
            ) {
                return true;
            }
        }

        return false;
    }

    private static function emptyMatch(): SmartCatalogMatchResult
    {
        return new SmartCatalogMatchResult([], null, 0, false);
    }
}
