<?php

namespace common\components\Platform\Assistant\Chat\Preprocess;

use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use Yii;
use common\components\Ai\IAManager;

/**
 * Preprocess: canal (user_goal), texto normalizado y extracciones (spans).
 *
 * El `user_goal` lo decide la IA; no hay piso PHP ni fallback heurístico si la IA falla.
 * Prompt: {@see prompts/preprocess.yaml}. Predicados de dominio: {@see ChatChannelPolicy}.
 */
final class ChatPreprocessService
{
    public const GOALS = [
        'guide',
        'operational',
        'ambiguous',
        'in_flow_question',
    ];

    private const ENTITY_CATEGORIES = ['servicio', 'efector', 'persona', 'profesional', 'turno', 'tiempo'];

    public static function isClinicalSymptomContent(string $content): bool
    {
        return ChatChannelPolicy::isClinicalSymptomContent($content);
    }

    public static function isStaffDataAccessQuery(string $content): bool
    {
        return ChatChannelPolicy::isStaffDataAccessQuery($content);
    }

    public static function isStaffDataAccessEditQuery(string $content): bool
    {
        return ChatChannelPolicy::isStaffDataAccessEditQuery($content);
    }

    public static function isStaffDataAccessOperationalQuery(string $content): bool
    {
        return ChatChannelPolicy::isStaffDataAccessOperationalQuery($content);
    }

    /**
     * @return list<string>
     */
    public static function allowedEntityCategories(): array
    {
        return self::ENTITY_CATEGORIES;
    }

    public static function canonicalizeGoal(string $goal): string
    {
        $goal = trim($goal);
        if ($goal === '') {
            return 'ambiguous';
        }
        if (!in_array($goal, self::GOALS, true)) {
            return 'ambiguous';
        }

        return $goal;
    }

    public static function resetCacheForTests(): void
    {
        AssistantMetadataLoader::resetCacheForTests();
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>,
     *   context_areas: list<string>
     * }|null null = falló la IA (sin clasificar por heurística)
     */
    public static function run(string $content, int $userId): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return self::emptyResult('');
        }

        return self::runAi($content);
    }

    public static function stablePromptPrefix(): string
    {
        $config = AssistantMetadataLoader::load(ProductMetadataPaths::preprocessPromptFile());
        $template = AssistantMetadataLoader::dotString($config, 'stable_prompt');
        if ($template === '') {
            Yii::warning('ChatPreprocessService: stable_prompt vacío en preprocess.yaml', __METHOD__);

            return 'Mensaje:';
        }

        $categoriesList = self::allowedEntityCategories();

        return AssistantMetadataLoader::applyPlaceholders($template, [
            'goals_json' => json_encode(self::GOALS, JSON_UNESCAPED_UNICODE),
            'categories_json' => json_encode($categoriesList, JSON_UNESCAPED_UNICODE),
            'categories_human' => implode(', ', $categoriesList),
            'context_areas_catalog' => AssistantContextHISArea::catalogForPreprocess(),
        ]);
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
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>
     * }|null
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
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>
     * }
     */
    private static function normalizeResult(array $raw, string $fallbackContent): array
    {
        $goal = isset($raw['user_goal']) ? trim((string) $raw['user_goal']) : 'ambiguous';
        $goal = self::canonicalizeGoal($goal);

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

        return [
            'ok' => true,
            'normalized_text' => $normalized,
            'user_goal' => $goal,
            'action_text' => $actionText,
            'extractions' => $extractions,
            'context_areas' => self::normalizeContextAreas($raw['context_areas'] ?? []),
        ];
    }

    /**
     * @param mixed $rawAreas
     * @return list<string>
     */
    public static function normalizeContextAreas($rawAreas): array
    {
        if (!is_array($rawAreas)) {
            return [];
        }
        $out = [];
        foreach ($rawAreas as $row) {
            $id = trim((string) $row);
            if ($id === '' || !AssistantContextHISArea::isValid($id)) {
                continue;
            }
            $out[] = $id;
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>,
     *   context_areas: list<string>
     * }
     */
    private static function emptyResult(string $content): array
    {
        return [
            'ok' => true,
            'normalized_text' => $content,
            'user_goal' => 'ambiguous',
            'action_text' => '',
            'extractions' => [],
            'context_areas' => [],
        ];
    }
}
