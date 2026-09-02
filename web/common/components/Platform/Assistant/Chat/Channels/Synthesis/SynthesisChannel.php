<?php

namespace common\components\Platform\Assistant\Chat\Channels\Synthesis;

use common\components\Ai\IAManager;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannel;
use common\components\Platform\Assistant\Chat\Envelope\AssistantEnvelope;
use common\components\Platform\Assistant\Context\AssistantContextAssemblyService;
use common\components\Platform\Assistant\Planning\DeclarativePlanExecutionResult;
use common\components\Platform\Assistant\Planning\SynthesisCtaResolver;
use common\components\Platform\Assistant\Planning\SmartCatalogRoutingEvaluation;
use Yii;

/**
 * 2ª IA síntesis para routing incompletas (reemplazo parcial de guide).
 */
final class SynthesisChannel
{
    /**
     * @param array<string, mixed> $firstIa
     * @return array<string, mixed>|null
     */
    public static function handle(
        array $firstIa,
        DeclarativePlanExecutionResult $execution,
        SmartCatalogRoutingEvaluation $evaluation,
        string $content,
        int $userId
    ): ?array {
        $prompt = SynthesisPromptAssembler::build(
            $firstIa,
            $execution->scopedSystemRecords,
            $execution->articleBlock,
            $content
        );

        $text = self::consultSynthesisIa($prompt);
        if ($text === null || $text === '') {
            return null;
        }

        $cta = SynthesisCtaResolver::resolve($evaluation, $userId);
        if ($cta === null) {
            return AssistantContextAssemblyService::attachDebugIfEnabled(
                AssistantEnvelope::message($text)
            );
        }

        return AssistantContextAssemblyService::attachDebugIfEnabled(
            AssistantEnvelope::interactive($text, [$cta])
        );
    }

    private static function consultSynthesisIa(string $prompt): ?string
    {
        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-synthesis', 'text-generation');
            if (is_string($raw) && trim($raw) !== '') {
                return trim($raw);
            }
            if (is_array($raw) && isset($raw['text'])) {
                $text = trim((string) $raw['text']);

                return $text !== '' ? $text : null;
            }
        } catch (\Throwable $e) {
            Yii::warning('SynthesisChannel: ' . $e->getMessage(), 'asistente');
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function iaFailureEnvelope(): array
    {
        return GuideChannel::iaFailureEnvelope();
    }
}
