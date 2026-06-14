<?php

namespace common\components\Platform\Assistant\Chat\Preprocess;

use Yii;
use common\components\Platform\Ai\IAManager;
use common\components\Platform\Assistant\IntentEngine\IntentClassificationRulesService;

/**
 * Preprocess: canal (user_goal), texto normalizado y extracciones (spans).
 *
 * Detección léxica y overrides de goal: metadata {@see IntentClassificationRulesService}.
 */
final class ChatPreprocessService
{
    public const GOALS = [
        'operational',
        'conversational',
        'informational',
        'in_flow_question',
        'meta',
        'unclear',
    ];

    public static function isClinicalSymptomContent(string $content): bool
    {
        return IntentClassificationRulesService::isClinicalSymptomContent($content);
    }

    public static function isStaffDataAccessQuery(string $content): bool
    {
        return IntentClassificationRulesService::isStaffDataAccessQuery($content);
    }

    public static function isStaffDataAccessEditQuery(string $content): bool
    {
        return IntentClassificationRulesService::isStaffDataAccessEditQuery($content);
    }

    public static function isStaffDataAccessOperationalQuery(string $content): bool
    {
        return IntentClassificationRulesService::isStaffDataAccessOperationalQuery($content);
    }

    /**
     * @return list<string>
     */
    public static function allowedEntityCategories(): array
    {
        $fromMeta = IntentClassificationRulesService::chatPreprocessEntityCategories();

        return $fromMeta !== [] ? $fromMeta : ['servicio', 'efector', 'persona', 'profesional', 'turno'];
    }

    /**
     * @return array{
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>
     * }
     */
    public static function run(string $content, int $userId): array
    {
        $content = trim($content);
        if ($content === '') {
            return self::emptyResult('');
        }

        $ia = self::runAi($content);
        if ($ia !== null) {
            return $ia;
        }

        return self::heuristicFallback($content);
    }

    public static function stablePromptPrefix(): string
    {
        return IntentClassificationRulesService::chatPreprocessStablePromptPrefix();
    }

    public static function userMessagePart(string $content): string
    {
        return trim($content);
    }

    public static function buildFullPrompt(string $content): string
    {
        return self::stablePromptPrefix() . self::userMessagePart($content);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function runAi(string $content): ?array
    {
        $prompt = self::buildFullPrompt($content);

        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-preprocess', 'analysis');
            if (!is_array($raw)) {
                return null;
            }
            return self::normalizeResult($raw, $content);
        } catch (\Throwable $e) {
            Yii::warning('ChatPreprocessService IA: ' . $e->getMessage(), 'asistente');
            return null;
        }
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private static function normalizeResult(array $raw, string $fallbackContent): array
    {
        $goal = isset($raw['user_goal']) ? trim((string) $raw['user_goal']) : 'unclear';
        if (!in_array($goal, self::GOALS, true)) {
            $goal = 'unclear';
        }

        $normalized = isset($raw['normalized_text']) ? trim((string) $raw['normalized_text']) : '';
        if ($normalized === '') {
            $normalized = $fallbackContent;
        }

        $actionText = isset($raw['action_text']) ? trim((string) $raw['action_text']) : '';

        $allowedCat = array_flip(self::allowedEntityCategories());
        $extractions = [];
        if (isset($raw['extractions']) && is_array($raw['extractions'])) {
            foreach ($raw['extractions'] as $ex) {
                if (!is_array($ex)) {
                    continue;
                }
                $span = isset($ex['span']) ? trim((string) $ex['span']) : '';
                $cat = isset($ex['category']) ? trim((string) $ex['category']) : '';
                if ($span === '' || $cat === '' || !isset($allowedCat[$cat])) {
                    continue;
                }
                $syns = [];
                if (isset($ex['synonyms']) && is_array($ex['synonyms'])) {
                    foreach ($ex['synonyms'] as $s) {
                        if (is_string($s) && trim($s) !== '') {
                            $syns[] = trim($s);
                        }
                        if (count($syns) >= 2) {
                            break;
                        }
                    }
                }
                $extractions[] = [
                    'span' => $span,
                    'category' => $cat,
                    'synonyms' => $syns,
                ];
            }
        }

        $goal = IntentClassificationRulesService::applyChatPreprocessGoalOverrides($normalized, $goal);

        return [
            'normalized_text' => $normalized,
            'user_goal' => $goal,
            'action_text' => $actionText,
            'extractions' => $extractions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function heuristicFallback(string $content): array
    {
        $goal = IntentClassificationRulesService::resolveHeuristicUserGoal($content);

        return [
            'normalized_text' => $content,
            'user_goal' => $goal,
            'action_text' => $goal === 'operational' ? $content : '',
            'extractions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyResult(string $content): array
    {
        return [
            'normalized_text' => $content,
            'user_goal' => 'unclear',
            'action_text' => '',
            'extractions' => [],
        ];
    }
}
