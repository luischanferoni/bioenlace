<?php

namespace common\components\Platform\Assistant\IntentEngine;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Motor genérico sobre metadata de producto ({@see ProductMetadataPaths::intentClassificationRulesFile()}).
 */
final class IntentClassificationRulesService
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public const STAFF_DATA_ACCESS_QUERY_RULES = [
        'staff_data_access_count_or_resumen',
        'staff_data_access_list',
        'staff_data_access_list_phrase',
    ];

    public const STAFF_DATA_ACCESS_OPERATIONAL_RULES = [
        'staff_data_access_count_or_resumen',
        'staff_data_access_list',
        'staff_data_access_list_phrase',
        'staff_data_access_edit',
    ];

    public static function ruleMatches(string $ruleId, string $message): bool
    {
        $ruleId = trim($ruleId);
        if ($ruleId === '') {
            return false;
        }
        $folded = self::foldMessage($message);
        if ($folded === '') {
            return false;
        }
        $rule = self::matchRules()[$ruleId] ?? null;

        return is_array($rule) && self::evaluateMatchRule($rule, $folded);
    }

    /**
     * @param list<string> $ruleIds
     */
    public static function matchesAnyRule(string $message, array $ruleIds): bool
    {
        foreach ($ruleIds as $ruleId) {
            if (is_string($ruleId) && self::ruleMatches($ruleId, $message)) {
                return true;
            }
        }

        return false;
    }

    public static function isClinicalSymptomContent(string $message): bool
    {
        return self::ruleMatches('clinical_symptom', $message);
    }

    public static function isStaffDataAccessQuery(string $message): bool
    {
        return self::matchesAnyRule($message, self::staffDataAccessQueryRules());
    }

    public static function isStaffDataAccessEditQuery(string $message): bool
    {
        return self::ruleMatches(self::staffDataAccessEditRule(), $message);
    }

    public static function isStaffDataAccessOperationalQuery(string $message): bool
    {
        return self::matchesAnyRule($message, self::staffDataAccessOperationalRules());
    }

    public static function isCapabilityMenuQuery(string $message): bool
    {
        return self::ruleMatches('help_menu_query', $message);
    }

    public static function isSchedulingOperational(string $message): bool
    {
        return self::ruleMatches('scheduling_operational', $message);
    }

    /**
     * @return list<string>
     */
    public static function chatPreprocessEntityCategories(): array
    {
        $raw = self::chatPreprocessSection()['entity_categories'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $cat) {
            if (is_string($cat) && trim($cat) !== '') {
                $out[] = trim($cat);
            }
        }

        return $out;
    }

    public static function chatPreprocessStablePromptPrefix(): string
    {
        $prompt = self::chatPreprocessSection()['prompt'] ?? [];
        if (!is_array($prompt)) {
            return '';
        }
        $intro = trim((string) ($prompt['intro'] ?? ''));
        $rules = $prompt['rules'] ?? [];
        $categories = json_encode(self::chatPreprocessEntityCategories(), JSON_UNESCAPED_UNICODE);
        $goals = json_encode(
            ['operational', 'conversational', 'informational', 'in_flow_question', 'meta', 'unclear'],
            JSON_UNESCAPED_UNICODE
        );

        $lines = [
            $intro !== '' ? $intro : 'Analizá el mensaje del usuario.',
            '',
            'Respondé ÚNICAMENTE con JSON:',
            '{',
            '  "normalized_text": "mensaje limpio, ortografía corregida y abreviaturas médicas abiertas cuando aplique",',
            '  "user_goal": "uno de ' . $goals . '",',
            '  "action_text": "fragmento que expresa la acción pedida o vacío",',
            '  "extractions": [',
            '    {',
            '      "span": "fragmento mencionado (no palabras sueltas)",',
            '      "category": "una de ' . $categories . '",',
            '      "synonyms": ["0-2 variantes ortográficas o abreviaturas"]',
            '    }',
            '  ]',
            '}',
            '',
            'Reglas:',
        ];
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                if (is_string($rule) && trim($rule) !== '') {
                    $lines[] = '- ' . trim($rule);
                }
            }
        }
        $lines[] = '';
        $lines[] = 'Mensaje:';

        return implode("\n", $lines);
    }

    public static function applyChatPreprocessGoalOverrides(string $normalized, string $goal): string
    {
        $overrides = self::chatPreprocessSection()['goal_overrides'] ?? [];
        if (!is_array($overrides)) {
            return $goal;
        }

        foreach ($overrides as $block) {
            if (!is_array($block)) {
                continue;
            }
            $toGoal = trim((string) ($block['to_goal'] ?? ''));
            if ($toGoal === '') {
                continue;
            }

            $whenRule = trim((string) ($block['when_rule'] ?? ''));
            $whenAny = $block['when_any_rule'] ?? [];
            $matches = false;
            if ($whenRule !== '' && self::ruleMatches($whenRule, $normalized)) {
                $matches = true;
            } elseif (is_array($whenAny) && $whenAny !== [] && self::matchesAnyRule($normalized, $whenAny)) {
                $matches = true;
            }
            if (!$matches) {
                continue;
            }

            $fromGoals = $block['from_goals'] ?? null;
            if (is_array($fromGoals) && $fromGoals !== []) {
                if (!in_array($goal, $fromGoals, true)) {
                    continue;
                }
            } else {
                $fromGoal = trim((string) ($block['from_goal'] ?? ''));
                if ($fromGoal !== '' && $goal !== $fromGoal) {
                    continue;
                }
            }

            $whenNot = trim((string) ($block['when_not_rule'] ?? ''));
            if ($whenNot !== '' && self::ruleMatches($whenNot, $normalized)) {
                continue;
            }

            return $toGoal;
        }

        return $goal;
    }

    public static function resolveHeuristicUserGoal(string $content): string
    {
        $blocks = self::chatPreprocessSection()['heuristic_fallback'] ?? [];
        if (!is_array($blocks)) {
            return 'unclear';
        }

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $goal = trim((string) ($block['goal'] ?? ''));
            if ($goal === '') {
                continue;
            }
            $whenRule = trim((string) ($block['when_rule'] ?? ''));
            $whenAny = $block['when_any_rule'] ?? [];
            if ($whenRule !== '' && self::ruleMatches($whenRule, $content)) {
                return $goal;
            }
            if (is_array($whenAny) && $whenAny !== [] && self::matchesAnyRule($content, $whenAny)) {
                return $goal;
            }
        }

        return 'unclear';
    }

    /**
     * @return array<string, mixed>
     */
    public static function conversationalChannelConfig(): array
    {
        $cfg = self::loadConfig()['conversational_channel'] ?? [];

        return is_array($cfg) ? $cfg : [];
    }

    public static function scoreAdjustment(string $message, string $actionId): int
    {
        $actionId = trim($actionId);
        if ($actionId === '') {
            return 0;
        }
        $folded = self::foldMessage($message);
        if ($folded === '') {
            return 0;
        }

        $total = 0;
        foreach (self::scoreAdjustments() as $block) {
            if (!is_array($block)) {
                continue;
            }
            $whenRule = trim((string) ($block['when_rule'] ?? ''));
            if ($whenRule === '' || !self::ruleMatches($whenRule, $message)) {
                continue;
            }
            $whenAlso = trim((string) ($block['when_also'] ?? ''));
            if ($whenAlso !== '' && !self::ruleMatches($whenAlso, $message)) {
                continue;
            }
            $whenNot = trim((string) ($block['when_not_rule'] ?? ''));
            if ($whenNot !== '' && self::ruleMatches($whenNot, $message)) {
                continue;
            }
            foreach ($block['apply'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $delta = (int) ($row['delta'] ?? 0);
                if ($delta === 0) {
                    continue;
                }
                $targetId = trim((string) ($row['intent_id'] ?? ''));
                $prefix = trim((string) ($row['intent_id_prefix'] ?? ''));
                if ($targetId !== '' && $targetId === $actionId) {
                    $total += $delta;
                    continue;
                }
                if ($prefix !== '' && strncmp($actionId, $prefix, strlen($prefix)) === 0) {
                    $total += $delta;
                }
            }
        }

        return $total;
    }

    /**
     * @return array{item: UiActionCatalogItem, confidence: float, method: string}|null
     */
    public static function resolveOperationalFallback(string $message, UiActionCatalog $catalog): ?array
    {
        foreach (self::operationalFallbacks() as $fb) {
            if (!is_array($fb)) {
                continue;
            }
            if (!empty($fb['requires_staff_data_access'])
                && !self::isStaffDataAccessOperationalQuery($message)) {
                continue;
            }
            $whenAny = $fb['when_any_rule'] ?? [];
            if (!is_array($whenAny) || !self::matchesAnyRule($message, $whenAny)) {
                continue;
            }
            $intentId = trim((string) ($fb['intent_id'] ?? ''));
            if ($intentId === '') {
                continue;
            }
            $item = $catalog->byActionId[$intentId] ?? null;
            if (!$item instanceof UiActionCatalogItem) {
                continue;
            }

            return [
                'item' => $item,
                'confidence' => (float) ($fb['confidence'] ?? 0.9),
                'method' => trim((string) ($fb['method'] ?? 'rules_declarative_fallback')),
            ];
        }

        return null;
    }

    /**
     * Pistas para el prompt de IA (sin intent_id fijos).
     *
     * @return list<string>
     */
    public static function aiPromptHintLines(): array
    {
        $out = [];
        foreach (self::matchRules() as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $hint = trim((string) ($rule['ai_hint'] ?? ''));
            if ($hint !== '') {
                $out[] = $hint;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [
            'vocab' => [],
            'match_rules' => [],
            'score_adjustments' => [],
            'operational_fallbacks' => [],
            'chat_preprocess' => [],
            'conversational_channel' => [],
        ];

        $path = ProductMetadataPaths::intentClassificationRulesFile();
        if (!is_file($path)) {
            return self::$config;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('IntentClassificationRulesService: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }
        if (!is_array($data)) {
            return self::$config;
        }
        foreach (['vocab', 'match_rules', 'score_adjustments', 'operational_fallbacks', 'chat_preprocess', 'conversational_channel'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                self::$config[$key] = $data[$key];
            }
        }

        return self::$config;
    }

    /**
     * @return array<string, mixed>
     */
    private static function chatPreprocessSection(): array
    {
        $section = self::loadConfig()['chat_preprocess'] ?? [];

        return is_array($section) ? $section : [];
    }

    /**
     * @return list<string>
     */
    private static function staffDataAccessQueryRules(): array
    {
        $configured = self::chatPreprocessSection()['staff_data_access_rules']['query_any'] ?? null;
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        return self::STAFF_DATA_ACCESS_QUERY_RULES;
    }

    private static function staffDataAccessEditRule(): string
    {
        $configured = self::chatPreprocessSection()['staff_data_access_rules']['edit'] ?? null;

        return is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : 'staff_data_access_edit';
    }

    /**
     * @return list<string>
     */
    private static function staffDataAccessOperationalRules(): array
    {
        $configured = self::chatPreprocessSection()['staff_data_access_rules']['operational_any'] ?? null;
        if (is_array($configured) && $configured !== []) {
            return array_values(array_filter(array_map('strval', $configured)));
        }

        return self::STAFF_DATA_ACCESS_OPERATIONAL_RULES;
    }

    /**
     * @return array<string, mixed>
     */
    private static function vocab(): array
    {
        $v = self::loadConfig()['vocab'] ?? [];

        return is_array($v) ? $v : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function matchRules(): array
    {
        $r = self::loadConfig()['match_rules'] ?? [];

        return is_array($r) ? $r : [];
    }

    /**
     * @return list<mixed>
     */
    private static function scoreAdjustments(): array
    {
        $r = self::loadConfig()['score_adjustments'] ?? [];

        return is_array($r) ? array_values($r) : [];
    }

    /**
     * @return list<mixed>
     */
    private static function operationalFallbacks(): array
    {
        $r = self::loadConfig()['operational_fallbacks'] ?? [];

        return is_array($r) ? array_values($r) : [];
    }

    /**
     * @param array<string, mixed> $rule
     */
    private static function evaluateMatchRule(array $rule, string $folded): bool
    {
        foreach ($rule['forbid_any'] ?? [] as $key) {
            if (!is_string($key)) {
                continue;
            }
            if (self::patternMatches($key, $folded)) {
                return false;
            }
        }

        foreach ($rule['require_all'] ?? [] as $key) {
            if (!is_string($key) || !self::patternMatches($key, $folded)) {
                return false;
            }
        }

        $requireAny = $rule['require_any'] ?? [];
        if (is_array($requireAny) && $requireAny !== []) {
            $hit = false;
            foreach ($requireAny as $key) {
                if (is_string($key) && self::patternMatches($key, $folded)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }

        $phrases = $rule['require_phrase_any'] ?? null;
        if (is_string($phrases) && $phrases !== '') {
            $phraseList = self::vocab()[$phrases] ?? [];
            if (is_array($phraseList) && $phraseList !== []) {
                $hit = false;
                foreach ($phraseList as $phrase) {
                    if (is_string($phrase) && $phrase !== '' && str_contains($folded, self::foldAccents(mb_strtolower($phrase, 'UTF-8')))) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function patternMatches(string $vocabKey, string $folded): bool
    {
        $vocabKey = trim($vocabKey);
        if ($vocabKey === '') {
            return false;
        }
        $pattern = self::vocab()[$vocabKey] ?? null;
        if (!is_string($pattern) || trim($pattern) === '') {
            return false;
        }

        return @preg_match('/' . $pattern . '/u', $folded) === 1;
    }

    private static function foldMessage(string $message): string
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($lower === '') {
            return '';
        }

        return self::foldAccents($lower);
    }

    private static function foldAccents(string $text): string
    {
        return strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }
}
