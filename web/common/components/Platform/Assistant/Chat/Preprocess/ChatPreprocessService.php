<?php

namespace common\components\Platform\Assistant\Chat\Preprocess;

use common\components\Platform\Assistant\Chat\Channels\Guide\GuideHistoryWindow;
use common\components\Platform\Assistant\Context\AssistantContextHISArea;
use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Preprocess\PreprocessExtractionCategoryCatalog;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;
use common\components\Platform\Assistant\Preprocess\PreprocessTagVocabularyCatalog;
use common\components\Platform\Core\Product\ProductMetadataPaths;
use Yii;
use common\components\Ai\IAManager;

/**
 * Preprocess: etiquetado 1ª IA (v1) + compat legacy {@see user_goal}.
 *
 * Prompt: {@see prompts/preprocess.yaml}. Predicados: {@see ChatChannelPolicy}.
 */
final class ChatPreprocessService
{
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
        return PreprocessExtractionCategoryCatalog::all();
    }

    /**
     * @return list<string>
     */
    public static function routingHints(): array
    {
        return PreprocessRoutingHintCatalog::all();
    }

    /**
     * @return list<string>
     * @deprecated Usar {@see PreprocessRoutingHintCatalog::legacyGoals()}.
     */
    public static function legacyGoals(): array
    {
        return PreprocessRoutingHintCatalog::legacyGoals();
    }

    public static function canonicalizeGoal(string $goal): string
    {
        $goal = trim($goal);
        if ($goal === '') {
            return 'ambiguous';
        }
        if ($goal === 'incompletas') {
            return 'guide';
        }
        if (!in_array($goal, PreprocessRoutingHintCatalog::legacyGoals(), true)) {
            return 'ambiguous';
        }

        return $goal;
    }

    public static function routingHintFromLegacyGoal(string $goal): string
    {
        return PreprocessRoutingHintCatalog::routingHintFromLegacyGoal(self::canonicalizeGoal($goal));
    }

    public static function canonicalizeRoutingHint(string $hint): string
    {
        $hint = mb_strtolower(trim($hint), 'UTF-8');
        if ($hint === '') {
            return 'dudosa';
        }
        if (!PreprocessRoutingHintCatalog::isValid($hint)) {
            return 'dudosa';
        }

        return $hint;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    public static function normalizeTags($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $tag) {
            if (!is_string($tag)) {
                continue;
            }
            $tag = mb_strtolower(trim($tag), 'UTF-8');
            $tag = (string) preg_replace('/[^a-z0-9_]+/u', '_', $tag);
            $tag = trim($tag, '_');
            if ($tag !== '' && !in_array($tag, $out, true)) {
                $out[] = $tag;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $tags
     */
    public static function userGoalFromRoutingHint(string $routingHint, array $tags): string
    {
        return PreprocessRoutingHintCatalog::legacyUserGoalFromRoutingHint(
            $routingHint,
            in_array('in_flow_question', $tags, true)
        );
    }

    public static function resetCacheForTests(): void
    {
        AssistantMetadataLoader::resetCacheForTests();
        AssistantContextHISArea::resetCacheForTests();
        PreprocessExtractionCategoryCatalog::resetCacheForTests();
        PreprocessRoutingHintCatalog::resetCacheForTests();
        PreprocessTagVocabularyCatalog::resetCacheForTests();
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   necesidad_usuario: string,
     *   routing_hint: string,
     *   tags: list<string>,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>,
     *   context_areas: list<string>,
     *   intent_ids_hint: list<string>
     * }|null null = falló la IA
     */
    public static function run(string $content, int $userId): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return self::emptyResult('');
        }

        return self::runAi($content, $userId);
    }

    public static function stablePromptPrefix(): string
    {
        $config = AssistantMetadataLoader::load(ProductMetadataPaths::preprocessPromptFile());
        $template = AssistantMetadataLoader::dotString($config, 'stable_prompt');
        if ($template === '') {
            Yii::warning('ChatPreprocessService: stable_prompt vacío en preprocess.yaml', __METHOD__);

            return 'Mensaje:';
        }

        return AssistantMetadataLoader::applyPlaceholders($template, [
            'routing_hints_list' => PreprocessRoutingHintCatalog::listForPrompt(),
            'preprocess_tags_vocabulary' => PreprocessTagVocabularyCatalog::listForPrompt(),
            'extraction_categories_list' => PreprocessExtractionCategoryCatalog::listForPrompt(),
            'context_his_areas_list' => AssistantContextHISArea::listForPrompt(),
            'conversation_history' => '(sin historial previo)',
        ]);
    }

    public static function userMessagePart(string $content): string
    {
        return trim($content);
    }

    public static function buildFullPrompt(string $content, int $userId = 0): string
    {
        $history = self::formatHistoryForPrompt($userId, $content);
        $prefix = self::stablePromptPrefix();
        if ($history !== '') {
            $prefix = str_replace('(sin historial previo)', $history, $prefix);
        }

        return $prefix . self::userMessagePart($content);
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   necesidad_usuario: string,
     *   routing_hint: string,
     *   tags: list<string>,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>,
     *   context_areas: list<string>,
     *   intent_ids_hint: list<string>
     * }
     */
    public static function normalizeFromAi(array $raw, string $fallbackContent): array
    {
        $routingHint = self::canonicalizeRoutingHint((string) ($raw['routing_hint'] ?? ''));
        if ($routingHint === 'dudosa' && isset($raw['user_goal'])) {
            $legacyGoal = self::canonicalizeGoal((string) $raw['user_goal']);
            $routingHint = self::routingHintFromLegacyGoal($legacyGoal);
        }

        $tags = self::normalizeTags($raw['tags'] ?? []);
        $goal = self::userGoalFromRoutingHint($routingHint, $tags);

        $normalized = isset($raw['normalized_text']) ? trim((string) $raw['normalized_text']) : '';
        if ($normalized === '') {
            $normalized = trim($fallbackContent);
        }

        $necesidad = isset($raw['necesidad_usuario']) ? trim((string) $raw['necesidad_usuario']) : '';
        if ($necesidad === '') {
            $actionText = isset($raw['action_text']) ? trim((string) $raw['action_text']) : '';
            $necesidad = $actionText !== '' ? $actionText : $normalized;
        }

        $actionText = isset($raw['action_text']) ? trim((string) $raw['action_text']) : '';

        return [
            'ok' => true,
            'normalized_text' => $normalized,
            'necesidad_usuario' => $necesidad,
            'routing_hint' => $routingHint,
            'tags' => $tags,
            'user_goal' => $goal,
            'action_text' => $actionText,
            'extractions' => self::normalizeExtractions($raw['extractions'] ?? []),
            'context_areas' => self::normalizeContextAreas($raw['context_areas'] ?? []),
            'intent_ids_hint' => self::normalizeIntentIdsHint($raw['intent_ids_hint'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function runAi(string $content, int $userId): ?array
    {
        $prompt = self::buildFullPrompt($content, $userId);

        try {
            $raw = IAManager::consultarIA($prompt, 'asistente-preprocess', 'analysis');
            if (!is_array($raw)) {
                return null;
            }

            return self::normalizeFromAi($raw, $content);
        } catch (\Throwable $e) {
            Yii::warning('ChatPreprocessService IA: ' . $e->getMessage(), 'asistente');

            return null;
        }
    }

    /**
     * @param mixed $raw
     * @return list<array{span: string, category: string, synonyms: list<string>}>
     */
    private static function normalizeExtractions($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $allowedCat = array_flip(self::allowedEntityCategories());
        $extractions = [];
        foreach ($raw as $ex) {
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

        return $extractions;
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
     * @param mixed $raw
     * @return list<string>
     */
    private static function normalizeIntentIdsHint($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            if (!is_string($id)) {
                continue;
            }
            $id = trim($id);
            if ($id !== '' && !in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    private static function formatHistoryForPrompt(int $userId, string $content): string
    {
        if ($userId <= 0) {
            return '';
        }

        return trim(GuideHistoryWindow::formatForPrompt($userId, $content, ''));
    }

    /**
     * @return array{
     *   ok: bool,
     *   normalized_text: string,
     *   necesidad_usuario: string,
     *   routing_hint: string,
     *   tags: list<string>,
     *   user_goal: string,
     *   action_text: string,
     *   extractions: list<array{span: string, category: string, synonyms: list<string>}>,
     *   context_areas: list<string>,
     *   intent_ids_hint: list<string>
     * }
     */
    private static function emptyResult(string $content): array
    {
        return [
            'ok' => true,
            'normalized_text' => $content,
            'necesidad_usuario' => $content,
            'routing_hint' => 'dudosa',
            'tags' => [],
            'user_goal' => 'ambiguous',
            'action_text' => '',
            'extractions' => [],
            'context_areas' => [],
            'intent_ids_hint' => [],
        ];
    }
}
