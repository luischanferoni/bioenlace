<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use Yii;

final class AssistantContextAssemblyService
{
    private static ?AssistantContextAssemblyResult $lastResult = null;

    public static function lastResult(): ?AssistantContextAssemblyResult
    {
        return self::$lastResult;
    }

    public static function assembleForChannel(string $channel, int $userId): AssistantContextAssemblyResult
    {
        $result = new AssistantContextAssemblyResult();
        $areas = ChatPreprocessContext::contextAreas();
        if ($areas === []) {
            self::$lastResult = $result;

            return $result;
        }

        $extractions = ChatPreprocessContext::extractions();
        $anchors = AssistantContextAnchorResolver::resolve($userId, $extractions);
        $plan = AssistantContextAreaAspectResolver::plan($areas, $extractions, $channel, $anchors);
        if ($plan->isEmpty()) {
            self::$lastResult = $result;

            return $result;
        }

        $ctx = new AssistantContextLoadContext($userId, $channel, $plan->anchors, $extractions);
        $payload = [];
        $scopeApplied = [];

        foreach ($plan->aspectKeys as $aspectKey) {
            if (!AssistantContextHISAreaAspect::isValid($aspectKey)) {
                continue;
            }
            $started = hrtime(true);
            $data = AssistantContextAspectLoaderRegistry::load($aspectKey, $ctx);
            $elapsedMs = (int) ((hrtime(true) - $started) / 1_000_000);
            $payload[$aspectKey] = $data;
            $scopeApplied[$aspectKey] = $data['scope'] ?? [];
            $result->applied[] = [
                'aspect' => $aspectKey,
                'chars' => strlen(json_encode($data, JSON_UNESCAPED_UNICODE) ?: ''),
                'ms' => $elapsedMs,
            ];
        }

        if ($payload === []) {
            self::$lastResult = $result;

            return $result;
        }

        $limitations = self::globalLimitations();
        if ($limitations !== []) {
            $payload['limitations'] = $limitations;
        }

        $result->scopeApplied = array_merge($plan->anchors->toScopeArray(), ['aspects' => $scopeApplied]);
        $result->promptSection = AssistantContextFormatter::formatBlock($payload, $result->scopeApplied);

        if (self::isDebugEnabled()) {
            Yii::info([
                'context_applied' => $result->applied,
                'areas' => $areas,
            ], 'asistente-context');
        }

        self::$lastResult = $result;

        return $result;
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array<string, mixed>
     */
    public static function attachDebugIfEnabled(array $envelope): array
    {
        if (!self::isDebugEnabled() || self::$lastResult === null || self::$lastResult->applied === []) {
            return $envelope;
        }

        $envelope['context_applied'] = self::$lastResult->applied;

        return $envelope;
    }

    public static function resetForTests(): void
    {
        self::$lastResult = null;
        AssistantContextAspectLoaderRegistry::resetForTests();
    }

    /**
     * @return list<string>
     */
    private static function globalLimitations(): array
    {
        return [
            'arrival_time_not_recorded_in_system',
        ];
    }

    public static function isDebugEnabled(): bool
    {
        return (bool) (Yii::$app->params['asistente_context_debug'] ?? false);
    }
}
