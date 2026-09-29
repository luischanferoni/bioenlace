<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Platform\Assistant\Catalog\DiscoveryIndex;
use common\components\Platform\Assistant\Catalog\DiscoveryResult;
use common\components\Platform\Assistant\Catalog\SmartCatalogMatchResult;
use common\components\Platform\Assistant\Chat\Preprocess\ChatChannelPolicy;
use common\components\Platform\Assistant\Context\AssistantContextAnchorResolver;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Orquesta discovery (tags) + plan declarativo + decisión de canal.
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
        $match = new SmartCatalogMatchResult();

        // Áreas HIS deprecadas: discovery adjunta intents/artículos; plan solo por tools discovery.
        $firstIa['context_areas'] = [];

        $extractions = is_array($firstIa['extractions']) ? $firstIa['extractions'] : [];
        $anchors = AssistantContextAnchorResolver::resolve($userId, $extractions);

        $declarative = DeclarativePlanService::plan([], $extractions, $anchors);
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
        $tags = is_array($firstIa['tags'] ?? null) ? $firstIa['tags'] : [];

        if ($hint === PreprocessRoutingHintCatalog::FUERA_HIS || self::hasFueraHisTag($tags)) {
            return self::fueraDeHisDecision();
        }

        $fromStates = self::stateTagDecision($discovery);
        if ($fromStates !== null) {
            return $fromStates;
        }

        if ($hint === PreprocessRoutingHintCatalog::SIN_PEDIDO && $discovery->isEmpty()) {
            return self::dudosaDecision();
        }

        if ($hint === PreprocessRoutingHintCatalog::GUIDE || !$discovery->isEmpty()) {
            return new SmartCatalogRoutingDecision(
                PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT,
                PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                    PreprocessRoutingHintCatalog::PATH_NEEDS_CONTEXT
                ),
                $discovery->intentIds(4),
                '',
                $discovery->primaryArticleTopic(),
            );
        }

        return self::dudosaDecision();
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
        );
    }

    private static function greetingDecision(): SmartCatalogRoutingDecision
    {
        return self::dudosaDecision();
    }

    private static function dudosaDecision(): SmartCatalogRoutingDecision
    {
        return new SmartCatalogRoutingDecision(
            PreprocessRoutingHintCatalog::PATH_NO_ACTION,
            PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
                PreprocessRoutingHintCatalog::PATH_NO_ACTION
            ),
            [],
            '',
            '',
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
        );
    }

    private static function fueraDeHisText(): string
    {
        $config = AssistantMetadataLoader::load(ProductMetadataPaths::channelLimitsFile());
        $text = AssistantMetadataLoader::dotString($config, 'fuera_de_his_text');

        return $text !== ''
            ? $text
            : 'No puedo ayudarte con esa consulta desde el asistente del sistema de salud.';
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
            if (trim($tag) === 'fuera_his') {
                return true;
            }
        }

        return false;
    }
}
