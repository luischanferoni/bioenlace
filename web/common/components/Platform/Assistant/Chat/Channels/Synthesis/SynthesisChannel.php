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
            $content,
            $evaluation
        );

        $ctaButtons = SynthesisCtaResolver::resolveAll($evaluation, $userId);
        $text = self::consultSynthesisIa($prompt);
        if ($text === null || $text === '') {
            if ($ctaButtons === []) {
                return null;
            }
            $text = self::ctaFallbackText($ctaButtons);
        }

        if ($ctaButtons === []) {
            return AssistantContextAssemblyService::attachDebugIfEnabled(
                AssistantEnvelope::message($text)
            );
        }

        return AssistantContextAssemblyService::attachDebugIfEnabled(
            AssistantEnvelope::interactive($text, $ctaButtons)
        );
    }

    /**
     * @param list<array{label: string, intent_id: string}> $ctaButtons
     */
    private static function ctaFallbackText(array $ctaButtons): string
    {
        if (count($ctaButtons) === 1) {
            $label = $ctaButtons[0]['label'];

            return 'Para continuar, podés usar la opción «' . $label . '».';
        }

        $parts = [];
        foreach ($ctaButtons as $b) {
            $parts[] = '«' . $b['label'] . '»';
        }

        return 'Para continuar, elegí una de estas opciones: ' . implode(' o ', $parts) . '.';
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
