<?php

namespace common\components\Platform\Assistant\Planning;

use common\components\Domain\Content\Service\InfoContentResolverService;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideChannelConfig;
use common\components\Platform\Assistant\Chat\Channels\Synthesis\SynthesisChannelConfig;
use common\components\Platform\Assistant\Context\AssistantContextAnchorResolver;
use common\components\Platform\Assistant\Context\AssistantContextAspectLoaderRegistry;
use common\components\Platform\Assistant\Context\AssistantContextFormatter;
use common\components\Platform\Assistant\Context\AssistantContextHISAreaAspect;
use common\components\Platform\Assistant\Context\AssistantContextLoadContext;
use common\components\Platform\Assistant\Chat\ChatPreprocessContext;
use Yii;

/**
 * Ejecuta tool_ids del plan declarativo (aspect loaders + artículos).
 */
final class DeclarativePlanExecutor
{
    /**
     * @param list<string> $toolIds
     */
    public static function execute(array $toolIds, int $userId, string $channel = 'synthesis'): DeclarativePlanExecutionResult
    {
        $toolIds = array_values(array_unique(array_filter(array_map('strval', $toolIds), static fn (string $id): bool => trim($id) !== '')));
        if ($toolIds === []) {
            return new DeclarativePlanExecutionResult('', '', [], false);
        }

        $extractions = ChatPreprocessContext::extractions();
        $anchors = AssistantContextAnchorResolver::resolve($userId, $extractions);
        $ctx = new AssistantContextLoadContext($userId, $channel, $anchors, $extractions);

        $aspectPayload = [];
        $scopeApplied = array_merge($anchors->toScopeArray(), ['aspects' => []]);
        $articleBlock = '';
        $executed = [];
        $hasUsefulData = false;

        foreach ($toolIds as $toolId) {
            $toolId = trim($toolId);
            if ($toolId === '') {
                continue;
            }

            if (str_starts_with($toolId, 'aspect:')) {
                $aspectKey = trim(substr($toolId, strlen('aspect:')));
                if ($aspectKey === '' || !AssistantContextHISAreaAspect::isValid($aspectKey)) {
                    continue;
                }

                $started = hrtime(true);
                $data = AssistantContextAspectLoaderRegistry::load($aspectKey, $ctx);
                $elapsedMs = (int) ((hrtime(true) - $started) / 1_000_000);
                $encoded = json_encode($data, JSON_UNESCAPED_UNICODE) ?: '';
                $aspectPayload[$aspectKey] = $data;
                $scopeApplied['aspects'][$aspectKey] = is_array($data['scope'] ?? null) ? $data['scope'] : [];

                $row = [
                    'tool_id' => $toolId,
                    'ms' => $elapsedMs,
                    'chars' => strlen($encoded),
                    'had_null_fields' => self::hadNullFields($data),
                ];
                $executed[] = $row;
                AssistantPlanningLogService::addExecutedTool($row);

                if (self::isUsefulPayload($data)) {
                    $hasUsefulData = true;
                }

                continue;
            }

            if (str_starts_with($toolId, 'article:')) {
                $topic = trim(substr($toolId, strlen('article:')));
                if ($topic === '') {
                    continue;
                }

                $started = hrtime(true);
                $article = InfoContentResolverService::resolve($topic, self::currentIdEfector(), null);
                $elapsedMs = (int) ((hrtime(true) - $started) / 1_000_000);
                $formatted = '';
                if ($article !== null && ($userId <= 0 || InfoContentResolverService::isVisibleToUser($article, $userId))) {
                    $formatted = GuideChannelConfig::formatArticleContent(
                        trim((string) $article->title),
                        trim((string) $article->body)
                    );
                    if ($formatted !== '') {
                        $articleBlock = SynthesisChannelConfig::formatOptionalAttachment('article', $formatted);
                        $hasUsefulData = true;
                    }
                }

                $row = [
                    'tool_id' => $toolId,
                    'ms' => $elapsedMs,
                    'chars' => strlen($formatted),
                    'had_null_fields' => $article === null,
                ];
                $executed[] = $row;
                AssistantPlanningLogService::addExecutedTool($row);
            }
        }

        $scopedRecords = $aspectPayload !== []
            ? AssistantContextFormatter::formatBlock($aspectPayload, $scopeApplied)
            : '';

        return new DeclarativePlanExecutionResult($scopedRecords, $articleBlock, $executed, $hasUsefulData);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function isUsefulPayload(array $data): bool
    {
        if ($data === [] || isset($data['error'])) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed $value
     */
    private static function hadNullFields(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (self::hadNullFields($item)) {
                return true;
            }
        }

        return false;
    }

    private static function currentIdEfector(): ?int
    {
        try {
            if (!Yii::$app->has('user', true)) {
                return null;
            }
            $id = Yii::$app->user->getIdEfector();

            return $id > 0 ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
